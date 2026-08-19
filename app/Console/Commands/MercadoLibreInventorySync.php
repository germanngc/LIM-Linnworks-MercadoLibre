<?php

namespace App\Console\Commands;

use App\Models\LinnworkUser;
use App\Models\MercadoLibreAccount;
use App\Models\MercadoLibreListing;
use App\Services\InventoryService;
use App\Services\MercadoLibreService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class MercadoLibreInventorySync extends Command
{
	protected $signature = 'MercadoLibreInventorySync:task {--limit=50}';
	protected $description = 'Sync ML listings/stock with Linnworks inventory (ML create → LW; LW stock → ML).';

	public function handle(MercadoLibreService $meli, InventoryService $inventory)
	{
		$accounts = MercadoLibreAccount::query()->get();

		if ($accounts->isEmpty()) {
			$this->warn('No Mercado Libre accounts. Visit /auth/mercadolibre first.');
			return self::SUCCESS;
		}

		foreach ($accounts as $account) {
			if (!$meli->ensureToken($account)) {
				$this->error('Cannot refresh token for ML user ' . $account->ml_user_id);
				continue;
			}

			$this->syncAccount($account, $meli, $inventory);
		}

		return self::SUCCESS;
	}

	private function syncAccount(MercadoLibreAccount $account, MercadoLibreService $meli, InventoryService $inventory): void
	{
		$user = $account->linnworkUser ?: LinnworkUser::where('login_status', true)->first();
		$locationId = null;

		if ($user && $user->token) {
			$locations = $inventory->GetStockLocations($user->token, $user->server);
			if (is_array($locations) && $locations) {
				$locationId = $locations[0]['StockLocationId'] ?? null;
			}
		}

		$ids = $meli->searchItemIds($account, (int) $this->option('limit'), 0);
		if ($ids === null) {
			$this->error('items/search failed for ' . $account->ml_user_id . ($meli->lastError ? ' — '.$meli->lastError : ''));
			$this->warn('En DevCenter de la app ML activa scopes Lectura/Escritura (publicaciones) y vuelve a autorizar el vendedor en /auth/mercadolibre.');
			return;
		}

		$linked = 0;
		$pushed = 0;
		$created = 0;

		foreach ($ids as $itemId) {
			$item = $meli->getItem($account, (string) $itemId);
			if (!$item) {
				continue;
			}

			foreach ($meli->itemToListingRows($item) as $row) {
				$listing = MercadoLibreListing::updateOrCreate(
					[
						'mercadolibre_account_id' => $account->id,
						'ml_item_id' => $row['ml_item_id'],
						'ml_variation_id' => $row['ml_variation_id'],
					],
					[
						'sku' => $row['sku'],
						'title' => $row['title'],
						'available_quantity' => $row['available_quantity'],
						'price' => $row['price'],
						'ml_updated_at' => $row['ml_updated_at'] ? date('Y-m-d H:i:s', strtotime($row['ml_updated_at'])) : now(),
					]
				);

				if (!$user || !$user->token || !$locationId || !$listing->sku) {
					continue;
				}

				$lwItem = $inventory->GetStockItemBySKU($user->token, $user->server, $listing->sku);
				if ($lwItem === false) {
					continue;
				}

				if (!$lwItem) {
					$stockItemId = (string) Str::uuid();
					$createdItem = $inventory->AddInventoryItem(
						$user->token,
						$user->server,
						$stockItemId,
						$listing->sku,
						(string) ($listing->title ?: $listing->sku),
						(float) ($listing->price ?? 0)
					);

					if ($createdItem === false) {
						continue;
					}

					$inventory->SetStockLevel(
						$user->token,
						$user->server,
						$listing->sku,
						$locationId,
						(int) $listing->available_quantity
					);

					$listing->update([
						'linnworks_stock_item_id' => $stockItemId,
						'linnworks_quantity' => (int) $listing->available_quantity,
						'lw_updated_at' => now(),
						'last_sync_direction' => 'ml_to_lw',
					]);
					$created++;
					$linked++;
					continue;
				}

				$stockItemId = $lwItem['StockItemId'] ?? $lwItem['Id'] ?? $listing->linnworks_stock_item_id;
				$lwQty = $inventory->stockLevelFromItem($lwItem, $locationId);
				if ($lwQty === null) {
					$lwQty = (int) ($lwItem['Quantity'] ?? 0);
				}

				$listing->update([
					'linnworks_stock_item_id' => $stockItemId,
					'linnworks_quantity' => $lwQty,
				]);
				$linked++;

				$direction = MercadoLibreListing::stockSyncDirection(
					(int) $listing->available_quantity,
					(int) $lwQty,
					true
				);

				if ($direction === 'lw_to_ml' && (int) $listing->available_quantity !== (int) $lwQty) {
					$variationId = $listing->ml_variation_id ?: null;
					$ok = $meli->updateItemQuantity(
						$account,
						$listing->ml_item_id,
						(int) $lwQty,
						$variationId ?: null
					);
					if ($ok) {
						$listing->update([
							'available_quantity' => (int) $lwQty,
							'last_sync_direction' => 'lw_to_ml',
							'lw_updated_at' => now(),
						]);
						$pushed++;
					}
				}
			}
		}

		$this->info(sprintf(
			'ML user %s: %d items scanned, %d linked, %d created in LW, %d stock pushes to ML',
			$account->ml_user_id,
			count($ids),
			$linked,
			$created,
			$pushed
		));
	}
}
