<?php

namespace App\Console\Commands;

use App\Models\LinnworkUser;
use App\Models\MercadoLibreAccount;
use App\Models\MercadoLibreOrder;
use App\Services\InventoryService;
use App\Services\MercadoLibreService;
use App\Services\OrderService;
use App\Traits\CustomLogger;
use Illuminate\Console\Command;

class MercadoLibreSyncOrders extends Command
{
	use CustomLogger;

	protected $signature = 'MercadoLibreSync:task {--offset=0} {--limit=50}';
	protected $description = 'Pull ML orders, refresh Full status, and push Linnworks tracking to ML (non-Full).';

	public function handle(MercadoLibreService $meli, OrderService $orders, InventoryService $inventory)
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

			$this->syncAccount($account, $meli, $orders, $inventory);
		}

		return self::SUCCESS;
	}

	private function syncAccount(MercadoLibreAccount $account, MercadoLibreService $meli, OrderService $orders, InventoryService $inventory): void
	{
		$search = $meli->searchOrders($account, [
			'offset' => (int) $this->option('offset'),
			'limit' => (int) $this->option('limit'),
		]);

		if ($search === null) {
			$this->error('orders/search failed for ' . $account->ml_user_id);
			return;
		}

		$results = $search['results'] ?? [];
		$lwIndex = $this->linnworksOrderIndex($account, $orders, $inventory);
		$openCount = 0;

		foreach ($results as $mlOrder) {
			$orderId = $mlOrder['id'] ?? null;
			if (!$orderId) {
				continue;
			}

			$shipment = $meli->getOrderShipment($account, $orderId) ?? [];
			$logisticType = $shipment['logistic_type']
				?? ($mlOrder['shipping']['logistic_type'] ?? null);
			$shippingStatus = $shipment['status'] ?? ($mlOrder['shipping']['status'] ?? null);
			$tracking = $shipment['tracking_number']
				?? ($shipment['tracking_method'] ?? null);
			$orderStatus = $mlOrder['status'] ?? null;

			$record = MercadoLibreOrder::updateOrCreate(
				['ml_order_id' => $orderId],
				[
					'mercadolibre_account_id' => $account->id,
					'pack_id' => $mlOrder['pack_id'] ?? null,
					'shipment_id' => $shipment['id'] ?? ($mlOrder['shipping']['id'] ?? null),
					'status' => $orderStatus,
					'shipping_status' => $shippingStatus,
					'shipping_substatus' => $shipment['substatus'] ?? null,
					'logistic_type' => $logisticType,
					'tracking_number' => $tracking,
					'payload' => [
						'order' => $mlOrder,
						'shipment' => $shipment ?: null,
					],
				]
			);

			// Closed / cancelled: keep DB row, skip active fulfillment sync.
			if (MercadoLibreOrder::isTerminalStatus($orderStatus, $shippingStatus)) {
				if ($record->last_notified_status !== 'closed') {
					$record->update(['last_notified_status' => 'closed']);
				}
				continue;
			}

			$openCount++;

			$lwOrder = $this->matchLinnworksOrder($lwIndex, $mlOrder, $record);
			if ($lwOrder && !$record->linnworks_order_id) {
				$record->update(['linnworks_order_id' => $lwOrder['OrderId'] ?? $lwOrder['orderId'] ?? null]);
			}

			// Linked before but no longer in Linnworks open orders → mark closed locally (no auto-delivered on ML).
			if ($record->linnworks_order_id && !$lwOrder) {
				$record->update(['last_notified_status' => 'closed']);
				continue;
			}

			if ($record->isFullFulfillment()) {
				$this->syncFullToLinnworks($account, $record, $lwOrder, $orders);
				continue;
			}

			$this->pushLinnworksTrackingToMl($account, $record, $lwOrder, $meli);
		}

		$this->info('ML user ' . $account->ml_user_id . ': ' . $openCount . ' open orders');
	}

	/**
	 * Full: ML owns the shipment. Mirror tracking into Linnworks if we have a match.
	 */
	private function syncFullToLinnworks(MercadoLibreAccount $account, MercadoLibreOrder $record, ?array $lwOrder, OrderService $orders): void
	{
		if (!$lwOrder || !$record->tracking_number) {
			return;
		}

		$user = $account->linnworkUser;
		if (!$user || !$user->token) {
			return;
		}

		$existing = $lwOrder['ShippingInfo']['TrackingNumber'] ?? '';
		if ($existing) {
			return;
		}

		$orders->SetOrderShippingInfo($user->token, $user->server, $lwOrder['OrderId'], [
			'PostalServiceId' => $lwOrder['ShippingInfo']['PostalServiceId'] ?? null,
			'PostalServiceName' => $lwOrder['ShippingInfo']['PostalServiceName'] ?? 'Mercado Envíos Full',
			'Vendor' => $lwOrder['ShippingInfo']['Vendor'] ?? 'MercadoLibre',
			'TrackingNumber' => $record->tracking_number,
		]);
	}

	/**
	 * Seller-fulfilled: if Linnworks already has tracking, notify ML (shipped).
	 */
	private function pushLinnworksTrackingToMl(MercadoLibreAccount $account, MercadoLibreOrder $record, ?array $lwOrder, MercadoLibreService $meli): void
	{
		if (!$lwOrder || !$record->shipment_id) {
			return;
		}

		$tracking = $lwOrder['ShippingInfo']['TrackingNumber'] ?? null;
		if (!$tracking) {
			return;
		}

		if (in_array($record->shipping_status, ['shipped', 'delivered', 'not_delivered', 'cancelled'], true)) {
			return;
		}

		if ($record->last_notified_status === 'shipped') {
			return;
		}

		$ok = $meli->notifyShipment($account, $record->shipment_id, 'shipped', null, $tracking, 'Dispatched from Linnworks');
		if ($ok) {
			$record->update([
				'last_notified_status' => 'shipped',
				'tracking_number' => $tracking,
				'shipping_status' => 'shipped',
			]);
		}
	}

	private function matchLinnworksOrder(array $index, array $mlOrder, MercadoLibreOrder $record): ?array
	{
		$keys = array_filter([
			(string) ($mlOrder['id'] ?? ''),
			(string) ($mlOrder['pack_id'] ?? ''),
			(string) ($record->ml_order_id ?? ''),
			(string) ($record->pack_id ?? ''),
		]);

		foreach ($keys as $key) {
			if (isset($index[$key])) {
				return $index[$key];
			}
		}

		return null;
	}

	private function linnworksOrderIndex(MercadoLibreAccount $account, OrderService $orders, InventoryService $inventory): array
	{
		$user = $account->linnworkUser ?: LinnworkUser::where('login_status', true)->first();
		if (!$user || !$user->token) {
			return [];
		}

		$locations = $inventory->GetStockLocations($user->token, $user->server);
		if ($locations === false) {
			return [];
		}

		$index = [];

		foreach ($locations as $location) {
			$ids = $orders->GetAllOpenOrders($user->token, $user->server, $location['StockLocationId']);
			if ($ids === false || !$ids) {
				continue;
			}

			$details = $orders->GetOrders($user->token, $user->server, $ids);
			if ($details === false) {
				continue;
			}

			foreach ($details as $order) {
				$source = strtolower($order['GeneralInfo']['Source'] ?? '');
				$sub = strtolower($order['GeneralInfo']['SubSource'] ?? '');
				if (strpos($source, 'mercado') === false && strpos($sub, 'mercado') === false && strpos($source, 'meli') === false) {
					continue;
				}

				$refs = array_filter([
					(string) ($order['GeneralInfo']['ReferenceNum'] ?? ''),
					(string) ($order['GeneralInfo']['SecondaryReference'] ?? ''),
					(string) ($order['GeneralInfo']['ExternalReference'] ?? ''),
				]);

				foreach ($refs as $ref) {
					$index[$ref] = $order;
				}
			}
		}

		return $index;
	}
}
