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

	public function test_extract_sku_from_seller_sku_attribute()
	{
		$meli = new MercadoLibreService();
		$rows = $meli->itemToListingRows([
			'id' => 'MLM3306948249',
			'title' => 'Libretas',
			'available_quantity' => 9,
			'attributes' => [
				['id' => 'SELLER_SKU', 'value_name' => 'ML-TEST-001'],
			],
		]);

		$this->assertSame('ML-TEST-001', $rows[0]['sku']);
	}

	public function test_last_sync_label()
	{
		$listing = new MercadoLibreListing(['last_sync_direction' => 'ml_to_lw']);
		$this->assertSame('ML → Linnworks', $listing->lastSyncLabel());

		$listing->last_sync_direction = 'lw_to_ml';
		$this->assertSame('Linnworks → ML', $listing->lastSyncLabel());

		$listing->last_sync_direction = null;
		$this->assertSame('—', $listing->lastSyncLabel());
	}
}
