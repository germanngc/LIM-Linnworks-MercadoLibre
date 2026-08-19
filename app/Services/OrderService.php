<?php

namespace App\Services;

use App\Models\LinnworkOrder;
use App\Traits\ConsumeService;
use App\Traits\CustomLogger;
use Carbon\Carbon;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;

class OrderService
{
	use ConsumeService, CustomLogger;

	protected $baseUri;

	/**
	 * Get all open orders
	 * 
	 * @param string $token (Required) The token to authenticate the request.
	 * @param string $server (Required) The server to search for orders.
	 * @param string $fulfilmentCenter (Required) The fulfilment center to search for orders.
	 * @param string $fromDate (Optional) The initial date range to search for orders. If not specified, the default is 1 day ago.
	 * @param string $toDate (Optional) The end date range to search for orders. If not specified, the default is now.
	 * 
	 * @return array
	 */
	public function GetAllOpenOrders(string $token, string $server, string $fulfilmentCenter, string $fromDate = null, string $toDate = null)
	{
		$this->baseUri = $server;
		$fromDate = $fromDate ?? Carbon::now()->subDays(1)->toIso8601String();
		// $fromDate = Carbon::now()->subYears(2)->toIso8601String();
		$toDate = $toDate ?? Carbon::now()->toIso8601String();

		try {
			$OpenOrders = json_decode($this->request(
				'POST',
				'/api/Orders/GetAllOpenOrders',
				[
					'filters' => '{
						"DateFields": [{
							"DateFrom": "' . $fromDate . '",
							"DateTo": "' . $toDate . '",
							"FieldCode": "GENERAL_INFO_DATE",
							"Type": "Range"
						}]
					}',
					'fulfilmentCenter' => $fulfilmentCenter,
					'sorting' => '[{"FieldCode": 0, "Direction": 0, "Order": 1}]',
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

			return $OpenOrders;
		} catch (RequestException $e) {
			self::log($e, 'error', __CLASS__, __FUNCTION__);
			return false;
		}
	}

	/**
	 * Search Processed Orders
	 * 
	 * @param string $token (Required) The token to use for authentication.
	 * @param string $server (Required) The server to use for authentication. (e.g. https://eu1.linnworks.net)
	 * @param string $fromDate (Optional) The initial date range to search for orders. If not specified, the default is 1 day ago.
	 * @param string $toDate (Optional) The end date range to search for orders. If not specified, the default is now.
	 * @param int $pageNumber (Optional) The page number to return. If not specified, the default is 1.
	 * 
	 * @return array
	 */
	public function SearchProcessedOrders(string $token, string $server, string $fromDate = null, string $toDate = null, int $pageNumber = null)
	{
		$this->baseUri = $server;
		$fromDate = $fromDate ?? Carbon::now()->subDays(1)->toIso8601String();
		// $fromDate = Carbon::now()->subYears(2)->toIso8601String();
		$toDate = $toDate ?? Carbon::now()->toIso8601String();

		try {
			$ProcessedOrders = json_decode($this->request(
				'POST',
				'/api/ProcessedOrders/SearchProcessedOrders',
				[
					'request' => '{
						"DateField": "processed",
						"FromDate": "' . $fromDate . '",
						"ToDate": "' . $toDate . '",
						"PageNumber": ' . ($pageNumber ?? 1) . ',
						"ResultsPerPage": 100
					}',
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

			return $ProcessedOrders;
		} catch (RequestException $e) {
			self::log($e, 'error', __CLASS__, __FUNCTION__);
			return false;
		}
	}

	/**
	 * Get orders data
	 * 
	 * @param string $token
	 * @param string $server
	 * @param array $ordersIds
	 * 
	 * @return array
	 */
	public function GetOrders(string $token, string $server, array $ordersIds)
	{
		$this->baseUri = $server;

		try {
			$orders = json_decode($this->request(
				'POST',
				'/api/Orders/GetOrders',
				[
					'fulfilmentCenter'=> null,
					'loadAdditionalInfo' => true,
					'loadItems' => true,
					'ordersIds' => json_encode($ordersIds),
				],
				[
					'Accept' => 'application/json',
					'Connection' => 'keep-alive',
					'Content-Type' => 'application/x-www-form-urlencoded; charset=UTF-8',
					'Accept-Encoding' => 'gzip, deflate',
					'Authorization' => $token
				],
				true
			), true);

			return $orders;
		} catch (RequestException $e) {
			self::log($e, 'error', __CLASS__, __FUNCTION__);
			return false;
		}
	}

	/**
	 * Get order details by num order id
	 * 
	 * @param string $token
	 * @param string $server
	 * @param string $orderId
	 * 
	 * @return array
	 */
	public function GetOrdersDetails(string $token, string $server, string $orderId)
	{
		$this->baseUri = $server;

		try {
			$orders = json_decode($this->request(
				'POST',
				'/api/Orders/GetOrderDetailsByNumOrderId',
				[
					'OrderId'=> $orderId,
				],
				[
					'Accept' => 'application/json',
					'Connection' => 'keep-alive',
					'Content-Type' => 'application/x-www-form-urlencoded; charset=UTF-8',
					'Accept-Encoding' => 'gzip, deflate',
					'Authorization' => $token
				],
				true
			), true);

			return $orders;
		} catch (RequestException $e) {
			self::log($e, 'error', __CLASS__, __FUNCTION__);
			return false;
		}
	}

	/**
	 * Update shipping info (tracking) on an open Linnworks order.
	 */
	public function SetOrderShippingInfo(string $token, string $server, string $orderId, array $info)
	{
		$this->baseUri = $server;

		try {
			return json_decode($this->request(
				'POST',
				'/api/Orders/SetOrderShippingInfo',
				[
					'orderId' => $orderId,
					'info' => json_encode($info),
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
		} catch (RequestException $e) {
			self::log($e, 'error', __CLASS__, __FUNCTION__);
			return false;
		}
	}

	/**
	 * Compare Processed Orders if exists or not on the database
	 * 
	 * @param array $orderData each of the orders from the response
	 * @param string $token
	 * @param string $server
	 * @param string $userId
	 * 
	 * @return array $object
	 */
	public function compareProcessedOrders(array $orderData, string $token, string $server, string $userId)
	{
		try {
			$order = LinnworkOrder::where('order_id', $orderData['pkOrderID'])->first();
			
			$newOrder = $this->GetOrdersDetails($token, $server, $orderData['nOrderId']);
			$this->_saveOrdersData($newOrder, $userId);
		} catch(QueryException $e) {
			self::log($e, 'error', __CLASS__, __FUNCTION__);
			return false;
		}
	}

	/**
	 * Save Orders Data
	 * 
	 * @param array $orderData Json output from Linnworks API
	 * @param string $userId
	 * 
	 * @return array $object
	 */
	public function _saveOrdersData(array $orderData, string $userId)
	{
		try {
			$KlaviyoService = new KlaviyoService();
			$formatEventData = $KlaviyoService->formatEventData($orderData)['properties'] ?? [];

			$validatedData = [
				'num_order_id'			=> $orderData['NumOrderId'],
				'order_id'				=> $orderData['OrderId'],
				'user_id'				=> $userId,
				'items_obj'				=> $orderData['Items'],
				'postal_service_id'		=> $orderData['ShippingInfo']['PostalServiceId'],
				'postal_service_name'	=> $orderData['ShippingInfo']['PostalServiceName'],
				'received_date'			=> Carbon::parse($orderData['GeneralInfo']['ReceivedDate']),
				'response_obj'			=> $orderData,
				'response_obj_checksum'	=> $this->_setChecksum($formatEventData),
				'shipping_address_obj'	=> $orderData['CustomerInfo']['Address'],
				'source'				=> $orderData['GeneralInfo']['Source'],
				'status'				=> $orderData['GeneralInfo']['Status'],
				'tracking_number'		=> $orderData['ShippingInfo']['TrackingNumber'],
				'vendor'				=> $orderData['ShippingInfo']['Vendor'],
				'total_weight'			=> $orderData['ShippingInfo']['TotalWeight'],
				'customer_obj'			=> $orderData['CustomerInfo'],
			];

			$order = LinnworkOrder::where('order_id', $orderData['OrderId'])->first();

			if (!$order && $orderData['ShippingInfo']['TrackingNumber']) {
				$order = LinnworkOrder::create($validatedData);
				$succeed = $KlaviyoService->KlaviyoClientEvent('Linnworks Order Processed', $validatedData);

				if (!$succeed) {
					Log::error('Failed to send order to Klaviyo, order id: ' . $orderData['OrderId']);
					$order->delete();
					return false;
				}
			}

			return $order;
		} catch(QueryException $e) {
			self::log($e, 'error', __CLASS__, __FUNCTION__);
			return false;
		}
	}

	/**
	 * Create a Checksum comparation for changes.
	 * 
	 * @param array $klaviyoData the responsed Json
	 * 
	 * @return string The checksum code.
	 */
	private function _setChecksum(array $klaviyoData): string
	{
		return base64_encode(hash('sha256', json_encode($klaviyoData)));
	}
}
