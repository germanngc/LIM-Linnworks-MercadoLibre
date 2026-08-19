<?php

namespace App\Services;

use App\Traits\ConsumeService;
use App\Traits\CustomLogger;
use GuzzleHttp\Exception\RequestException;

class LinnworkApiMainPing
{
    use ConsumeService, CustomLogger;

	protected $baseUri;

	/**
	 * Linnworks Api Main Ping
	 * Refresh the user token for the applications intalled.
	 * 
	 * @param string $token The uuid token to refresh.
	 * @param string $server The Endpoint to validate the request.
	 * @return bool
	 */
	public function _linnworksPing(string $token, string $server): bool
	{
		$this->baseUri = $server;

		try {
			$this->request(
				'POST',
				'/api/Main/ping',
				[
				],
				[
					'Accept' => 'application/json',
					'Connection' => 'keep-alive',
					'Content-Type' => 'application/x-www-form-urlencoded; charset=UTF-8',
					'Accept-Encoding' => 'gzip, deflate',
					'Authorization' => $token
				],
				true
			);

			return true;
		} catch(RequestException $e) {
			self::log($e, 'error', __CLASS__, __FUNCTION__);
			return false;
		}
	}
}
