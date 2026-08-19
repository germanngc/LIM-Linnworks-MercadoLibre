<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\v1\AuthorizeController AS Authorize;
use Illuminate\Http\Request;

class OrdersController extends Controller
{
	/**
	 * get
	 * 
	 * @param Request $request
	 */
	public function get(Request $request)
	{
		$Authorize = new Authorize();

		return response()->json($Authorize->get());
	}
}
