<?php

namespace Tests\Feature;

use App\Models\ChannelTenant;
use App\Models\MercadoLibreAccount;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ChannelListingTest extends TestCase
{
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
		] as $path) {
			$this->artisan('migrate', ['--database' => 'sqlite', '--path' => $path]);
		}
	}

	public function test_listing_update_creates_ml_item_and_check_feed(): void
	{
		$token = ChannelTenant::generateAuthorizationToken();
		ChannelTenant::create([
			'linnworks_user_id' => 'lw-listing',
			'linnworks_email' => 'a@b.c',
			'authorization_token' => $token,
			'site_id' => 'MLM',
			'active' => true,
		]);
		MercadoLibreAccount::create([
			'ml_user_id' => 3608229310,
			'nickname' => 'TESTUSER',
			'site_id' => 'MLM',
			'access_token' => 'tok',
			'refresh_token' => 'ref',
			'expires_at' => now()->addDay(),
		]);

		Http::fake([
			'https://api.mercadolibre.com/categories/MLM437616/attributes' => Http::response([]),
			'https://api.mercadolibre.com/marketplace/items' => Http::response([
				'id' => 'CBT3360000001',
				'title' => 'Notebook test',
				'available_quantity' => 4,
				'price' => 99.5,
				'permalink' => 'https://www.mercadolibre.com/CBT-3360000001',
				'status' => 'active',
			], 201),
		]);

		$create = $this->postJson('/api/Listing/ListingUpdate', [
			'AuthorizationToken' => $token,
			'Type' => 0,
			'Listings' => [[
				'TemplateId' => 7,
				'SKU' => 'LW-TEST-SKU',
				'Title' => 'Notebook test',
				'Description' => '<p>From Linnworks</p>',
				'Quantity' => 0,
				'Price' => 99.5,
				'Categories' => ['MLM437616'],
				'Images' => [['Url' => 'https://example.test/photo.jpg']],
				'Attributes' => [],
			]],
		])->assertOk()->assertJsonPath('Error', null);

		$feedId = $create->json('ChannelFeedId');
		$this->assertNotEmpty($feedId);

		$this->postJson('/api/Listing/CheckFeed', [
			'AuthorizationToken' => $token,
			'ChannelFeedId' => $feedId,
		])->assertOk()
			->assertJsonPath('IsFeedReady', true)
			->assertJsonPath('ProductFeeds.0.SKU', 'LW-TEST-SKU')
			->assertJsonPath('ProductFeeds.0.ExternalListingId', 'CBT3360000001')
			->assertJsonPath('ProductFeeds.0.Messages', null);

		Http::assertSent(fn ($request) => $request->method() === 'POST'
			&& $request->url() === 'https://api.mercadolibre.com/marketplace/items'
			&& $request['available_quantity'] === 1
			&& $request['title'] === 'Notebook test'
			&& $request['currency_id'] === 'USD'
			&& $request['category_id'] === 'MLM437616'
			&& isset($request['sites_to_sell'][0]['logistic_type']));
	}
}
