<?php

namespace Tests\Unit;

use App\Models\MercadoLibreListing;
use App\Models\MercadoLibreOrder;
use App\Services\MercadoLibreService;
use PHPUnit\Framework\TestCase;

class MercadoLibreOrderTest extends TestCase
{
	public function test_full_logistic_types()
	{
		$this->assertTrue(MercadoLibreOrder::isFullLogisticType('fulfillment'));
		$this->assertTrue(MercadoLibreOrder::isFullLogisticType('fulfillment_lite'));
		$this->assertFalse(MercadoLibreOrder::isFullLogisticType('drop_off'));
		$this->assertFalse(MercadoLibreOrder::isFullLogisticType('custom'));
		$this->assertFalse(MercadoLibreOrder::isFullLogisticType(null));
	}

	public function test_open_vs_terminal_orders()
	{
		$this->assertFalse(MercadoLibreOrder::isTerminalStatus('paid', 'ready_to_ship'));
		$this->assertFalse(MercadoLibreOrder::isTerminalStatus('paid', 'shipped'));
		$this->assertTrue(MercadoLibreOrder::isTerminalStatus('cancelled', 'ready_to_ship'));
		$this->assertTrue(MercadoLibreOrder::isTerminalStatus('paid', 'delivered'));
		$this->assertTrue(MercadoLibreOrder::isTerminalStatus('paid', 'not_delivered'));
	}

	public function test_humanize_underscores()
	{
		$this->assertSame('Ready to ship', MercadoLibreOrder::humanize('ready_to_ship'));
		$this->assertSame('Xd drop off', MercadoLibreOrder::humanize('xd_drop_off'));
		$this->assertSame('Paid', MercadoLibreOrder::humanize('paid'));
		$this->assertSame('—', MercadoLibreOrder::humanize(null));
	}

	public function test_stock_sync_direction()
	{
		$this->assertSame('ml_to_lw', MercadoLibreListing::stockSyncDirection(5, null, false));
		$this->assertSame('none', MercadoLibreListing::stockSyncDirection(5, 5, true));
		$this->assertSame('lw_to_ml', MercadoLibreListing::stockSyncDirection(5, 3, true));
		$this->assertSame('lw_to_ml', MercadoLibreListing::stockSyncDirection(null, 2, true));
		$this->assertSame('none', MercadoLibreListing::stockSyncDirection(null, null, true));
	}

	public function test_stock_sync_follows_the_side_that_changed()
	{
		$this->assertSame('ml_to_lw', MercadoLibreListing::stockSyncDirection(3, 5, true, 5, 5));
		$this->assertSame('lw_to_ml', MercadoLibreListing::stockSyncDirection(5, 8, true, 5, 5));
		$this->assertSame('lw_to_ml', MercadoLibreListing::stockSyncDirection(2, 9, true, 5, 5));
		$this->assertSame('none', MercadoLibreListing::stockSyncDirection(5, 5, true, 5, 5));
	}

	public function test_create_listing_blockers()
	{
		$this->assertNotEmpty(MercadoLibreListing::createListingBlockers('ab', 10, 1, ['https://img']));
		$this->assertNotEmpty(MercadoLibreListing::createListingBlockers('Good title', 0, 1, ['https://img']));
		$this->assertNotEmpty(MercadoLibreListing::createListingBlockers('Good title', 10, 0, ['https://img']));
		$this->assertNotEmpty(MercadoLibreListing::createListingBlockers('Good title', 10, 1, []));
		$this->assertSame([], MercadoLibreListing::createListingBlockers('Good title', 10.5, 2, ['https://img.example/a.jpg']));
	}

	public function test_assemble_attributes_sets_sku_and_generic_brand()
	{
		$meli = new MercadoLibreService();
		$result = $meli->assembleAttributes('SKU-1', '7501234567890', [], [
			['id' => 'BRAND', 'name' => 'Marca', 'tags' => ['required' => true]],
			['id' => 'GTIN', 'name' => 'GTIN', 'tags' => ['required' => true]],
		]);

		$this->assertTrue($result['ok']);
		$ids = array_column($result['attributes'], 'id');
		$this->assertContains('SELLER_SKU', $ids);
		$this->assertContains('BRAND', $ids);
		$this->assertContains('GTIN', $ids);
	}

	public function test_assemble_attributes_fails_when_required_cannot_be_filled()
	{
		$meli = new MercadoLibreService();
		$result = $meli->assembleAttributes('SKU-1', null, [], [
			['id' => 'GTIN', 'name' => 'Código universal', 'tags' => ['required' => true]],
		]);

		$this->assertFalse($result['ok']);
		$this->assertStringContainsString('Código universal', $result['error']);
	}

	public function test_build_item_payload_truncates_title_and_caps_pictures()
	{
		$meli = new MercadoLibreService();
		$payload = $meli->buildItemPayload(
			['currency_id' => 'MXN', 'listing_type_id' => 'gold_special'],
			str_repeat('A', 80),
			'MLM123',
			99.9,
			4,
			['https://a.jpg', 'https://b.jpg'],
			[['id' => 'SELLER_SKU', 'value_name' => 'X']]
		);

		$this->assertSame(60, mb_strlen($payload['family_name']));
		$this->assertArrayNotHasKey('title', $payload);
		$this->assertSame('MLM123', $payload['category_id']);
		$this->assertCount(2, $payload['pictures']);
		$this->assertSame('buy_it_now', $payload['buying_mode']);
	}

