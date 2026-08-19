<?php

namespace App\Services;

use App\Traits\ConsumeService;
use App\Traits\CustomLogger;
use GuzzleHttp\Exception\RequestException;

class InventoryService
{
    use ConsumeService, CustomLogger;

	protected $baseUri;

	/**
	 * GetStockLocations
	 * 
	 * @param string $token
	 * @param string $server
	 */
	public function GetStockLocations(string $token, string $server)
	{
		$this->baseUri = $server;

		try {
			$stockLocations = json_decode($this->request(
				'POST',
				'/api/Inventory/GetStockLocations',
				[
				],
				[
					'Accept' => 'application/json',
					'Accept-Encoding' => 'gzip, deflate',
                    'Authorization' => $token,
					'Connection' => 'keep-alive',
					'Content-Type' => 'application/x-www-form-urlencoded; charset=UTF-8',
				],
				true
			), true);

			return $stockLocations;
		} catch (RequestException $e) {
			self::log($e, 'error', __CLASS__, __FUNCTION__);
			return false;
		}
	}



	/**
	 * GetStockLocations
	 * 
	 * @param string $token
	 * @param string $server
	 */
	public function GetCountries(string $token, string $server)
	{
		$this->baseUri = $server;

		try {
			$countries = json_decode($this->request(
				'POST',
				'/api/Inventory/GetCountries',
				[
				],
				[
					'Accept' => 'application/json',
					'Accept-Encoding' => 'gzip, deflate',
                    'Authorization' => $token,
					'Connection' => 'keep-alive',
					'Content-Type' => 'application/x-www-form-urlencoded; charset=UTF-8',
				],
				true
			), true);

			return $countries;
		} catch (RequestException $e) {
			self::log($e, 'error', __CLASS__, __FUNCTION__);
			return false;
		}
	}

	private function lwHeaders(string $token): array
	{
		return [
			'Accept' => 'application/json',
			'Accept-Encoding' => 'gzip, deflate',
			'Authorization' => $token,
			'Connection' => 'keep-alive',
			'Content-Type' => 'application/x-www-form-urlencoded; charset=UTF-8',
		];
	}

	/** Find stock item by SKU (ItemNumber). */
	public function GetStockItemBySKU(string $token, string $server, string $sku)
	{
		$this->baseUri = $server;

		try {
			$result = json_decode($this->request(
				'POST',
				'/api/Stock/GetStockItems',
				[
					'keyword' => $sku,
					'loadCompositeParents' => 'false',
					'loadVariationParents' => 'false',
					'entriesPerPage' => 20,
					'pageNumber' => 1,
					'dataRequirements' => '["StockLevels"]',
					'searchTypes' => '["SKU"]',
				],
				$this->lwHeaders($token),
				true
			), true);

			$items = $result['Data'] ?? $result ?? [];
			if (!is_array($items)) {
				return null;
			}

			foreach ($items as $item) {
				$number = $item['ItemNumber'] ?? $item['SKU'] ?? null;
				if ($number !== null && strcasecmp((string) $number, $sku) === 0) {
					return $item;
				}
			}

			return null;
		} catch (RequestException $e) {
			self::log($e, 'error', __CLASS__, __FUNCTION__);
			return false;
		}
	}

	/**
	 * Create a basic inventory item. Caller must pass a new GUID for StockItemId.
	 * @return array|false
	 */
	public function AddInventoryItem(string $token, string $server, string $stockItemId, string $sku, string $title, float $retailPrice = 0)
	{
		$this->baseUri = $server;

		try {
			$payload = json_encode([
				'StockItemId' => $stockItemId,
				'ItemNumber' => $sku,
				'ItemTitle' => $title !== '' ? $title : $sku,
				'RetailPrice' => $retailPrice,
				'Quantity' => 0,
			]);

			$result = json_decode($this->request(
				'POST',
				'/api/Inventory/AddInventoryItem',
				['inventoryItem' => $payload],
				$this->lwHeaders($token),
				true
			), true);

			return $result ?? ['StockItemId' => $stockItemId];
		} catch (RequestException $e) {
			self::log($e, 'error', __CLASS__, __FUNCTION__);
			return false;
		}
	}

	public function SetStockLevel(string $token, string $server, string $sku, string $locationId, int $level)
	{
		$this->baseUri = $server;

		try {
			$stockLevels = json_encode([[
				'SKU' => $sku,
				'LocationId' => $locationId,
				'Level' => $level,
			]]);

			return json_decode($this->request(
				'POST',
				'/api/Stock/SetStockLevel',
				[
					'stockLevels' => $stockLevels,
					'changeSource' => 'MercadoLibreSync',
				],
				$this->lwHeaders($token),
				true
			), true);
		} catch (RequestException $e) {
			self::log($e, 'error', __CLASS__, __FUNCTION__);
			return false;
		}
	}

	public function stockLevelFromItem(array $item, ?string $locationId = null): ?int
	{
		$levels = $item['StockLevels'] ?? $item['stockLevels'] ?? null;
		if (is_array($levels) && $levels) {
			foreach ($levels as $level) {
				$loc = $level['Location']['StockLocationId']
					?? $level['StockLocationId']
					?? $level['LocationId']
					?? null;
				if ($locationId && $loc && strcasecmp((string) $loc, $locationId) !== 0) {
					continue;
				}
				if (isset($level['Available'])) {
					return (int) $level['Available'];
				}
				if (isset($level['StockLevel'])) {
					return (int) $level['StockLevel'];
				}
			}
			$first = $levels[0];
			if (isset($first['Available'])) {
				return (int) $first['Available'];
			}
			if (isset($first['StockLevel'])) {
				return (int) $first['StockLevel'];
			}
		}

		if (isset($item['Quantity'])) {
			return (int) $item['Quantity'];
		}

		return null;
	}
}
