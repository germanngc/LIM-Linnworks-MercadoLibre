<?php

namespace App\Http\Controllers;

use App\Models\ChannelListingFeed;
use App\Models\ChannelTenant;
use App\Models\MercadoLibreAccount;
use App\Models\MercadoLibreListing;
use App\Models\MercadoLibreOrder;
use App\Services\MercadoLibreService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ChannelIntegrationController extends Controller
{
	public function addNewUser(Request $request): JsonResponse
	{
		$email = (string) ($request->input('Email') ?? $request->input('email') ?? '');
		$userId = (string) ($request->input('UserId') ?? $request->input('userId') ?? '');

		if ($email === '' && $userId === '') {
			return response()->json(['Error' => 'Missing user identity (Email/UserId).']);
		}

		$tenant = $this->findOrCreateTenant($email, $userId);

		Log::info('Channel AddNewUser', ['tenant_id' => $tenant->id, 'user_id' => $userId]);

		return response()->json([
			'Error' => null,
			'AuthorizationToken' => $tenant->authorization_token,
		]);
	}

	public function userConfig(Request $request): JsonResponse
	{
		$tenant = $this->tenantFromRequest($request, true);

		return response()->json($this->buildUserConfigResponse($tenant));
	}

	public function saveUserConfig(Request $request): JsonResponse
	{
		$tenant = $this->tenantFromRequest($request, false);
		if (!$tenant) {
			return response()->json(['Error' => 'Invalid or inactive AuthorizationToken']);
		}

		$items = collect((array) $request->input('ConfigItems', []))
			->filter(fn ($item) => is_array($item) && isset($item['ConfigItemId']))
			->mapWithKeys(fn (array $item) => [(string) $item['ConfigItemId'] => (string) ($item['SelectedValue'] ?? '')]);

		$site = strtoupper((string) ($items->get('Site') ?: $request->input('Site') ?: $tenant->site_id ?: $this->defaultMlSite()));
		if (!in_array($site, ['CBT', 'MLM', 'MLA', 'MLB', 'MLC', 'MCO', 'MLU', 'MPE'], true)) {
			$site = $this->defaultMlSite();
		}

		$tenant->update(['site_id' => $site, 'active' => true]);

		return response()->json($this->buildUserConfigResponse($tenant->fresh()));
	}

	public function configTest(Request $request, MercadoLibreService $meli): JsonResponse
	{
		$tenant = $request->attributes->get('channel_tenant');
		if (!$tenant) {
			return response()->json(['Error' => 'Invalid or inactive AuthorizationToken', 'Success' => false]);
		}

		$account = MercadoLibreAccount::query()->latest()->first();
		if (!$account || !$meli->ensureToken($account)) {
			// ponytail: Test must not block install; seller OAuth is the next click in the wizard/app
			return response()->json([
				'Error' => null,
				'Success' => true,
				'Message' => 'Channel ready. Click Authorize Mercado Libre, sign in, then Test again to verify the seller.',
			]);
		}

		return response()->json([
			'Error' => null,
			'Success' => true,
			'Message' => 'Mercado Libre token ok ('.$account->nickname.').',
		]);
	}

	public function shippingTags(Request $request): JsonResponse
	{
		return response()->json([
			'Error' => null,
			'ShippingTags' => [
				['Tag' => 'remote', 'FriendlyName' => 'Remote (Global Selling)', 'Site' => ''],
				['Tag' => 'us_fulfillment', 'FriendlyName' => 'US fulfillment', 'Site' => ''],
				['Tag' => 'drop_off', 'FriendlyName' => 'Drop off (local MX)', 'Site' => ''],
				['Tag' => 'xd_drop_off', 'FriendlyName' => 'Xd drop off', 'Site' => ''],
				['Tag' => 'fulfillment', 'FriendlyName' => 'Full', 'Site' => ''],
			],
		]);
	}

	public function paymentTags(Request $request): JsonResponse
	{
		return response()->json([
			'Error' => null,
			'PaymentTags' => [
				['Tag' => 'mercadopago', 'FriendlyName' => 'Mercado Pago', 'Site' => ''],
			],
		]);
	}

	public function configDeleted(Request $request): JsonResponse
	{
		$tenant = $request->attributes->get('channel_tenant');
		if ($tenant) {
			$tenant->update(['active' => false]);
		}

		return response()->json(['Error' => null]);
	}

	public function orders(Request $request, MercadoLibreService $meli): JsonResponse
	{
		$account = $this->mlAccount($meli);
		if (!$account) {
			return response()->json(['Error' => null, 'HasMorePages' => false, 'Orders' => []]);
		}

		$page = max(1, (int) $request->input('PageNumber', 1));
		$limit = 50;
		$query = [
			'offset' => ($page - 1) * $limit,
			'limit' => $limit,
			'order.status' => 'paid',
		];
		$from = $this->mlOrderSearchFrom((string) $request->input('UTCTimeFrom', ''));
		if ($from !== null) {
			$query['order.date_created.from'] = $from;
		}

		$search = $meli->searchOrders($account, $query);
		$results = is_array($search) ? ($search['results'] ?? []) : [];
		$paging = is_array($search['paging'] ?? null) ? $search['paging'] : [];
		$orders = [];
		foreach ($results as $row) {
			if (!is_array($row) || empty($row['id'])) {
				continue;
			}
			$status = strtolower((string) ($row['status'] ?? ''));
			if (str_contains($status, 'cancel')) {
				continue;
			}
			$detail = $meli->getOrder($account, $row['id']) ?: $row;
			$orders[] = $this->mapMlOrderToLinnworks($detail, $meli, $account);
		}

		Log::info('Channel Orders', ['page' => $page, 'count' => count($orders)]);

		$offset = (int) ($paging['offset'] ?? (($page - 1) * $limit));
		$total = (int) ($paging['total'] ?? count($results));

		return response()->json([
			'Error' => null,
			'HasMorePages' => ($offset + $limit) < $total,
			'Orders' => $orders,
		]);
	}

	public function despatch(Request $request, MercadoLibreService $meli): JsonResponse
	{
		$account = $this->mlAccount($meli);
		foreach ((array) $request->input('Orders', []) as $row) {
			if (!is_array($row)) {
				continue;
			}
			$ref = (string) ($row['ReferenceNumber'] ?? '');
			$tracking = (string) ($row['TrackingNumber'] ?? '');
			if ($ref === '' || !$account) {
				continue;
			}
			$record = MercadoLibreOrder::query()->where('ml_order_id', $ref)->first();
			if ($record && $record->skipChannelDespatch()) {
				continue;
			}
			$shipmentId = $record?->shipment_id;
			if (!$shipmentId) {
				$shipment = $this->firstShipment($meli->getOrderShipment($account, $ref));
				$shipmentId = $shipment['id'] ?? null;
			}
			if ($shipmentId && $tracking !== '') {
				$meli->notifyShipment($account, $shipmentId, 'shipped', null, $tracking);
			}
		}

		return response()->json(['Error' => null]);
	}

	public function cancel(Request $request): JsonResponse
	{
		return response()->json(['Error' => null]);
	}

	public function refund(Request $request): JsonResponse
	{
		return response()->json(['Error' => null, 'RefundReference' => '']);
	}

	public function postSaleOptions(Request $request): JsonResponse
	{
		return response()->json([
			'Error' => null,
			'CanCancel' => false,
			'CanRefund' => false,
			'CanReturn' => false,
			'PostSaleReasons' => [],
		]);
	}

	public function products(Request $request): JsonResponse
	{
		$page = max(1, (int) $request->input('PageNumber', 1));
		$size = 100;
		$query = MercadoLibreListing::query()
			->whereNotNull('ml_item_id')
			->where('ml_item_id', '!=', '')
			->whereNotNull('sku')
			->where('sku', '!=', '');
		$total = $query->count();
		$products = $query->orderBy('id')->skip(($page - 1) * $size)->take($size)->get()
			->map(fn (MercadoLibreListing $row) => [
				'SKU' => (string) $row->sku,
				'Title' => (string) ($row->title ?: $row->sku),
				'Quantity' => max(0, (int) $row->available_quantity),
				'Price' => (float) $row->price,
				'Reference' => mb_substr((string) $row->ml_item_id, 0, 64),
			])
			->all();

		return response()->json([
			'Error' => null,
			'HasMorePages' => ($page * $size) < $total,
			'Products' => $products,
		]);
	}

	public function inventoryUpdate(Request $request, MercadoLibreService $meli): JsonResponse
	{
		$account = $this->mlAccount($meli);
		$results = [];
		foreach ((array) $request->input('Products', []) as $product) {
			if (!is_array($product)) {
				continue;
			}
			$sku = trim((string) ($product['SKU'] ?? ''));
			$reference = trim((string) ($product['Reference'] ?? ''));
			$quantity = max(0, (int) ($product['Quantity'] ?? 0));
			if ($sku === '') {
				$results[] = ['SKU' => '', 'Error' => 'Missing SKU'];
				continue;
			}
			if (!$account) {
				$results[] = ['SKU' => $sku, 'Error' => 'Connect Mercado Libre in the SI app first.'];
				continue;
			}
			$listing = $this->findListing($sku, $reference);
			if (!$listing) {
				$results[] = ['SKU' => $sku, 'Error' => 'SKU is not mapped to a Mercado Libre listing'];
				continue;
			}
			$variationId = $listing->ml_variation_id ? (int) $listing->ml_variation_id : null;
			$ok = $meli->updateItemQuantity($account, (string) $listing->ml_item_id, $quantity, $variationId);
			if ($ok) {
				$listing->update(['available_quantity' => $quantity, 'linnworks_quantity' => $quantity, 'lw_updated_at' => now()]);
			}
			$results[] = ['SKU' => $sku, 'Error' => $ok ? null : ($meli->lastError ?: 'Inventory update failed')];
		}

		return response()->json(['Error' => null, 'Products' => $results]);
	}

	public function priceUpdate(Request $request, MercadoLibreService $meli): JsonResponse
	{
		$account = $this->mlAccount($meli);
		$results = [];
		foreach ((array) $request->input('Products', []) as $item) {
			if (!is_array($item)) {
				continue;
			}
			$sku = trim((string) ($item['SKU'] ?? ''));
			$reference = trim((string) ($item['Reference'] ?? ''));
			$price = (float) ($item['Price'] ?? 0);
			if ($sku === '') {
				$results[] = ['SKU' => '', 'Error' => 'Missing SKU'];
				continue;
			}
			$listing = $this->findListing($sku, $reference);
			if (!$account || !$listing) {
				$results[] = ['SKU' => $sku, 'Error' => 'SKU is not mapped to a Mercado Libre listing'];
				continue;
			}
			$qty = max(1, (int) $listing->available_quantity);
			$ok = $meli->updateItemPrice($account, (string) $listing->ml_item_id, $price, $qty);
			if ($ok) {
				$listing->update(['price' => $price, 'lw_updated_at' => now()]);
			}
			$results[] = ['SKU' => $sku, 'Error' => $ok ? null : ($meli->lastError ?: 'Price update failed')];
		}

		return response()->json(['Error' => null, 'Products' => $results]);
	}

	public function priceAutomation(Request $request, MercadoLibreService $meli): JsonResponse
	{
		$account = $this->mlAccount($meli);
		$results = [];
		foreach ((array) $request->input('Products', []) as $item) {
			if (!is_array($item)) {
				continue;
			}
			$sku = trim((string) ($item['SKU'] ?? ''));
			$reference = trim((string) ($item['Reference'] ?? ''));
			$action = strtolower((string) ($item['Action'] ?? $request->input('Action') ?? 'upsert'));
			if ($sku === '') {
				$results[] = ['SKU' => '', 'Error' => 'Missing SKU'];
				continue;
			}
			$listing = $this->findListing($sku, $reference);
			if (!$account || !$listing) {
				$results[] = ['SKU' => $sku, 'Error' => 'SKU is not mapped to a Mercado Libre listing'];
				continue;
			}
			$ok = $action === 'delete'
				? $meli->deletePriceAutomation($account, (string) $listing->ml_item_id)
				: $meli->upsertPriceAutomation(
					$account,
					(string) $listing->ml_item_id,
					(float) ($item['MinPrice'] ?? 0),
					isset($item['MaxPrice']) ? (float) $item['MaxPrice'] : null,
					(string) ($item['RuleId'] ?? 'INT')
				);
			$results[] = ['SKU' => $sku, 'Error' => $ok ? null : ($meli->lastError ?: 'Price automation failed')];
		}

		return response()->json(['Error' => null, 'Products' => $results]);
	}

	public function promotions(Request $request, MercadoLibreService $meli): JsonResponse
	{
		$account = $this->mlAccount($meli);
		$products = (array) $request->input('Products', []);
		$action = strtolower((string) $request->input('Action', $products === [] ? 'list' : 'join'));
		if ($products === [] || $action === 'list') {
			if (!$account) {
				return response()->json(['Error' => 'Connect Mercado Libre in the SI app first.', 'Promotions' => []]);
			}
			$list = $meli->listSellerPromotions($account);

			return response()->json([
				'Error' => $list === null ? ($meli->lastError ?: 'Promotions list failed') : null,
				'Promotions' => $list ?? [],
			]);
		}
		$results = [];
		foreach ($products as $item) {
			if (!is_array($item)) {
				continue;
			}
			$sku = trim((string) ($item['SKU'] ?? ''));
			$rowAction = strtolower((string) ($item['Action'] ?? $action));
			if ($sku === '') {
				$results[] = ['SKU' => '', 'Error' => 'Missing SKU'];
				continue;
			}
			$listing = $this->findListing($sku, trim((string) ($item['Reference'] ?? '')));
			if (!$account || !$listing) {
				$results[] = ['SKU' => $sku, 'Error' => 'SKU is not mapped to a Mercado Libre listing'];
				continue;
			}
			$promotionId = trim((string) ($item['PromotionId'] ?? ''));
			$promotionType = trim((string) ($item['PromotionType'] ?? 'MARKETPLACE_CAMPAIGN'));
			if ($rowAction === 'leave' || $rowAction === 'optout' || $rowAction === 'delete') {
				$ok = $meli->leaveItemPromotion($account, (string) $listing->ml_item_id, $promotionId ?: null, $promotionType ?: null);
			} else {
				if ($promotionId === '') {
					$results[] = ['SKU' => $sku, 'Error' => 'Missing PromotionId'];
					continue;
				}
				$deal = isset($item['DealPrice']) ? (float) $item['DealPrice'] : null;
				$ok = $meli->joinItemPromotion($account, (string) $listing->ml_item_id, $promotionId, $promotionType, $deal);
			}
			$results[] = ['SKU' => $sku, 'Error' => $ok ? null : ($meli->lastError ?: 'Promotion update failed')];
		}

		return response()->json(['Error' => null, 'Products' => $results]);
	}

	public function getConfiguratorSettings(Request $request): JsonResponse
	{
		return response()->json([
			'Error' => null,
			'Settings' => [[
				'GroupName' => 'GENERAL',
				'ConfigItemId' => 'Condition',
				'Subtitle' => 'Product',
				'SubTitleSortOrder' => 1,
				'ItemSortOrder' => 1,
				'Description' => 'Item condition on Mercado Libre.',
				'FriendlyName' => 'Condition',
				'MustBeSpecified' => true,
				'ExpectedType' => 'LIST',
				'ValueOptions' => ['new'],
				'InitialValues' => ['new'],
				'IsMultiOption' => false,
				'ValueFromOptionsList' => true,
				'RegExValidation' => '',
				'RegExError' => '',
				'IsWizardOnly' => false,
			]],
			'MaxDescriptionLength' => 50000,
			'ImageSettings' => [
				'Type' => 1,
				'MaxImages' => 10,
				'MaxVariantImages' => 0,
				'ImageTags' => [],
			],
			'MaxCategoryCount' => 1,
			'MaxCustomAttributeLength' => 255,
			'IsCustomHtmlSupported' => false,
			'IsCustomAttributesAllowed' => true,
			'IsVariationsAllowed' => false,
			'HasMainVariationPrice' => false,
			'IsTitleInVariation' => false,
			'HasVariationAttributeDisplayName' => false,
			'IsPriceInVariation' => false,
			'IsShippingListingSpecific' => false,
			'IsPaymentListingSpecific' => false,
		]);
	}

	public function getCategories(Request $request): JsonResponse
	{
		return response()->json([
			'Error' => null,
			'HasMorePages' => false,
			'Categories' => [
				['CategoryId' => 'MLM145912', 'CategoryName' => 'Libretas'],
				['CategoryId' => 'MLM1648', 'CategoryName' => 'Computación'],
			],
		]);
	}

	public function getAttributesByCategory(Request $request): JsonResponse
	{
		return response()->json([
			'Error' => null,
			'Attributes' => [
				[
					'ID' => 'SELLER_SKU',
					'FriendlyName' => 'SKU',
					'Description' => 'Seller SKU.',
					'MustBeSpecified' => 'Desired',
					'ExpectedType' => 'STRING',
					'ValueOptions' => [],
					'ValueFromOptionsList' => false,
					'MaxAttributeUse' => 1,
					'AttribueReadFrom' => 'Parent',
					'RegExValidation' => '',
					'RegExError' => '',
				],
			],
		]);
	}

	public function getVariationsByCategory(Request $request): JsonResponse
	{
		return response()->json([
			'Error' => null,
			'MaxVariationAttributes' => 0,
			'NeededVariations' => [],
		]);
	}

	public function listingUpdate(Request $request, MercadoLibreService $meli): JsonResponse
	{
		$tenant = $request->attributes->get('channel_tenant');
		$operation = $this->listingOperation($request->input('Type', 0));
		$listings = (array) $request->input('Listings', []);
		$account = MercadoLibreAccount::query()->latest()->first();
		$feeds = [];

		Log::info('Channel ListingUpdate', [
			'tenant_id' => $tenant->id,
			'operation' => $operation,
			'skus' => collect($listings)->pluck('SKU')->filter()->values()->all(),
			'quantities' => collect($listings)->map(fn ($row) => is_array($row) ? ($row['Quantity'] ?? null) : null)->values()->all(),
		]);

		if (!$account || !$meli->ensureToken($account)) {
			foreach ($listings as $listing) {
				if (!is_array($listing)) {
					continue;
				}
				$feeds[] = $this->listingFeedResult(
					trim((string) ($listing['SKU'] ?? '')),
					(int) ($listing['TemplateId'] ?? 0),
					(string) ($listing['ExternalListingId'] ?? ''),
					null,
					'Connect Mercado Libre in the SI app first.'
				);
			}
		} else {
			$defaults = $meli->listingDefaults($account);
			foreach ($listings as $listing) {
				if (!is_array($listing)) {
					continue;
				}
				$feeds[] = $this->pushListingToMercadoLibre($meli, $account, $defaults, $listing, $operation);
			}
		}

		$feed = ChannelListingFeed::create([
			'channel_tenant_id' => $tenant->id,
			'channel_feed_id' => (string) Str::uuid(),
			'operation' => $operation,
			'product_feeds' => $feeds,
		]);

		return response()->json(['Error' => null, 'ChannelFeedId' => $feed->channel_feed_id]);
	}

	public function listingDelete(Request $request, MercadoLibreService $meli): JsonResponse
	{
		$tenant = $request->attributes->get('channel_tenant');
		$account = MercadoLibreAccount::query()->latest()->first();
		$ids = (array) $request->input('ExternalListingIds', []);
		$feeds = [];

		foreach ($ids as $row) {
			if (!is_array($row)) {
				continue;
			}
			$sku = (string) ($row['ChannelSKU'] ?? $row['SKU'] ?? '');
			$externalId = (string) ($row['ExternalListingId'] ?? '');
			$templateId = (int) ($row['TemplateId'] ?? 0);
			$ok = $account && $meli->ensureToken($account) && $externalId !== ''
				&& $meli->updateItemStatus($account, $externalId, 'paused', $sku ?: null);
			$feeds[] = $this->listingFeedResult($sku, $templateId, $externalId, $externalId, $ok ? null : ($meli->lastError ?: 'Pause failed.'));
		}

		$feed = ChannelListingFeed::create([
			'channel_tenant_id' => $tenant->id,
			'channel_feed_id' => (string) Str::uuid(),
			'operation' => 'delete',
			'product_feeds' => $feeds,
		]);

		return response()->json(['Error' => null, 'ChannelFeedId' => $feed->channel_feed_id]);
	}

	public function checkFeed(Request $request): JsonResponse
	{
		$tenant = $request->attributes->get('channel_tenant');
		$feed = ChannelListingFeed::query()
			->where('channel_tenant_id', $tenant->id)
			->where('channel_feed_id', (string) $request->input('ChannelFeedId'))
			->first();

		if (!$feed) {
			return response()->json(['Error' => 'Unknown ChannelFeedId.', 'IsFeedReady' => true, 'ProductFeeds' => []]);
		}

		return response()->json([
			'Error' => null,
			'IsFeedReady' => true,
			'ProductFeeds' => $feed->product_feeds ?? [],
		]);
	}

	public function oauthAuthorize(Request $request): JsonResponse
	{
		return response()->json([
			'access_token' => (string) Str::uuid(),
			'token_type' => 'bearer',
			'expires_in' => 3600,
			'scope' => (string) ($request->input('scope') ?? 'read write'),
		]);
	}

	private function tenantFromRequest(Request $request, bool $bootstrap): ?ChannelTenant
	{
		$tenant = $request->attributes->get('channel_tenant');
		if ($tenant instanceof ChannelTenant) {
			return $tenant;
		}

		$token = (string) ($request->input('AuthorizationToken') ?? '');
		if ($token === '') {
			return $bootstrap ? new ChannelTenant([
				'linnworks_email' => '',
				'site_id' => $this->defaultMlSite(),
			]) : null;
		}

		$tenant = ChannelTenant::query()->where('authorization_token', $token)->where('active', true)->first();
		if ($tenant) {
			return $tenant;
		}

		if (!$bootstrap) {
			return null;
		}

		return ChannelTenant::create([
			'linnworks_user_id' => 'lw-token-'.substr(md5($token), 0, 24),
			'linnworks_email' => (string) ($request->input('Email') ?? ''),
			'authorization_token' => $token,
			'site_id' => $this->defaultMlSite(),
			'active' => true,
		]);
	}

	private function defaultMlSite(): string
	{
		return strtolower((string) config('services.mercadolibre.channel_mode', 'global')) === 'global'
			? 'CBT'
			: 'MLM';
	}

	private function mlAccount(MercadoLibreService $meli): ?MercadoLibreAccount
	{
		$account = MercadoLibreAccount::query()->latest()->first();
		if (!$account || !$meli->ensureToken($account)) {
			return null;
		}

		return $account;
	}

	private function findListing(string $sku, string $reference): ?MercadoLibreListing
	{
		$query = MercadoLibreListing::query()->whereNotNull('ml_item_id')->where('ml_item_id', '!=', '');
		if ($reference !== '') {
			$hit = (clone $query)->where('ml_item_id', $reference)->first();
			if ($hit) {
				return $hit;
			}
		}

		return $query->where('sku', $sku)->orderByDesc('id')->first();
	}

	/** Linnworks sends "2026-08-30 02:06:49Z"; ML wants ISO 8601 with T. */
	private function mlOrderSearchFrom(string $raw): ?string
	{
		$raw = trim($raw);
		if ($raw === '') {
			return null;
		}

		try {
			if (preg_match('/\/Date\((\d+)/', $raw, $m)) {
				$dt = \Carbon\Carbon::createFromTimestampMs((int) $m[1], 'UTC');
			} else {
				$dt = \Carbon\Carbon::parse(str_replace(' ', 'T', $raw))->utc();
			}
		} catch (\Throwable) {
			return null; // ponytail: drop the filter; a bad date used to 400 the whole poll
		}

		return $dt->format('Y-m-d\TH:i:s.000').'-00:00';
	}

	private function firstShipment(mixed $shipment): array
	{
		if (!is_array($shipment) || $shipment === []) {
			return [];
		}
		if (isset($shipment['id']) || isset($shipment['logistic_type'])) {
			return $shipment;
		}
		$first = $shipment[0] ?? null;

		return is_array($first) ? $first : [];
	}

	private function mapMlOrderToLinnworks(array $order, MercadoLibreService $meli, MercadoLibreAccount $account): array
	{
		$buyer = is_array($order['buyer'] ?? null) ? $order['buyer'] : [];
		$ship = is_array($order['shipping'] ?? null) ? $order['shipping'] : [];
		$shipment = [];
		if (!empty($order['id'])) {
			$shipment = $this->firstShipment($meli->getOrderShipment($account, $order['id']));
		}
		if (!$shipment && !empty($ship['id'])) {
			$shipment = $this->firstShipment($meli->getShipment($account, $ship['id']));
		}
		$addr = is_array($ship['receiver_address'] ?? null) ? $ship['receiver_address'] : [];
		if (!$addr) {
			$addr = is_array($shipment['destination']['shipping_address'] ?? null)
				? $shipment['destination']['shipping_address']
				: (is_array($shipment['receiver_address'] ?? null) ? $shipment['receiver_address'] : []);
		}
		$logistic = (string) ($shipment['logistic_type'] ?? $ship['logistic_type'] ?? '');
		if (!empty($order['id'])) {
			MercadoLibreOrder::updateOrCreate(
				['ml_order_id' => $order['id']],
				[
					'mercadolibre_account_id' => $account->id,
					'pack_id' => $order['pack_id'] ?? null,
					'shipment_id' => $shipment['id'] ?? ($ship['id'] ?? null),
					'status' => $order['status'] ?? null,
					'shipping_status' => $shipment['status'] ?? ($ship['status'] ?? null),
					'shipping_substatus' => $shipment['substatus'] ?? null,
					'logistic_type' => $logistic !== '' ? $logistic : null,
					'tracking_number' => $shipment['tracking_number'] ?? null,
					'payload' => ['order' => $order, 'shipment' => $shipment ?: null],
				]
			);
		}
		$name = trim((string) ($buyer['first_name'] ?? '').' '.(string) ($buyer['last_name'] ?? ''));
		if ($name === '') {
			$name = (string) ($buyer['nickname'] ?? 'ML buyer');
		}
		$mappedAddr = [
			'FullName' => $name,
			'Company' => '',
			'Address1' => (string) ($addr['address_line'] ?? $addr['street_name'] ?? ''),
			'Address2' => (string) ($addr['street_number'] ?? ''),
			'Address3' => '',
			'Town' => (string) ($addr['city']['name'] ?? $addr['city'] ?? ''),
			'Region' => (string) ($addr['state']['name'] ?? $addr['state'] ?? ''),
			'PostCode' => (string) ($addr['zip_code'] ?? ''),
			'Country' => (string) ($addr['country']['name'] ?? $addr['country'] ?? 'United States'),
			'CountryCode' => (string) ($addr['country']['id'] ?? 'US'),
			'PhoneNumber' => (string) ($addr['receiver_phone'] ?? ''),
			'EmailAddress' => (string) ($buyer['email'] ?? ''),
		];
		$items = [];
		foreach ($order['order_items'] ?? [] as $i => $line) {
			if (!is_array($line)) {
				continue;
			}
			$item = is_array($line['item'] ?? null) ? $line['item'] : [];
			$sku = (string) ($item['seller_sku'] ?? $meli->extractSku($item) ?? ($item['id'] ?? ''));
			$items[] = [
				'OrderLineNumber' => (string) ($i + 1),
				'SKU' => $sku,
				'ItemTitle' => (string) ($item['title'] ?? $sku),
				'Qty' => (int) ($line['quantity'] ?? 1),
				'PricePerUnit' => (float) ($line['unit_price'] ?? 0),
				'TaxRate' => 0,
				'LinePercentDiscount' => 0,
				'TaxCostInclusive' => false,
				'UseChannelTax' => false,
				'IsService' => false,
				'Options' => [],
				'Taxes' => [],
			];
		}
		$status = strtolower((string) ($order['status'] ?? 'paid'));
		$received = $this->mlOrderSearchFrom((string) ($order['date_created'] ?? ''))
			?? now()->utc()->format('Y-m-d\TH:i:s.000').'-00:00';

		return [
			'Source' => 'MercadoLibreChannel',
			'SubSource' => $this->defaultMlSite() === 'CBT' ? 'Global Selling' : 'MLM Tester',
			'ReferenceNumber' => (string) ($order['id'] ?? ''),
			'ExternalReference' => (string) ($order['pack_id'] ?? $order['id'] ?? ''),
			'PaymentStatus' => str_contains($status, 'cancel') ? 'CANCELLED' : 'PAID',
			'OrderStatusType' => 'Unshipped',
			'Currency' => (string) ($order['currency_id'] ?? ($this->defaultMlSite() === 'CBT' ? 'USD' : 'MXN')),
			'ReceivedDate' => $received,
			'DispatchBy' => $received,
			'PostalServiceCost' => (float) ($ship['cost'] ?? 0),
			'PostalServiceTaxRate' => 0,
			'UseChannelTax' => false,
			'MatchPostalServiceTag' => MercadoLibreOrder::postalServiceTag($logistic),
			'MatchPaymentMethodTag' => 'mercadopago',
			'ChannelBuyerName' => $name,
			'BillingAddress' => $mappedAddr,
			'DeliveryAddress' => $mappedAddr,
			'OrderItems' => $items,
			'ExtendedProperties' => [],
			'Notes' => [],
		];
	}

	private function listingOperation(mixed $type): string
	{
		if (is_string($type) && strcasecmp($type, 'UPDATE') === 0) {
			return 'update';
		}

		return (int) $type === 1 ? 'update' : 'create';
	}

	private function listingFeedResult(string $sku, int $templateId, string $externalListingId, ?string $reference, ?string $error = null): array
	{
		return [
			'SKU' => $sku,
			'TemplateId' => $templateId,
			'ExternalListingId' => $externalListingId,
			'URL' => '',
			'Messages' => $error === null ? null : [['Type' => 1, 'Message' => $error]],
			'ChannelReferences' => $reference === null ? [] : [['SKU' => $sku, 'Reference' => $reference]],
		];
	}

	private function listingQuantity(array $listing, string $operation): int
	{
		foreach (['Quantity', 'AvailableQuantity', 'Qty', 'Stock', 'Available'] as $key) {
			if (!array_key_exists($key, $listing)) {
				continue;
			}
			$raw = $listing[$key];
			if (is_array($raw)) {
				$raw = $raw['Value'] ?? $raw['Quantity'] ?? $raw['Available'] ?? 0;
			}
			$n = (int) $raw;
			if ($n > 0) {
				return $n;
			}
		}

		return $operation === 'update' ? 0 : 1; // ponytail: GLT sends Quantity 0; ML create requires >= 1
	}

	private function listingImageUrls(array $listing): array
	{
		$urls = [];
		foreach ((array) ($listing['Images'] ?? []) as $image) {
			$url = is_string($image) ? $image : (string) ($image['Url'] ?? $image['URL'] ?? $image['FullSource'] ?? '');
			if (str_starts_with($url, 'http')) {
				$urls[] = $url;
			}
		}

		return array_values(array_unique($urls));
	}

	private function listingBarcode(array $listing): ?string
	{
		foreach ((array) ($listing['Attributes'] ?? []) as $attr) {
			if (!is_array($attr)) {
				continue;
			}
			$id = strtoupper((string) ($attr['AttributeID'] ?? $attr['Name'] ?? ''));
			$val = trim((string) ($attr['AttributeValue'] ?? $attr['Value'] ?? ''));
			if ($val !== '' && in_array($id, ['BARCODE', 'GTIN', 'EAN', 'UPC'], true)) {
				return $val;
			}
		}

		$raw = trim((string) ($listing['Barcode'] ?? ''));

		return $raw !== '' ? $raw : null;
	}

	private function listingPredictedAttributes(array $listing): array
	{
		$rows = [];
		foreach ((array) ($listing['Attributes'] ?? []) as $attr) {
			if (!is_array($attr)) {
				continue;
			}
			$id = (string) ($attr['AttributeID'] ?? $attr['Name'] ?? '');
			$val = trim((string) ($attr['AttributeValue'] ?? $attr['Value'] ?? ''));
			if ($id === '' || $val === '') {
				continue;
			}
			$mlId = match (strtoupper($id)) {
				'BRAND' => 'BRAND',
				'BARCODE', 'GTIN', 'EAN', 'UPC' => 'GTIN',
				'SKU', 'SELLER_SKU' => 'SELLER_SKU',
				default => $id,
			};
			$rows[] = ['id' => $mlId, 'value_name' => $val];
		}

		return $rows;
	}

	private function listingCategoryId(array $listing): string
	{
		$cat = $listing['Categories'][0] ?? '';
		if (is_array($cat)) {
			$cat = $cat['CategoryId'] ?? $cat['Id'] ?? '';
		}
		$cat = trim((string) $cat);

		return str_starts_with($cat, 'ML') ? $cat : '';
	}

	private function rememberChannelListing(MercadoLibreAccount $account, array $created, string $sku, string $title, int $qty, float $price, string $categoryId, array $images): void
	{
		$id = (string) ($created['id'] ?? '');
		if ($id === '') {
			return;
		}

		MercadoLibreListing::updateOrCreate(
			[
				'mercadolibre_account_id' => $account->id,
				'ml_item_id' => $id,
				'ml_variation_id' => 0,
			],
			[
				'sku' => $sku,
				'title' => $created['title'] ?? $title,
				'available_quantity' => (int) ($created['available_quantity'] ?? $qty),
				'linnworks_quantity' => $qty,
				'price' => (float) ($created['price'] ?? $price),
				'ml_status' => $created['status'] ?? 'active',
				'lw_pictures_hash' => MercadoLibreListing::picturesHash($images),
				'ml_updated_at' => now(),
				'lw_updated_at' => now(),
				'last_sync_direction' => 'create',
				'source' => 'lw',
				'category_id' => $categoryId,
				'permalink' => $created['permalink'] ?? null,
				'last_error' => null,
			]
		);
	}

	private function pushListingToMercadoLibre(
		MercadoLibreService $meli,
		MercadoLibreAccount $account,
		array $defaults,
		array $listing,
		string $operation
	): array {
		$sku = trim((string) ($listing['SKU'] ?? ''));
		$templateId = (int) ($listing['TemplateId'] ?? 0);
		$externalId = trim((string) ($listing['ExternalListingId'] ?? ''));
		$title = trim((string) ($listing['Title'] ?? $sku));
		$price = (float) ($listing['Price'] ?? 0);
		$qty = $this->listingQuantity($listing, $operation);
		$images = $this->listingImageUrls($listing);
		$description = trim(strip_tags((string) ($listing['Description'] ?? '')));

		if ($sku === '') {
			return $this->listingFeedResult($sku, $templateId, $externalId, null, 'Missing SKU.');
		}

		if ($operation === 'update' && $externalId !== '') {
			$ok = $meli->updateItemTitle($account, $externalId, $title)
				&& $meli->updateItemPrice($account, $externalId, $price, max(1, $qty))
				&& ($images === [] || $meli->updateItemPictures($account, $externalId, $images));
			if ($ok && $description !== '') {
				$meli->updateItemDescription($account, $externalId, $description);
			}

			return $this->listingFeedResult($sku, $templateId, $externalId, $externalId, $ok ? null : ($meli->lastError ?: 'Update failed.'));
		}

		$blockers = MercadoLibreListing::createListingBlockers($title, $price, $qty, $images);
		if ($blockers) {
			return $this->listingFeedResult($sku, $templateId, $externalId, null, implode('; ', $blockers));
		}

		$categoryId = $this->listingCategoryId($listing);
		$predicted = ['attributes' => $this->listingPredictedAttributes($listing)];
		if ($categoryId === '') {
			$hit = $meli->predictCategory($account, $title);
			if (!$hit) {
				return $this->listingFeedResult($sku, $templateId, $externalId, null, $meli->lastError ?: 'Category predictor failed.');
			}
			$categoryId = (string) $hit['category_id'];
			$predicted['attributes'] = array_merge($hit['attributes'] ?? [], $predicted['attributes']);
		}

		$assembled = $meli->assembleAttributes(
			$sku,
			$this->listingBarcode($listing),
			$predicted['attributes'],
			$meli->categoryAttributes($account, $categoryId)
		);
		if (!$assembled['ok']) {
			return $this->listingFeedResult($sku, $templateId, $externalId, null, $assembled['error']);
		}

		$payload = $meli->buildItemPayload($defaults, $title, $categoryId, $price, $qty, $images, $assembled['attributes']);
		$created = $meli->createItem($account, $payload);
		if ((!$created || empty($created['id'])) && stripos((string) $meli->lastError, 'category') !== false) {
			$query = preg_match('/notebook|lined|sheets|cuaderno|libreta/i', $title)
				? 'cuaderno rayado 80 hojas'
				: $title;
			$hit = $meli->predictCategory($account, $query);
			if ($hit && !empty($hit['category_id']) && (string) $hit['category_id'] !== $categoryId) {
				$categoryId = (string) $hit['category_id'];
				$predicted['attributes'] = array_merge($hit['attributes'] ?? [], $this->listingPredictedAttributes($listing));
				$assembled = $meli->assembleAttributes(
					$sku,
					$this->listingBarcode($listing),
					$predicted['attributes'],
					$meli->categoryAttributes($account, $categoryId)
				);
				if ($assembled['ok']) {
					$payload = $meli->buildItemPayload($defaults, $title, $categoryId, $price, $qty, $images, $assembled['attributes']);
					$created = $meli->createItem($account, $payload);
				}
			}
		}
		if (!$created || empty($created['id'])) {
			return $this->listingFeedResult($sku, $templateId, $externalId, null, $meli->lastError ?: 'Create item failed.');
		}

		$itemId = (string) $created['id'];
		if ($description !== '') {
			$meli->updateItemDescription($account, $itemId, $description);
		}
		$this->rememberChannelListing($account, $created, $sku, $title, $qty, $price, $categoryId, $images);

		return $this->listingFeedResult($sku, $templateId, $itemId, $itemId);
	}

	private function findOrCreateTenant(string $email, string $userId): ChannelTenant
	{
		$resolvedUserId = $userId !== '' ? $userId : 'lw-'.md5($email ?: uniqid('tenant-', true));
		$tenant = ChannelTenant::query()->where('linnworks_user_id', $resolvedUserId)->first();
		if (!$tenant && $email !== '') {
			$tenant = ChannelTenant::query()->where('linnworks_email', $email)->first();
		}

		if ($tenant) {
			$tenant->fill([
				'linnworks_user_id' => $resolvedUserId,
				'linnworks_email' => $email !== '' ? $email : $tenant->linnworks_email,
				'active' => true,
			])->save();

			return $tenant;
		}

		return ChannelTenant::create([
			'linnworks_user_id' => $resolvedUserId,
			'linnworks_email' => $email,
			'authorization_token' => ChannelTenant::generateAuthorizationToken(),
			'site_id' => $this->defaultMlSite(),
			'active' => true,
		]);
	}

	private function buildUserConfigResponse(?ChannelTenant $tenant): array
	{
		$site = $tenant && $tenant->site_id ? $tenant->site_id : $this->defaultMlSite();
		$configured = $tenant && $tenant->exists;
		$global = $this->defaultMlSite() === 'CBT';
		$connectQuery = array_filter([
			'channel_token' => $tenant?->authorization_token,
			'linnwork_user_id' => $tenant?->linnworks_user_id,
		]);
		$connectUrl = rtrim((string) config('app.url'), '/').'/auth/mercadolibre'.($connectQuery ? '?'.http_build_query($connectQuery) : '');

		$items = [[
			'ConfigItemId' => 'Site',
			'Name' => 'Mercado Libre site',
			'Description' => $global ? 'Parent merchant site for Global Selling.' : 'Marketplace site ID.',
			'GroupName' => 'Account',
			'SortOrder' => 1,
			'SelectedValue' => $site,
			'RegExValidation' => null,
			'RegExError' => null,
			'MustBeSpecified' => true,
			'ReadOnly' => false,
			'ListValues' => $global
				? [['Display' => 'Global Selling (CBT)', 'Value' => 'CBT']]
				: [['Display' => 'Mexico (MLM)', 'Value' => 'MLM']],
			'ValueType' => 'LIST',
			'HidesHeaderAttribute' => false,
		], [
			'ConfigItemId' => 'AuthorizeMercadoLibre',
			'Name' => 'Authorize Mercado Libre',
			'Description' => 'Open this link, sign in to Mercado Libre, then come back and click Test.',
			'GroupName' => 'Account',
			'SortOrder' => 2,
			'SelectedValue' => $connectUrl,
			'RegExValidation' => null,
			'RegExError' => null,
			'MustBeSpecified' => false,
			'ReadOnly' => true,
			'ListValues' => [],
			'ValueType' => 'STRING',
			'HidesHeaderAttribute' => false,
		]];

		return [
			'Error' => null,
			'StepName' => $configured ? 'UserConfig' : 'AddCredentials',
			'AccountName' => (string) (($tenant->linnworks_email ?? '') ?: 'Mercado Libre'),
			'WizardStepTitle' => $configured ? 'Configuration Complete' : 'Mercado Libre site',
			'WizardStepDescription' => 'Install the channel, then click Authorize Mercado Libre (opens a login). You do not need any extra website. After signing in, click Test.',
			'ConfigItems' => $items,
			'GlobalConfigSettings' => [
				'PriceTags' => [],
			],
		];
	}
}
