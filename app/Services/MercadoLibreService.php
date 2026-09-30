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

	/** Item IDs last touched by updateItemStatus (marketplace + mshops siblings). */
	public array $lastStatusItemIds = [];

	/** @var array<string, string> item id => status Mercado Libre actually has after the PUT */
	public array $lastStatusByItemId = [];

	public function isGlobal(): bool
	{
		return strtolower((string) config('services.mercadolibre.channel_mode', 'global')) === 'global';
	}

	/** @return string[] */
	public function globalSites(): array
	{
		$raw = strtoupper((string) config('services.mercadolibre.gs_sites', 'MLM,MLB,MLC,MCO'));

		return array_values(array_filter(array_map('trim', explode(',', $raw))));
	}

	public function globalLogistic(): string
	{
		$logistic = strtolower((string) config('services.mercadolibre.gs_logistic', 'remote'));

		return in_array($logistic, ['remote', 'fulfillment'], true) ? $logistic : 'remote';
	}

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
		$ids = [];

		$searchPath = $this->isGlobal()
			? '/marketplace/users/' . $account->ml_user_id . '/items/search'
			: '/users/' . $account->ml_user_id . '/items/search';

		foreach (['active', 'paused', 'pending', 'under_review'] as $status) {
			$response = Http::withHeaders($this->authHeaders($account))
				->get($this->api() . $searchPath, [
					'status' => $status,
					'limit' => $limit,
					'offset' => $offset,
				]);

			if ($response->failed()) {
				if ($status === 'active') {
					$code = $response->json('code') ?? 'http_'.$response->status();
					$msg = $response->json('message') ?? $response->body();
					$this->lastError = $response->status().' '.$code.': '.$msg;
					Log::error('ML items/search failed', [
						'status' => $response->status(),
						'body' => $response->body(),
					]);
					return null;
				}
				continue;
			}

			foreach ($response->json('results') ?? [] as $id) {
				$ids[] = $id;
			}
		}

		return array_values(array_unique($ids));
	}

	public function getItem(MercadoLibreAccount $account, string $itemId): ?array
	{
		$path = $this->isGlobal() ? '/marketplace/items/' . $itemId : '/items/' . $itemId;
		$response = Http::withHeaders($this->authHeaders($account))
			->get($this->api() . $path);

		if ($response->failed() && $this->isGlobal()) {
			$response = Http::withHeaders($this->authHeaders($account))
				->get($this->api() . '/items/' . $itemId);
		}

		if ($response->failed()) {
			Log::error('ML get item failed', ['item_id' => $itemId, 'body' => $response->body()]);
			return null;
		}

		return $response->json();
	}

	public function updateItemQuantity(MercadoLibreAccount $account, string $itemId, int $quantity, ?int $variationId = null): bool
	{
		$this->lastError = null;
		if ($this->isGlobal()) {
			return $this->putJson($account, '/global/items/' . $itemId, ['available_quantity' => $quantity], 'ML global qty failed');
		}

		$payload = $variationId
			? ['variations' => [['id' => $variationId, 'available_quantity' => $quantity]]]
			: ['available_quantity' => $quantity];

		$response = Http::withHeaders($this->authHeaders($account))
			->asJson()
			->put($this->api() . '/items/' . $itemId, $payload);

		if ($response->failed()) {
			$this->lastError = $this->errorMessage($response);
			Log::error('ML update item quantity failed', [
				'item_id' => $itemId,
				'variation_id' => $variationId,
				'body' => $response->body(),
			]);
			return false;
		}

		return true;
	}

	public function updateItemTitle(MercadoLibreAccount $account, string $itemId, string $title, ?array $item = null): bool
	{
		$this->lastError = null;
		$title = mb_substr(trim($title), 0, $this->isGlobal() ? 150 : 60);
		if ($title === '') {
			return false;
		}

		if ($this->isGlobal()) {
			return $this->putJson($account, '/global/items/' . $itemId, ['title' => $title], 'ML global title failed');
		}

		$item = is_array($item) ? $item : $this->getItem($account, $itemId);
		$userProductId = is_array($item) ? ($item['user_product_id'] ?? null) : null;

		if (!$userProductId) {
			return $this->putJson($account, '/items/' . $itemId, ['title' => $title], 'ML update item title failed');
		}

		$familyId = $this->familyIdForUserProduct($account, (string) $userProductId);
		if (!$familyId) {
			$this->lastError = $this->lastError ?: 'Cannot resolve ML family to update title';
			return false;
		}

		// User Products: family_name on PUT /items returns 400 cause 374.
		$ok = $this->putJson(
			$account,
			'/user-products-families/' . $familyId,
			['family_name' => $title],
			'ML update family name failed'
		);
		if (!$ok && str_contains(mb_strtolower((string) $this->lastError), 'target hash')) {
			$this->lastError = '409 Title not updated: ML locked the family (under review) or another listing already uses that name.';
		}

		return $ok;
	}

	public function updateItemStatus(MercadoLibreAccount $account, string $itemId, string $status, ?string $sku = null): bool
	{
		$this->lastError = null;
		$this->lastStatusItemIds = [];
		$this->lastStatusByItemId = [];
		$status = strtolower(trim($status));
		if (!in_array($status, ['paused', 'active'], true)) {
			$this->lastError = 'Invalid listing status';
			return false;
		}

		if ($this->isGlobal()) {
			$ok = $this->putJson($account, '/global/items/' . $itemId, ['status' => $status], 'ML global status failed');
			if ($ok) {
				$this->lastStatusItemIds = [$itemId];
				$this->lastStatusByItemId[$itemId] = $status;
			}

			return $ok;
		}

		$ids = [$itemId];
		$item = $this->getItem($account, $itemId);
		$sku = $sku ?: (is_array($item) ? $this->extractSku($item) : null);
		$userProductId = is_array($item) ? ($item['user_product_id'] ?? null) : null;
		// ponytail: UP search can return every listing; only pause channels that share this SKU. Cap 3 = marketplace + mshops + one stray.
		if (is_string($sku) && $sku !== '' && $userProductId) {
			$matched = $this->itemIdsForSku($account, $sku, (string) $userProductId);
			$ids = self::statusItemIds($itemId, $matched);
			if (count($ids) > 3) {
				$ids = [$itemId];
			}
		}

		$primaryOk = false;
		foreach ($ids as $id) {
			if (!$this->putJson($account, '/items/' . $id, ['status' => $status], 'ML update item status failed')) {
				continue;
			}
			$this->lastStatusItemIds[] = $id;
			$fresh = $this->getItem($account, $id);
			$actual = is_array($fresh) ? strtolower((string) ($fresh['status'] ?? $status)) : $status;
			$this->lastStatusByItemId[$id] = $actual;
			if ($id !== $itemId) {
				continue;
			}
			if ($actual !== $status) {
				$this->lastError = $actual === 'under_review'
					? 'ML did not activate: listing is under review.'
					: 'ML kept status "'.$actual.'" (requested '.$status.').';
				continue;
			}
			$primaryOk = true;
		}

		return $primaryOk;
	}

	/** Mexico User Products: one seller-panel listing = several item IDs (marketplace + mshops). */
	public static function statusItemIds(string $itemId, array $userProductItemIds): array
	{
		return array_values(array_unique(array_merge([$itemId], $userProductItemIds)));
	}

	public static function idsMatchingSku(string $sku, array $items): array
	{
		$ids = [];
		foreach ($items as $item) {
			if (!is_array($item) || (string) ($item['sku'] ?? '') !== $sku) {
				continue;
			}
			$id = $item['id'] ?? null;
			if (is_string($id) && $id !== '') {
				$ids[] = $id;
			}
		}

		return array_values(array_unique($ids));
	}

	/**
	 * @return string[]
	 */
	private function itemIdsForSku(MercadoLibreAccount $account, string $sku, string $userProductId): array
	{
		$response = Http::withHeaders($this->authHeaders($account))
			->get($this->api() . '/users/' . $account->ml_user_id . '/items/search', [
				'user_product_id' => $userProductId,
				'limit' => 50,
			]);

		if ($response->failed()) {
			return [];
		}

		$candidates = [];
		foreach ($response->json('results') ?? [] as $id) {
			if (is_string($id) && $id !== '') {
				$candidates[] = $id;
			}
		}
		if (!$candidates) {
			return [];
		}

		$rows = [];
		foreach ($this->itemsByIds($account, $candidates) as $item) {
			$extracted = $this->extractSku($item);
			$rows[] = [
				'id' => $item['id'] ?? null,
				'sku' => $extracted,
			];
		}

		return self::idsMatchingSku($sku, $rows);
	}

	/**
	 * @param  string[]  $ids
	 * @return array<int, array>
	 */
	private function itemsByIds(MercadoLibreAccount $account, array $ids): array
	{
		$out = [];
		foreach (array_chunk(array_values(array_unique($ids)), 20) as $chunk) {
			$response = Http::withHeaders($this->authHeaders($account))
				->get($this->api() . '/items', ['ids' => implode(',', $chunk)]);
			if ($response->failed() || !is_array($response->json())) {
				continue;
			}
			foreach ($response->json() as $row) {
				$body = null;
				if (is_array($row) && isset($row['body']) && is_array($row['body'])) {
					$body = $row['body'];
				} elseif (is_array($row) && isset($row['id'])) {
					$body = $row;
				}
				if ($body && !empty($body['id'])) {
					$out[] = $body;
				}
			}
		}

		return $out;
	}

	public function updateItemPrice(MercadoLibreAccount $account, string $itemId, float $price, int $quantity): bool
	{
		$this->lastError = null;
		if ($price <= 0) {
			return false;
		}

		if ($this->isGlobal()) {
			return $this->putJson($account, '/global/items/' . $itemId, [
				'net_proceeds' => round($price, 2),
			], 'ML global price failed');
		}

		$response = Http::withHeaders($this->authHeaders($account))
			->asJson()
			->put($this->api() . '/items/' . $itemId, [
				'price' => round($price, 2),
				'available_quantity' => $quantity,
			]);

		if ($response->failed()) {
			$this->lastError = $this->errorMessage($response);
			Log::error('ML update item price failed', ['item_id' => $itemId, 'body' => $response->body()]);
			return false;
		}

		if ($this->priceWasIgnored($response->json())) {
			$this->lastError = 'Price not updated (ML price automation)';
			return false;
		}

		return true;
	}

	public function upsertPriceAutomation(
		MercadoLibreAccount $account,
		string $itemId,
		float $minPrice,
		?float $maxPrice = null,
		string $ruleId = 'INT'
	): bool {
		$this->lastError = null;
		$ruleId = strtoupper($ruleId);
		if (!in_array($ruleId, ['INT', 'INT_EXT'], true)) {
			$ruleId = 'INT';
		}
		if ($minPrice <= 0) {
			$this->lastError = 'MinPrice is required';
			return false;
		}
		$payload = [
			'rule_id' => $ruleId,
			'min_price' => round($minPrice, 2),
		];
		if ($maxPrice !== null && $maxPrice > 0) {
			$payload['max_price'] = round($maxPrice, 2);
		}

		$path = $this->priceAutomationPath($itemId);
		$existing = Http::withHeaders($this->authHeaders($account))->get($this->api() . $path);
		if ($existing->successful() && $existing->json('status')) {
			return $this->putJson($account, $path, $payload, 'ML price automation update failed');
		}

		$response = Http::withHeaders($this->authHeaders($account))
			->asJson()
			->post($this->api() . $path, $payload);
		if ($response->failed()) {
			$this->lastError = $this->errorMessage($response);
			Log::error('ML price automation create failed', ['path' => $path, 'body' => $response->body()]);
			return false;
		}

		return true;
	}

	public function deletePriceAutomation(MercadoLibreAccount $account, string $itemId): bool
	{
		$this->lastError = null;
		$path = $this->priceAutomationPath($itemId);
		$response = Http::withHeaders($this->authHeaders($account))->delete($this->api() . $path);
		if ($response->failed() && $response->status() !== 404) {
			$this->lastError = $this->errorMessage($response);
			Log::error('ML price automation delete failed', ['path' => $path, 'body' => $response->body()]);
			return false;
		}

		return true;
	}

	/** @return array<int, mixed>|null */
	public function listSellerPromotions(MercadoLibreAccount $account): ?array
	{
		$this->lastError = null;
		$path = $this->isGlobal()
			? '/marketplace/seller-promotions/users/' . $account->ml_user_id
			: '/seller-promotions/users/' . $account->ml_user_id;
		$response = Http::withHeaders($this->promoHeaders($account))
			->get($this->api() . $path, [
				'app_version' => 'v2',
				'user_id' => (string) $account->ml_user_id,
			]);
		if ($response->failed()) {
			$this->lastError = $this->errorMessage($response);
			Log::error('ML promotions list failed', ['body' => $response->body()]);
			return null;
		}

		$json = $response->json();

		return is_array($json['results'] ?? null) ? $json['results'] : (is_array($json) ? $json : []);
	}

	public function joinItemPromotion(
		MercadoLibreAccount $account,
		string $itemId,
		string $promotionId,
		string $promotionType,
		?float $dealPrice = null
	): bool {
		$this->lastError = null;
		$payload = [
			'promotion_id' => $promotionId,
			'promotion_type' => strtoupper($promotionType),
		];
		if ($dealPrice !== null && $dealPrice > 0) {
			$payload['deal_price'] = round($dealPrice, 2);
		}
		[$path, $query] = $this->itemPromotionRequest($account, $itemId);
		$response = Http::withHeaders($this->promoHeaders($account))
			->asJson()
			->post($this->api() . $path . ($query ? '?' . http_build_query($query) : ''), $payload);
		if ($response->failed()) {
			$this->lastError = $this->errorMessage($response);
			Log::error('ML promotion join failed', ['item_id' => $itemId, 'body' => $response->body()]);
			return false;
		}

		return true;
	}

	public function leaveItemPromotion(
		MercadoLibreAccount $account,
		string $itemId,
		?string $promotionId = null,
		?string $promotionType = null
	): bool {
		$this->lastError = null;
		[$path, $query] = $this->itemPromotionRequest($account, $itemId);
		if ($promotionId) {
			$query['promotion_id'] = $promotionId;
		}
		if ($promotionType) {
			$query['promotion_type'] = strtoupper($promotionType);
		}
		$response = Http::withHeaders($this->promoHeaders($account))
			->delete($this->api() . $path . ($query ? '?' . http_build_query($query) : ''));
		if ($response->failed() && $response->status() !== 404) {
			$this->lastError = $this->errorMessage($response);
			Log::error('ML promotion leave failed', ['item_id' => $itemId, 'body' => $response->body()]);
			return false;
		}

		return true;
	}

	private function priceAutomationPath(string $itemId): string
	{
		return $this->isGlobal()
			? '/marketplace/items/' . $itemId . '/prices/automate'
			: '/pricing-automation/items/' . $itemId . '/automation';
	}

	/** @return array{0:string,1:array<string,string>} */
	private function itemPromotionRequest(MercadoLibreAccount $account, string $itemId): array
	{
		if ($this->isGlobal()) {
			return [
				'/marketplace/seller-promotions/items/' . $itemId,
				['user_id' => (string) $account->ml_user_id, 'app_version' => 'v2'],
			];
		}

		return ['/seller-promotions/items/' . $itemId, ['app_version' => 'v2']];
	}

	private function promoHeaders(MercadoLibreAccount $account): array
	{
		return $this->authHeaders($account) + [
			'version' => 'v2',
			'X-Caller-Id' => (string) $account->ml_user_id,
			'X-Client-Id' => (string) config('services.mercadolibre.client_id'),
		];
	}

	public function updateItemPictures(MercadoLibreAccount $account, string $itemId, array $pictureUrls): bool
	{
		$pictures = [];
		foreach (array_slice($pictureUrls, 0, 6) as $url) {
			$pictures[] = ['source' => $url];
		}
		if (!$pictures) {
			return false;
		}

		$path = $this->isGlobal() ? '/global/items/' . $itemId : '/items/' . $itemId;

		return $this->putJson($account, $path, ['pictures' => $pictures], 'ML update item pictures failed');
	}

	public function getItemDescription(MercadoLibreAccount $account, string $itemId): ?string
	{
		$response = Http::withHeaders($this->authHeaders($account))
			->get($this->api() . '/items/' . $itemId . '/description');

		if ($response->status() === 404) {
			return '';
		}
		if ($response->failed()) {
			return null;
		}

		return trim((string) ($response->json('plain_text') ?? ''));
	}

	public function updateItemDescription(MercadoLibreAccount $account, string $itemId, string $plainText): bool
	{
		$this->lastError = null;
		$plainText = trim($plainText);
		if ($plainText === '') {
			return false;
		}

		$response = Http::withHeaders($this->authHeaders($account))
			->asJson()
			->put($this->api() . '/items/' . $itemId . '/description', ['plain_text' => $plainText]);

		if ($response->failed()) {
			$this->lastError = $this->errorMessage($response);
			Log::error('ML update item description failed', ['item_id' => $itemId, 'body' => $response->body()]);
			return false;
		}

		return true;
	}

	public static function itemHasPriceAutomation(array $item): bool
	{
		$tags = $item['tags'] ?? [];
		if (!is_array($tags)) {
			return false;
		}

		return in_array('dynamic_standard_price', $tags, true)
			|| in_array('price_automation', $tags, true);
	}

	private function priceWasIgnored(?array $json): bool
	{
		if (!$json) {
			return false;
		}

		$blobs = [];
		foreach (['warnings', 'warning'] as $key) {
			if (!empty($json[$key])) {
				$blobs[] = json_encode($json[$key]);
			}
		}

		$text = mb_strtolower(implode(' ', $blobs));
		return $text !== '' && str_contains($text, 'price');
	}

	private function familyIdForUserProduct(MercadoLibreAccount $account, string $userProductId): ?string
	{
		$response = Http::withHeaders($this->authHeaders($account))
			->get($this->api() . '/user-products/' . $userProductId);

		if ($response->failed()) {
			$this->lastError = $this->errorMessage($response);
			Log::error('ML get user product failed', [
				'user_product_id' => $userProductId,
				'body' => $response->body(),
			]);
			return null;
		}

		$familyId = $response->json('family_id');
		return $familyId ? (string) $familyId : null;
	}

	private function putJson(MercadoLibreAccount $account, string $path, array $payload, string $logLabel): bool
	{
		$response = Http::withHeaders($this->authHeaders($account))
			->asJson()
			->put($this->api() . $path, $payload);

		if ($response->failed()) {
			$this->lastError = $this->errorMessage($response);
			Log::error($logLabel, ['path' => $path, 'body' => $response->body()]);
			return false;
		}

		return true;
	}

	/**
	 * @return array{site_id:string,currency_id:string,listing_type_id:string}|null
	 */
	public function listingDefaults(MercadoLibreAccount $account): ?array
	{
		if ($this->isGlobal()) {
			return [
				'site_id' => 'CBT',
				'currency_id' => 'USD',
				'listing_type_id' => 'gold_special',
			];
		}

		$site = $account->site_id ?: 'MLM';
		$siteRes = Http::acceptJson()->get($this->api() . '/sites/' . $site);
		$currency = $siteRes->json('default_currency_id');
		if (!$currency) {
			$currency = match ($site) {
				'MLA' => 'ARS',
				'MLB' => 'BRL',
				'MLC' => 'CLP',
				'MCO' => 'COP',
				'MPE' => 'PEN',
				'MLU' => 'UYU',
				default => 'MXN',
			};
		}

		$typesRes = Http::acceptJson()->get($this->api() . '/sites/' . $site . '/listing_types');
		$types = $typesRes->json();
		$listingType = 'gold_special';
		if (is_array($types)) {
			$ids = array_column($types, 'id');
			if (!in_array($listingType, $ids, true) && $ids) {
				$listingType = (string) $ids[0];
			}
		}

		return [
			'site_id' => $site,
			'currency_id' => (string) $currency,
			'listing_type_id' => $listingType,
		];
	}

	public function predictCategory(MercadoLibreAccount $account, string $title): ?array
	{
		$site = $this->isGlobal() ? ($this->globalSites()[0] ?? 'MLM') : ($account->site_id ?: 'MLM');
		$response = Http::withHeaders($this->authHeaders($account))
			->get($this->api() . '/sites/' . $site . '/domain_discovery/search', [
				'q' => $title,
				'limit' => 1,
			]);

		if ($response->failed()) {
			$this->lastError = $this->errorMessage($response);
			Log::error('ML category predictor failed', ['body' => $response->body()]);
			return null;
		}

		$data = $response->json();
		$first = is_array($data) ? ($data[0] ?? null) : null;
		if (!is_array($first) || empty($first['category_id'])) {
			$this->lastError = 'No category prediction for title';
			return null;
		}

		return $first;
	}

	public function categoryAttributes(MercadoLibreAccount $account, string $categoryId): array
	{
		$response = Http::withHeaders($this->authHeaders($account))
			->get($this->api() . '/categories/' . $categoryId . '/attributes');

		if ($response->failed() || !is_array($response->json())) {
			return [];
		}

		return $response->json();
	}

	/**
	 * Merge predictor attrs + SKU/barcode into required category attributes.
	 * @return array{ok:true,attributes:array}|array{ok:false,error:string}
	 */
	public function assembleAttributes(string $sku, ?string $barcode, array $predicted, array $categoryAttributes): array
	{
		$byId = [];
		foreach ($predicted as $attr) {
			$id = $attr['id'] ?? null;
			if (!is_string($id) || $id === '') {
				continue;
			}
			$row = ['id' => $id];
			if (!empty($attr['value_id'])) {
				$row['value_id'] = (string) $attr['value_id'];
			}
			if (!empty($attr['value_name'])) {
				$row['value_name'] = (string) $attr['value_name'];
			}
			if (count($row) > 1) {
				$byId[$id] = $row;
			}
		}

		$byId['SELLER_SKU'] = ['id' => 'SELLER_SKU', 'value_name' => $sku];

		$missing = [];
		foreach ($categoryAttributes as $def) {
			$id = $def['id'] ?? null;
			if (!is_string($id) || isset($byId[$id])) {
				continue;
			}
			if (!$this->attributeIsRequired($def)) {
				continue;
			}

			$filled = $this->fillRequiredAttribute($def, $sku, $barcode);
			if ($filled) {
				$byId[$id] = $filled;
			} else {
				$missing[] = $def['name'] ?? $id;
			}
		}

		if ($missing) {
			return ['ok' => false, 'error' => 'Missing required attributes: '.implode(', ', $missing)];
		}

		return ['ok' => true, 'attributes' => array_values($byId)];
	}

	public function createItem(MercadoLibreAccount $account, array $payload): ?array
	{
		$this->lastError = null;
		$path = $this->isGlobal() ? '/marketplace/items' : '/items';
		$response = Http::withHeaders($this->authHeaders($account))
			->asJson()
			->post($this->api() . $path, $payload);

		if ($response->failed()) {
			$this->lastError = $this->errorMessage($response);
			Log::error('ML create item failed', ['body' => $response->body(), 'sku' => $payload['attributes'] ?? null]);
			return null;
		}

		return $response->json();
	}

	public function buildItemPayload(
		array $defaults,
		string $title,
		string $categoryId,
		float $price,
		int $qty,
		array $pictureUrls,
		array $attributes
	): array {
		$pictures = [];
		foreach (array_slice($pictureUrls, 0, 6) as $url) {
			$pictures[] = ['source' => $url];
		}

		$title = mb_substr(trim($title), 0, $this->isGlobal() ? 150 : 60);

		if ($this->isGlobal()) {
			$sites = [];
			foreach ($this->globalSites() as $site) {
				$sites[] = [
					'site_id' => $site,
					'logistic_type' => $this->globalLogistic(),
					'pictures' => $pictures,
				];
			}

			return [
				'title' => $title,
				'category_id' => $categoryId,
				'price' => round($price, 2),
				'currency_id' => 'USD',
				'available_quantity' => $qty,
				'buying_mode' => 'buy_it_now',
				'condition' => 'new',
				'listing_type_id' => $defaults['listing_type_id'] ?? 'gold_special',
				'sites_to_sell' => $sites,
				'attributes' => $attributes,
			];
		}

		return [
			'family_name' => $title,
			'category_id' => $categoryId,
			'price' => round($price, 2),
			'currency_id' => $defaults['currency_id'],
			'available_quantity' => $qty,
			'buying_mode' => 'buy_it_now',
			'condition' => 'new',
			'listing_type_id' => $defaults['listing_type_id'],
			'pictures' => $pictures,
			'attributes' => $attributes,
			'sale_terms' => [
				['id' => 'WARRANTY_TYPE', 'value_name' => 'Garantía del vendedor'],
				['id' => 'WARRANTY_TIME', 'value_name' => '90 días'],
			],
		];
	}

	private function attributeIsRequired(array $def): bool
	{
		$tags = $def['tags'] ?? [];
		if (isset($tags['required']) && $tags['required']) {
			return true;
		}
		if (isset($tags['catalog_required']) && $tags['catalog_required']) {
			return true;
		}
		if (is_array($tags) && in_array('required', $tags, true)) {
			return true;
		}

		return false;
	}

	private function fillRequiredAttribute(array $def, string $sku, ?string $barcode): ?array
	{
		$id = (string) $def['id'];

		if (in_array($id, ['GTIN', 'EAN', 'UPC', 'ISBN', 'JAN'], true) && $barcode) {
			return ['id' => $id, 'value_name' => $barcode];
		}

		if ($id === 'BRAND') {
			return $this->pickAttributeValue($def, ['Genérico', 'Generico', 'Generic', 'Otro', 'Sin marca'])
				?? ['id' => 'BRAND', 'value_name' => 'Genérico'];
		}

		if (in_array($id, ['MODEL', 'LINE'], true)) {
			return ['id' => $id, 'value_name' => $sku];
		}

		return $this->pickAttributeValue($def, ['Genérico', 'Generico', 'Generic', 'Otro', 'No aplica', 'Does not apply']);
	}

	private function pickAttributeValue(array $def, array $preferredNames): ?array
	{
		$id = (string) $def['id'];
		foreach ($def['values'] ?? [] as $value) {
			$name = (string) ($value['name'] ?? '');
			foreach ($preferredNames as $preferred) {
				if (strcasecmp($name, $preferred) === 0) {
					$row = ['id' => $id, 'value_name' => $name];
					if (!empty($value['id'])) {
						$row['value_id'] = (string) $value['id'];
					}
					return $row;
				}
			}
		}

		return null;
	}

	private function errorMessage($response): string
	{
		$parts = [];
		foreach (['message', 'error'] as $key) {
			$value = $response->json($key);
			if (is_string($value) && $value !== '' && !in_array($value, $parts, true)) {
				$parts[] = $value;
			}
		}
		$msg = implode(' — ', $parts);
		$cause = $response->json('cause');
		if (is_array($cause) && $cause) {
			$bits = [];
			foreach ($cause as $item) {
				if (is_array($item)) {
					$bits[] = $item['message'] ?? $item['code'] ?? json_encode($item);
				} else {
					$bits[] = (string) $item;
				}
			}
			$msg = trim($msg . ' ' . implode('; ', $bits));
		}

		if (!$msg) {
			$msg = $response->body();
		}

		return $response->status() . ' ' . mb_substr((string) $msg, 0, 400);
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
	 * @return array<int, array{ml_item_id:string,ml_variation_id:int,sku:?string,title:?string,available_quantity:int,price:?float,ml_updated_at:?string,ml_status:?string}>
	 */
	public function itemToListingRows(array $item): array
	{
		$itemId = (string) ($item['id'] ?? '');
		$title = $item['title'] ?? null;
		$status = isset($item['status']) ? (string) $item['status'] : null;
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
					'ml_status' => $status,
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
			'ml_status' => $status,
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