	public function test_image_urls_prefer_full_source_and_main()
	{
		$inventory = new \App\Services\InventoryService();
		$urls = $inventory->imageUrlsFromItem([
			'Images' => [
				['Source' => 'https://cdn/thumb.jpg', 'FullSource' => 'https://cdn/full.jpg', 'IsMain' => false],
				['Source' => 'https://cdn/main-thumb.jpg', 'FullSource' => 'https://cdn/main.jpg', 'IsMain' => true],
				['Source' => 'not-a-url'],
			],
		]);

		$this->assertSame(['https://cdn/main.jpg', 'https://cdn/full.jpg'], $urls);
	}

	public function test_extract_sku_from_seller_sku_attribute()
	{
		$meli = new MercadoLibreService();
		$rows = $meli->itemToListingRows([
			'id' => 'MLM3306948249',
			'title' => 'Libretas',
			'status' => 'paused',
			'available_quantity' => 9,
			'attributes' => [
				['id' => 'SELLER_SKU', 'value_name' => 'ML-TEST-001'],
			],
		]);

		$this->assertSame('ML-TEST-001', $rows[0]['sku']);
		$this->assertSame('paused', $rows[0]['ml_status']);
	}

	public function test_last_sync_label()
	{
		$listing = new MercadoLibreListing(['last_sync_direction' => 'ml_to_lw']);
		$this->assertSame('ML → Linnworks', $listing->lastSyncLabel());

		$listing->last_sync_direction = 'lw_to_ml';
		$this->assertSame('Linnworks → ML', $listing->lastSyncLabel());

		$listing->last_sync_direction = 'create';
		$this->assertSame('Listing created', $listing->lastSyncLabel());

		$listing->last_sync_direction = null;
		$this->assertSame('—', $listing->lastSyncLabel());
	}

	public function test_titles_differ_ignores_case_and_whitespace()
	{
		$this->assertTrue(MercadoLibreListing::titlesDiffer('Libreta De Prueba Linnworks', 'notebook test Linnworks'));
		$this->assertFalse(MercadoLibreListing::titlesDiffer('  Notebook Test  ', 'notebook test'));
	}

	public function test_title_from_linnworks_item()
	{
		$inventory = new \App\Services\InventoryService();
		$this->assertSame('notebook test Linnworks', $inventory->titleFromItem(['ItemTitle' => 'notebook test Linnworks']));
		$this->assertSame('', $inventory->titleFromItem([]));
	}

	public function test_prices_differ_uses_cents()
	{
		$this->assertFalse(MercadoLibreListing::pricesDiffer(100.00, 100));
		$this->assertTrue(MercadoLibreListing::pricesDiffer(100.00, 110));
		$this->assertTrue(MercadoLibreListing::pricesDiffer(null, 50.5));
	}

	public function test_description_from_linnworks_item()
	{
		$inventory = new \App\Services\InventoryService();
		$this->assertSame('A notebook', $inventory->descriptionFromItem(['ItemDescription' => '  A notebook  ']));
		$this->assertSame('', $inventory->descriptionFromItem(['ItemTitle' => 'notebook']));
	}

	public function test_pictures_should_not_push_when_linnworks_has_none()
	{
		$this->assertFalse(MercadoLibreListing::picturesShouldPush([], null));
		$this->assertFalse(MercadoLibreListing::picturesShouldPush([], 'abc'));
		$hash = MercadoLibreListing::picturesHash(['https://cdn/a.jpg']);
		$this->assertTrue(MercadoLibreListing::picturesShouldPush(['https://cdn/a.jpg'], null));
		$this->assertFalse(MercadoLibreListing::picturesShouldPush(['https://cdn/a.jpg'], $hash));
	}

	public function test_item_has_price_automation_tag()
	{
		$this->assertTrue(\App\Services\MercadoLibreService::itemHasPriceAutomation(['tags' => ['good_quality_thumbnail', 'dynamic_standard_price']]));
		$this->assertFalse(\App\Services\MercadoLibreService::itemHasPriceAutomation(['tags' => ['good_quality_thumbnail']]));
	}

	public function test_listing_pause_activate_gates()
	{
		$listing = new MercadoLibreListing();
		$listing->ml_item_id = 'MLM1';
		$listing->ml_status = 'active';
		$this->assertTrue($listing->canPause());
		$this->assertFalse($listing->canActivate());

		$listing->ml_status = 'paused';
		$this->assertFalse($listing->canPause());
		$this->assertTrue($listing->canActivate());

		$listing->ml_status = 'closed';
		$this->assertFalse($listing->canPause());
		$this->assertFalse($listing->canActivate());
	}

	public function test_status_item_ids_include_user_product_siblings()
	{
		$this->assertSame(['MLM1'], MercadoLibreService::statusItemIds('MLM1', []));
		$this->assertSame(
			['MLM3306948249', 'MLM3306957979'],
			MercadoLibreService::statusItemIds('MLM3306948249', ['MLM3306948249', 'MLM3306957979'])
		);
	}

	public function test_ids_matching_sku_ignores_other_listings()
	{
		$items = [
			['id' => 'MLM3306948249', 'sku' => 'ML-TEST-001'],
			['id' => 'MLM3306957979', 'sku' => 'ML-TEST-001'],
			['id' => 'MLM3306936345', 'sku' => null],
			['id' => 'MLM3358565583', 'sku' => 'CF9-76C-7FE'],
		];
		$this->assertSame(
			['MLM3306948249', 'MLM3306957979'],
			MercadoLibreService::idsMatchingSku('ML-TEST-001', $items)
		);
	}
}
