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
	protected $signature = 'MercadoLibreInventorySync:task {--limit=50} {--sku= : Only this Linnworks SKU} {--create-listings : Publish Linnworks SKUs that have no ML listing}';
	protected $description = 'Bidirectional stock sync and LW→ML catalog (title, price, pictures, description); optionally create listings.';

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

			if ($this->option('create-listings')) {
				$this->createListingsFromLinnworks($account, $meli, $inventory);
			}
		}

		return self::SUCCESS;
	}

	private function syncAccount(MercadoLibreAccount $account, MercadoLibreService $meli, InventoryService $inventory): void
	{
		$user = $account->linnworkUser ?: LinnworkUser::where('login_status', true)->first();
		$locationId = $this->locationId($user, $inventory);

		$ids = $meli->searchItemIds($account, (int) $this->option('limit'), 0);
		if ($ids === null) {
			$this->error('items/search failed for ' . $account->ml_user_id . ($meli->lastError ? ' — '.$meli->lastError : ''));
			$this->warn('En DevCenter de la app ML activa scopes Lectura/Escritura (publicaciones) y vuelve a autorizar el vendedor en /auth/mercadolibre.');
			return;
		}

		$linked = 0;
		$createdLw = 0;
		$toMl = 0;
		$toLw = 0;
		$titlesToMl = 0;
		$pricesToMl = 0;
		$picturesToMl = 0;
		$descriptionsToMl = 0;

		foreach ($ids as $itemId) {
			$item = $meli->getItem($account, (string) $itemId);
			if (!$item) {
				continue;
			}

			foreach ($meli->itemToListingRows($item) as $row) {
				$listing = MercadoLibreListing::firstOrNew([
					'mercadolibre_account_id' => $account->id,
					'ml_item_id' => $row['ml_item_id'],
					'ml_variation_id' => $row['ml_variation_id'],
				]);

				$prevMl = $listing->exists && $listing->available_quantity !== null ? (int) $listing->available_quantity : null;
				$prevLw = $listing->exists && $listing->linnworks_quantity !== null ? (int) $listing->linnworks_quantity : null;

				$listing->fill([
					'sku' => $row['sku'] ?: $listing->sku,
					'title' => $row['title'],
					'available_quantity' => $row['available_quantity'],
					'price' => $row['price'],
					'ml_status' => $row['ml_status'] ?? $listing->ml_status,
					'ml_updated_at' => $row['ml_updated_at'] ? date('Y-m-d H:i:s', strtotime($row['ml_updated_at'])) : now(),
					'source' => $listing->source ?: 'ml',
				]);
				$listing->save();

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
						'last_error' => null,
					]);
					$createdLw++;
					$linked++;
					continue;
				}

				$stockItemId = $lwItem['StockItemId'] ?? $lwItem['Id'] ?? $listing->linnworks_stock_item_id;
				$lwQty = $inventory->stockLevelFromItem($lwItem, $locationId);
				if ($lwQty === null) {
					$lwQty = (int) ($lwItem['Quantity'] ?? 0);
				}
				$lwTitle = $inventory->titleFromItem($lwItem);

				$listing->update([
					'linnworks_stock_item_id' => $stockItemId,
					'linnworks_quantity' => $lwQty,
				]);
				$linked++;

				if (
					!(int) $listing->ml_variation_id
					&& $lwTitle !== ''
					&& MercadoLibreListing::titlesDiffer($listing->title, $lwTitle)
				) {
					$ok = $meli->updateItemTitle($account, $listing->ml_item_id, $lwTitle, $item);
					if ($ok) {
						$listing->update([
							'title' => mb_substr($lwTitle, 0, 60),
							'last_sync_direction' => 'lw_to_ml',
							'lw_updated_at' => now(),
							'last_error' => null,
						]);
						$titlesToMl++;
					} else {
						$listing->update([
							'last_error' => mb_substr($meli->lastError ?: 'Title update failed', 0, 1000),
						]);
					}
				}

				$lwPrice = $inventory->priceFromItem($lwItem);
				if (
					!(int) $listing->ml_variation_id
					&& $lwPrice !== null
					&& $lwPrice > 0
					&& MercadoLibreListing::pricesDiffer((float) ($listing->price ?? 0), $lwPrice)
				) {
					if (MercadoLibreService::itemHasPriceAutomation($item)) {
						$listing->update(['last_error' => 'Price not updated (ML price automation)']);
					} else {
						$ok = $meli->updateItemPrice(
							$account,
							$listing->ml_item_id,
							$lwPrice,
							(int) ($lwQty ?? $listing->available_quantity ?? 0)
						);
						if ($ok) {
							$listing->update([
								'price' => $lwPrice,
								'last_sync_direction' => 'lw_to_ml',
								'lw_updated_at' => now(),
								'last_error' => null,
							]);
							$pricesToMl++;
						} else {
							$listing->update([
								'last_error' => mb_substr($meli->lastError ?: 'Price update failed', 0, 1000),
							]);
						}
					}
				}

				$lwImages = $inventory->imageUrlsFromItem($lwItem);
				if (!$lwImages && $stockItemId) {
					$lwImages = $inventory->GetInventoryItemImages($user->token, $user->server, (string) $stockItemId);
				}
				if (
					!(int) $listing->ml_variation_id
					&& MercadoLibreListing::picturesShouldPush($lwImages, $listing->lw_pictures_hash)
				) {
					$ok = $meli->updateItemPictures($account, $listing->ml_item_id, $lwImages);
					if ($ok) {
						$listing->update([
							'lw_pictures_hash' => MercadoLibreListing::picturesHash($lwImages),
							'last_sync_direction' => 'lw_to_ml',
							'lw_updated_at' => now(),
							'last_error' => null,
						]);
						$picturesToMl++;
					} else {
						$listing->update([
							'last_error' => mb_substr($meli->lastError ?: 'Pictures update failed', 0, 1000),
						]);
					}
				}

				$lwDesc = $inventory->descriptionFromItem($lwItem);
				if (!(int) $listing->ml_variation_id && $lwDesc !== '') {
					$mlDesc = $meli->getItemDescription($account, $listing->ml_item_id);
					if ($mlDesc !== null && MercadoLibreListing::titlesDiffer($mlDesc, $lwDesc)) {
						$ok = $meli->updateItemDescription($account, $listing->ml_item_id, $lwDesc);
						if ($ok) {
							$listing->update([
								'last_sync_direction' => 'lw_to_ml',
								'lw_updated_at' => now(),
								'last_error' => null,
							]);
							$descriptionsToMl++;
						} else {
							$listing->update([
								'last_error' => mb_substr($meli->lastError ?: 'Description update failed', 0, 1000),
							]);
						}
					}
				}

				$direction = MercadoLibreListing::stockSyncDirection(
					(int) $listing->available_quantity,
					(int) $lwQty,
					true,
					$prevMl,
					$prevLw
				);

				if ($direction === 'lw_to_ml' && (int) $listing->available_quantity !== (int) $lwQty) {
					$ok = $meli->updateItemQuantity(
						$account,
						$listing->ml_item_id,
						(int) $lwQty,
						$listing->ml_variation_id ?: null
					);
					if ($ok) {
						$listing->update([
							'available_quantity' => (int) $lwQty,
							'last_sync_direction' => 'lw_to_ml',
							'lw_updated_at' => now(),
							'last_error' => null,
						]);
						$toMl++;
					}
				}

				if ($direction === 'ml_to_lw' && (int) $listing->available_quantity !== (int) $lwQty) {
					$set = $inventory->SetStockLevel(
						$user->token,
						$user->server,
						$listing->sku,
						$locationId,
						(int) $listing->available_quantity
					);
					if ($set !== false) {
						$listing->update([
							'linnworks_quantity' => (int) $listing->available_quantity,
							'last_sync_direction' => 'ml_to_lw',
							'lw_updated_at' => now(),
							'last_error' => null,
						]);
						$toLw++;
					}
				}
			}
		}

		$this->info(sprintf(
			'ML user %s: %d items scanned, %d linked, %d created in LW, %d stock → ML, %d stock → LW, %d title → ML, %d price → ML, %d pictures → ML, %d description → ML',
			$account->ml_user_id,
			count($ids),
			$linked,
			$createdLw,
			$toMl,
			$toLw,
			$titlesToMl,
			$pricesToMl,
			$picturesToMl,
			$descriptionsToMl
		));
	}

	private function createListingsFromLinnworks(MercadoLibreAccount $account, MercadoLibreService $meli, InventoryService $inventory): void
	{
		$user = $account->linnworkUser ?: LinnworkUser::where('login_status', true)->first();
		if (!$user || !$user->token) {
			$this->error('No Linnworks user/token to create listings.');
			return;
		}

		$locationId = $this->locationId($user, $inventory);
		$defaults = $meli->listingDefaults($account);
		if (!$defaults) {
			$this->error('Cannot resolve ML site/listing type.');
			return;
		}

		$listed = MercadoLibreListing::where('mercadolibre_account_id', $account->id)
			->whereNotNull('ml_item_id')
			->where('ml_item_id', '!=', '')
			->pluck('sku')
			->filter()
			->map(fn ($sku) => mb_strtolower((string) $sku))
			->all();
		$listed = array_fill_keys($listed, true);

		$onlySku = trim((string) $this->option('sku'));
		$limit = max(1, (int) $this->option('limit'));
		$created = 0;
		$skipped = 0;
		$failed = 0;

		if ($onlySku !== '') {
			$item = $inventory->GetStockItemBySKU($user->token, $user->server, $onlySku);
			if ($item === false || !$item) {
				$this->error('SKU not found in Linnworks: '.$onlySku);
				return;
			}
			$this->tryPublishListing($account, $meli, $inventory, $user, $locationId, $defaults, $listed, $item, $created, $skipped, $failed);
			$this->info(sprintf(
				'Create listings: %d published, %d skipped (missing data), %d failed',
				$created,
				$skipped,
				$failed
			));
			return;
		}

		$page = 1;
		while ($created < $limit) {
			$items = $inventory->GetStockItemsPage($user->token, $user->server, $page, 50);
			if ($items === false) {
				$this->error('GetStockItemsFull failed.');
				break;
			}
			if (!$items) {
				break;
			}

			foreach ($items as $item) {
				if ($created >= $limit) {
					break 2;
				}
				$this->tryPublishListing($account, $meli, $inventory, $user, $locationId, $defaults, $listed, $item, $created, $skipped, $failed);
			}

			$page++;
			if (count($items) < 50) {
				break;
			}
		}

		$this->info(sprintf(
			'Create listings: %d published, %d skipped (missing data), %d failed',
			$created,
			$skipped,
			$failed
		));
	}

	private function tryPublishListing(
		MercadoLibreAccount $account,
		MercadoLibreService $meli,
		InventoryService $inventory,
		LinnworkUser $user,
		?string $locationId,
		array $defaults,
		array &$listed,
		array $item,
		int &$created,
		int &$skipped,
		int &$failed
	): void {
		if (!empty($item['IsVariationParent'])) {
			return;
		}

		$sku = $item['ItemNumber'] ?? $item['SKU'] ?? null;
		if (!is_string($sku) || $sku === '') {
			return;
		}

		if (isset($listed[mb_strtolower($sku)])) {
			return;
		}

		$title = $inventory->titleFromItem($item) ?: (string) $sku;
		$price = isset($item['RetailPrice']) ? (float) $item['RetailPrice'] : null;
		$qty = $inventory->stockLevelFromItem($item, $locationId);
		if ($qty === null) {
			$qty = (int) ($item['Quantity'] ?? 0);
		}
		$stockItemId = $item['StockItemId'] ?? $item['Id'] ?? null;
		$images = $inventory->imageUrlsFromItem($item);
		if (!$images && $stockItemId) {
			$images = $inventory->GetInventoryItemImages($user->token, $user->server, (string) $stockItemId);
		}
		$barcode = $item['BarcodeNumber'] ?? $item['Barcode'] ?? null;
		$barcode = is_string($barcode) && $barcode !== '' ? $barcode : null;

		$blockers = MercadoLibreListing::createListingBlockers($title, $price, (int) $qty, $images);
		if ($blockers) {
			$skipped++;
			return;
		}

		$predicted = $meli->predictCategory($account, $title);
		if (!$predicted) {
			$this->rememberListingError($account, $sku, $title, $stockItemId, (int) $qty, $price, $meli->lastError ?: 'Category predictor failed');
			$failed++;
			return;
		}

		$assembled = $meli->assembleAttributes(
			$sku,
			$barcode,
			$predicted['attributes'] ?? [],
			$meli->categoryAttributes($account, (string) $predicted['category_id'])
		);
		if (!$assembled['ok']) {
			$this->rememberListingError($account, $sku, $title, $stockItemId, (int) $qty, $price, $assembled['error']);
			$failed++;
			return;
		}

		$payload = $meli->buildItemPayload(
			$defaults,
			$title,
			(string) $predicted['category_id'],
			(float) $price,
			(int) $qty,
			$images,
			$assembled['attributes']
		);

		$createdItem = $meli->createItem($account, $payload);
		if (!$createdItem || empty($createdItem['id'])) {
			$this->rememberListingError($account, $sku, $title, $stockItemId, (int) $qty, $price, $meli->lastError ?: 'Create item failed');
			$failed++;
			return;
		}

		MercadoLibreListing::updateOrCreate(
			[
				'mercadolibre_account_id' => $account->id,
				'ml_item_id' => (string) $createdItem['id'],
				'ml_variation_id' => 0,
			],
			[
				'sku' => $sku,
				'title' => $createdItem['title'] ?? $title,
				'linnworks_stock_item_id' => $stockItemId,
				'available_quantity' => (int) ($createdItem['available_quantity'] ?? $qty),
				'linnworks_quantity' => (int) $qty,
				'price' => (float) ($createdItem['price'] ?? $price),
				'ml_status' => $createdItem['status'] ?? 'active',
				'lw_pictures_hash' => MercadoLibreListing::picturesHash($images),
				'ml_updated_at' => now(),
				'lw_updated_at' => now(),
				'last_sync_direction' => 'create',
				'source' => 'lw',
				'category_id' => (string) $predicted['category_id'],
				'permalink' => $createdItem['permalink'] ?? null,
				'last_error' => null,
			]
		);

		$desc = $inventory->descriptionFromItem($item);
		if ($desc !== '') {
			$meli->updateItemDescription($account, (string) $createdItem['id'], $desc);
		}

		MercadoLibreListing::query()
			->where('mercadolibre_account_id', $account->id)
			->where('sku', $sku)
			->where(function ($q) {
				$q->whereNull('ml_item_id')->orWhere('ml_item_id', '');
			})
			->delete();

		$listed[mb_strtolower($sku)] = true;
		$created++;
	}

	private function rememberListingError(
		MercadoLibreAccount $account,
		string $sku,
		string $title,
		?string $stockItemId,
		int $qty,
		?float $price,
		string $error
	): void {
		$listing = MercadoLibreListing::query()
			->where('mercadolibre_account_id', $account->id)
			->where('sku', $sku)
			->where(function ($q) {
				$q->whereNull('ml_item_id')->orWhere('ml_item_id', '');
			})
			->first();

		$payload = [
			'mercadolibre_account_id' => $account->id,
			'ml_item_id' => null,
			'ml_variation_id' => 0,
			'sku' => $sku,
			'title' => $title,
			'linnworks_stock_item_id' => $stockItemId,
			'linnworks_quantity' => $qty,
			'price' => $price,
			'source' => 'lw',
			'last_error' => mb_substr($error, 0, 1000),
		];

		if ($listing) {
			$listing->update($payload);
			return;
		}

		MercadoLibreListing::create($payload);
	}

	private function locationId(?LinnworkUser $user, InventoryService $inventory): ?string
	{
		if (!$user || !$user->token) {
			return null;
		}

		$locations = $inventory->GetStockLocations($user->token, $user->server);
		if (!is_array($locations) || !$locations) {
			return null;
		}

		return $locations[0]['StockLocationId'] ?? null;
	}
}
