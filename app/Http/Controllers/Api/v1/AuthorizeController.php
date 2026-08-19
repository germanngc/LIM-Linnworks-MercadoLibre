<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Traits\ConsumeService;
use Illuminate\Http\Request;

class AuthorizeController extends Controller
{
	use ConsumeService;

	protected $baseUri;

	public function __construct()
	{
		$this->baseUri = env('LINNWORKS_API_URL', 'https://api.linnworks.net/api/');
	}

	/**
	 * get
	 */
	public function get()
	{
		$authorization = $this->performRequest(
			'POST',
			'Auth/AuthorizeByApplication',
			[
				'ApplicationId' => env('LINNWORKS_APP_ID', ''),
				'ApplicationSecret' => env('LINNWORKS_APP_SECRET', ''),
				'Token' => config('app.api_token'),
			],
			$header,
			true
		);
		return $authorization;
	}
}
