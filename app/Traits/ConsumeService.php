<?php

namespace App\Traits;

use GuzzleHttp\Client;

trait ConsumeService
{
	/**
	 * request
	 * Send request to any service
	 * 
	 * @param string $method The method to use GET|POST|PUT
	 * @param string $url The url to hit.
	 * @param array $formParams The data to send to the endpoint.
	 * @param array $headers The headers to parse in th request.
	 * @param bool $body Check if the request must be 
	 * @return string
	 */
	public function request(string $method, string $url, array $formParams = [], array $headers = [], bool $body = false)
	{
		$client = new Client([
			'base_uri' => $this->baseUri,
		]);

		if (isset($this->secret)) {
			$headers['Authorization'] = $this->secret;
		}

		$options = [
			'verify' => false,
			'headers' => $headers
		];

		if ($body) {
			$options['form_params'] = $formParams;
		} else {
			$options['body'] = $formParams;
		}

		return $client->request($method, $url, $options)->getBody()->getContents();
	}
}
