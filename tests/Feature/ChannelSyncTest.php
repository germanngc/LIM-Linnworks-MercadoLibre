<?php

namespace Tests\Feature;

use App\Models\ChannelTenant;
use App\Models\MercadoLibreAccount;
use App\Models\MercadoLibreListing;
use App\Models\MercadoLibreOrder;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ChannelSyncTest extends TestCase
{
	private string $token;

	protected function setUp(): void
	{
		parent::setUp();
		config(['database.default' => 'sqlite']);
		config(['database.connections.sqlite.database' => ':memory:']);
		\Illuminate\Support\Facades\DB::purge('sqlite');
		\Illuminate\Support\Facades\DB::reconnect('sqlite');
		foreach ([
			'database/migrations/2026_08_29_120000_create_channel_tenants_table.php',
			'database/migrations/2026_08_11_180000_create_mercadolibre_accounts_table.php',
			'database/migrations/2026_08_12_180000_create_mercadolibre_listings_table.php',
			'database/migrations/2026_08_19_213000_add_listing_create_fields_to_mercadolibre_listings.php',
			'database/migrations/2026_08_20_142600_add_listing_control_fields_to_mercadolibre_listings.php',
			'database/migrations/2026_08_11_180001_create_mercadolibre_orders_table.php',
		] as $path) {
			$this->artisan('migrate', ['--database' => 'sqlite', '--path' => $path]);
		}

		$this->token = ChannelTenant::generateAuthorizationToken();
		ChannelTenant::create([
			'linnworks_user_id' => 'lw-sync',
			'linnworks_email' => 'a@b.c',
			'authorization_token' => $this->token,
			'site_id' => 'MLM',
			'active' => true,
		]);
		$account = MercadoLibreAccount::create([
			'ml_user_id' => 3608229310,
			'nickname' => 'TESTUSER',
			'site_id' => 'MLM',
			'access_token' => 'tok',
			'refresh_token' => 'ref',
			'expires_at' => now()->addDay(),
		]);
		MercadoLibreListing::create([
			'mercadolibre_account_id' => $account->id,
			'ml_item_id' => 'MLM6130153220',
			'ml_variation_id' => 0,
			'sku' => 'MLM-NTB-001',
			'title' => 'Notebook',
			'available_quantity' => 1,
			'price' => 89,
		]);
	}

	public function test_products_returns_mapped_listings(): void
	{
		$this->postJson('/api/Product/Products', ['AuthorizationToken' => $this->token])
			->assertOk()
			->assertJsonPath('Error', null)
			->assertJsonPath('Products.0.SKU', 'MLM-NTB-001')
			->assertJsonPath('Products.0.Reference', 'MLM6130153220');
	}

	public function test_inventory_update_pushes_quantity_to_ml(): void
	{
		Http::fake([
			'https://api.mercadolibre.com/items/MLM6130153220' => Http::response(['id' => 'MLM6130153220', 'available_quantity' => 5]),
			'*' => Http::response(['error' => 'unexpected'], 500),
		]);

		$this->postJson('/api/Product/InventoryUpdate', [
			'AuthorizationToken' => $this->token,
			'Products' => [[
				'SKU' => 'MLM-NTB-001',
				'Reference' => 'MLM6130153220',
				'Quantity' => 5,
			]],
		])->assertOk()
			->assertJsonPath('Error', null)
			->assertJsonPath('Products.0.SKU', 'MLM-NTB-001')
			->assertJsonPath('Products.0.Error', null);

		Http::assertSent(fn ($request) => $request->method() === 'PUT'
			&& $request->url() === 'https://api.mercadolibre.com/items/MLM6130153220'
			&& $request['available_quantity'] === 5);

		$this->assertSame(5, MercadoLibreListing::query()->first()->available_quantity);
	}

	public function test_orders_maps_paid_ml_order(): void
	{
		Http::fake([
			'https://api.mercadolibre.com/orders/2000001/shipments' => Http::response([
				'id' => 9001,
				'logistic_type' => 'drop_off',
				'status' => 'ready_to_ship',
			]),
			'https://api.mercadolibre.com/orders/2000001' => Http::response([
				'id' => 2000001,
				'status' => 'paid',
				'currency_id' => 'MXN',
				'date_created' => '2026-08-29T12:00:00.000Z',
				'buyer' => ['first_name' => 'Ana', 'last_name' => 'Perez', 'nickname' => 'ANATEST'],
				'shipping' => ['id' => 9001, 'cost' => 0, 'logistic_type' => 'drop_off'],
				'order_items' => [[
					'quantity' => 1,
					'unit_price' => 89,
					'item' => ['id' => 'MLM6130153220', 'title' => 'Notebook', 'seller_sku' => 'MLM-NTB-001'],
				]],
			]),
			'https://api.mercadolibre.com/orders/search*' => Http::response([
				'results' => [['id' => 2000001, 'status' => 'paid']],
				'paging' => ['total' => 1, 'offset' => 0, 'limit' => 50],
			]),
			'*' => Http::response(['error' => 'unexpected'], 500),
		]);

		$this->postJson('/api/Order/Orders', [
			'AuthorizationToken' => $this->token,
			'PageNumber' => 1,
			'UTCTimeFrom' => '2026-08-30 02:06:49Z',
		])
			->assertOk()
			->assertJsonPath('Error', null)
			->assertJsonPath('HasMorePages', false)
			->assertJsonPath('Orders.0.ReferenceNumber', '2000001')
			->assertJsonPath('Orders.0.OrderItems.0.SKU', 'MLM-NTB-001')
			->assertJsonPath('Orders.0.MatchPostalServiceTag', 'drop_off');

		Http::assertSent(fn ($request) => str_contains($request->url(), '/orders/search')
			&& ($request['order.date_created.from'] ?? null) === '2026-08-30T02:06:49.000-00:00');

		$this->assertSame('9001', (string) MercadoLibreOrder::query()->where('ml_order_id', 2000001)->value('shipment_id'));
	}

	public function test_despatch_notifies_me1_and_skips_full(): void
	{
		$account = MercadoLibreAccount::query()->first();
		MercadoLibreOrder::create([
			'mercadolibre_account_id' => $account->id,
			'ml_order_id' => 2000001,
			'shipment_id' => 9001,
			'logistic_type' => 'drop_off',
		]);
		MercadoLibreOrder::create([
			'mercadolibre_account_id' => $account->id,
			'ml_order_id' => 2000002,
			'shipment_id' => 9002,
			'logistic_type' => 'fulfillment',
		]);

		Http::fake([
			'https://api.mercadolibre.com/shipments/9001/seller_notifications' => Http::response(['status' => 'ok']),
			'*' => Http::response(['error' => 'unexpected'], 500),
		]);

		$this->postJson('/api/Order/Despatch', [
			'AuthorizationToken' => $this->token,
			'Orders' => [
				['ReferenceNumber' => '2000001', 'TrackingNumber' => 'TRACK1'],
				['ReferenceNumber' => '2000002', 'TrackingNumber' => 'TRACK2'],
			],
		])->assertOk()->assertJsonPath('Error', null);

		Http::assertSent(fn ($request) => str_contains($request->url(), '/shipments/9001/seller_notifications')
			&& $request['tracking_number'] === 'TRACK1');
		Http::assertNotSent(fn ($request) => str_contains($request->url(), '/shipments/9002/'));
	}
}
