<?php

namespace App\Services;

use App\Models\MercadoLibreAccount;
use App\Traits\CustomLogger;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MercadoLibreService
{
	use CustomLogger;

	public ?string $lastError = null;

	private function api()
	{
		return rtrim(config('services.mercadolibre.api_url'), '/');
	}

	private function authHeaders(MercadoLibreAccount $account): array
	{
		return [
			'Authorization' => 'Bearer ' . $account->access_token,
			'Accept' => 'application/json',
		];
	}

	public function exchangeCode(string $code, ?string $codeVerifier = null): ?array
	{
		$payload = [
			'grant_type' => 'authorization_code',
			'client_id' => config('services.mercadolibre.client_id'),
			'client_secret' => config('services.mercadolibre.client_secret'),
			'code' => $code,
			'redirect_uri' => config('services.mercadolibre.redirect'),
		];

		if ($codeVerifier) {
			$payload['code_verifier'] = $codeVerifier;
		}

		$response = Http::asForm()->acceptJson()->post($this->api() . '/oauth/token', $payload);

		if ($response->failed()) {
			Log::error('ML oauth token failed', ['body' => $response->body()]);
			return null;
		}

		return $response->json();
	}

	public function refreshToken(MercadoLibreAccount $account): bool
	{
		if (!$account->refresh_token) {
			return false;
		}

		$response = Http::asForm()->acceptJson()->post($this->api() . '/oauth/token', [
			'grant_type' => 'refresh_token',
			'client_id' => config('services.mercadolibre.client_id'),
			'client_secret' => config('services.mercadolibre.client_secret'),
			'refresh_token' => $account->refresh_token,
		]);

		if ($response->failed()) {
			Log::error('ML refresh token failed', ['ml_user_id' => $account->ml_user_id, 'body' => $response->body()]);
			return false;
		}

		$data = $response->json();
		$account->update([
			'access_token' => $data['access_token'],
			'refresh_token' => $data['refresh_token'] ?? $account->refresh_token,
			'expires_at' => now()->addSeconds(($data['expires_in'] ?? 21600) - 60),
		]);

		return true;
	}

	public function ensureToken(MercadoLibreAccount $account): bool
	{
		if (!$account->tokenExpired()) {
			return true;
		}

		return $this->refreshToken($account);
	}

	public function getMe(string $accessToken): ?array
	{
		$response = Http::withToken($accessToken)->acceptJson()->get($this->api() . '/users/me');

		if ($response->failed()) {
			Log::error('ML users/me failed', ['body' => $response->body()]);
			return null;
		}

		return $response->json();
	}

	public function searchOrders(MercadoLibreAccount $account, array $query = []): ?array
	{
		$query = array_merge([
			'seller' => $account->ml_user_id,
			'sort' => 'date_desc',
			'limit' => 50,
			'offset' => 0,
		], $query);

		$response = Http::withHeaders($this->authHeaders($account))
			->get($this->api() . '/orders/search', $query);

		if ($response->failed()) {
			Log::error('ML orders/search failed', ['body' => $response->body()]);
			return null;
		}

		return $response->json();
	}

	public function getOrder(MercadoLibreAccount $account, $orderId): ?array
	{
		$response = Http::withHeaders($this->authHeaders($account))
			->get($this->api() . '/orders/' . $orderId);

		if ($response->failed()) {
			Log::error('ML get order failed', ['order_id' => $orderId, 'body' => $response->body()]);
			return null;
		}

		return $response->json();
	}

	public function getOrderShipment(MercadoLibreAccount $account, $orderId): ?array
	{
		$response = Http::withHeaders($this->authHeaders($account))
			->get($this->api() . '/orders/' . $orderId . '/shipments');

		if ($response->failed()) {
			Log::warning('ML order shipments failed', ['order_id' => $orderId, 'body' => $response->body()]);
			return null;
		}

		return $response->json();
	}

	public function getShipment(MercadoLibreAccount $account, $shipmentId): ?array
	{
		$response = Http::withHeaders($this->authHeaders($account))
			->get($this->api() . '/shipments/' . $shipmentId);

		if ($response->failed()) {
			Log::warning('ML shipment failed', ['shipment_id' => $shipmentId, 'body' => $response->body()]);
			return null;
		}

		return $response->json();
	}

	/**
	 * ME1 / custom shipping: notify buyer of dispatch / delivery.
	 * Full (fulfillment) shipments are owned by ML — do not call this.
	 */
	public function notifyShipment(MercadoLibreAccount $account, $shipmentId, string $status, ?string $substatus = null, ?string $trackingNumber = null, ?string $comment = null): bool
	{
		$payload = [
			'status' => $status,
			'substatus' => $substatus,
			'payload' => [
				'comment' => $comment ?? $status,
				'date' => now()->toIso8601String(),
			],
		];

		if ($trackingNumber) {
			$payload['tracking_number'] = $trackingNumber;
		}

		$response = Http::withHeaders($this->authHeaders($account))
			->asJson()
			->post($this->api() . '/shipments/' . $shipmentId . '/seller_notifications', $payload);

		if ($response->failed()) {
			Log::error('ML seller_notifications failed', [
				'shipment_id' => $shipmentId,
				'status' => $status,
				'body' => $response->body(),
			]);
			return false;
		}

		return true;
	}

	public function searchFulfillmentOperations(MercadoLibreAccount $account, array $query = []): ?array
	{
		$query = array_merge([
			'seller_id' => $account->ml_user_id,
		], $query);

		$response = Http::withHeaders($this->authHeaders($account))
			->get($this->api() . '/stock/fulfillment/operations/search', $query);

		if ($response->failed()) {
			Log::warning('ML fulfillment operations failed', ['body' => $response->body()]);
			return null;
		}

		return $response->json();
	}

	/**
	 * @return string[]|null item ids; null on failure (see $lastError)
	 */
	public function searchItemIds(MercadoLibreAccount $account, int $limit = 50, int $offset = 0): ?array
	{
		$this->lastError = null;

		$response = Http::withHeaders($this->authHeaders($account))
			->get($this->api() . '/users/' . $account->ml_user_id . '/items/search', [
				'status' => 'active',
				'limit' => $limit,
				'offset' => $offset,
			]);

		if ($response->failed()) {
			$code = $response->json('code') ?? 'http_'.$response->status();
			$msg = $response->json('message') ?? $response->body();
			$this->lastError = $response->status().' '.$code.': '.$msg;
			Log::error('ML items/search failed', [
				'status' => $response->status(),
				'body' => $response->body(),
			]);
			return null;
		}

		return $response->json('results') ?? [];
	}

	public function getItem(MercadoLibreAccount $account, string $itemId): ?array
	{
		$response = Http::withHeaders($this->authHeaders($account))
			->get($this->api() . '/items/' . $itemId);

		if ($response->failed()) {
			Log::error('ML get item failed', ['item_id' => $itemId, 'body' => $response->body()]);
			return null;
		}

		return $response->json();
	}

	public function updateItemQuantity(MercadoLibreAccount $account, string $itemId, int $quantity, ?int $variationId = null): bool
	{
		$payload = $variationId
			? ['variations' => [['id' => $variationId, 'available_quantity' => $quantity]]]
			: ['available_quantity' => $quantity];

		$response = Http::withHeaders($this->authHeaders($account))
			->asJson()
			->put($this->api() . '/items/' . $itemId, $payload);

		if ($response->failed()) {
			Log::error('ML update item quantity failed', [
				'item_id' => $itemId,
				'variation_id' => $variationId,
				'body' => $response->body(),
			]);
			return false;
		}

		return true;
	}

	/**
	 * SKU from ML item/variation: seller_custom_field, seller_sku, or attribute SELLER_SKU.
	 */
	public function extractSku(array $node, ?array $fallbackNode = null): ?string
	{
		foreach ([$node, $fallbackNode] as $source) {
			if (!$source) {
				continue;
			}
			$direct = $source['seller_custom_field'] ?? $source['seller_sku'] ?? null;
			if (is_string($direct) && $direct !== '') {
				return $direct;
			}
			foreach ($source['attributes'] ?? [] as $attr) {
				if (($attr['id'] ?? null) === 'SELLER_SKU') {
					$name = $attr['value_name'] ?? ($attr['values'][0]['name'] ?? null);
					if (is_string($name) && $name !== '') {
						return $name;
					}
				}
			}
		}

		return null;
	}

	/**
	 * Flatten an ML item into sync rows (one per variation, or one for the item).
	 * @return array<int, array{ml_item_id:string,ml_variation_id:int,sku:?string,title:?string,available_quantity:int,price:?float,ml_updated_at:?string}>
	 */
	public function itemToListingRows(array $item): array
	{
		$itemId = (string) ($item['id'] ?? '');
		$title = $item['title'] ?? null;
		$updated = $item['last_updated'] ?? $item['date_created'] ?? null;
		$variations = $item['variations'] ?? [];

		if ($variations) {
			$rows = [];
			foreach ($variations as $variation) {
				$rows[] = [
					'ml_item_id' => $itemId,
					'ml_variation_id' => (int) ($variation['id'] ?? 0),
					'sku' => $this->extractSku($variation, $item),
					'title' => $title,
					'available_quantity' => (int) ($variation['available_quantity'] ?? 0),
					'price' => isset($variation['price']) ? (float) $variation['price'] : (isset($item['price']) ? (float) $item['price'] : null),
					'ml_updated_at' => $updated,
				];
			}
			return $rows;
		}

		return [[
			'ml_item_id' => $itemId,
			'ml_variation_id' => 0,
			'sku' => $this->extractSku($item),
			'title' => $title,
			'available_quantity' => (int) ($item['available_quantity'] ?? 0),
			'price' => isset($item['price']) ? (float) $item['price'] : null,
			'ml_updated_at' => $updated,
		]];
	}

	public function saveAccount(array $tokenData, ?array $me = null, ?string $linnworkUserId = null): MercadoLibreAccount
	{
		$me = $me ?? $this->getMe($tokenData['access_token']) ?? [];

		return MercadoLibreAccount::updateOrCreate(
			['ml_user_id' => $tokenData['user_id'] ?? $me['id']],
			[
				'nickname' => $me['nickname'] ?? null,
				'site_id' => $me['site_id'] ?? null,
				'access_token' => $tokenData['access_token'],
				'refresh_token' => $tokenData['refresh_token'] ?? null,
				'expires_at' => now()->addSeconds(($tokenData['expires_in'] ?? 21600) - 60),
				'linnwork_user_id' => $linnworkUserId,
			]
		);
	}
}
