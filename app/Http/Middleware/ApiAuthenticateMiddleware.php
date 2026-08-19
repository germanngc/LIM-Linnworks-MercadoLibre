<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ApiAuthenticateMiddleware
{
	/**
	 * Handle an incoming request.
	 *
	 * @param  \Illuminate\Http\Request  $request
	 * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
	 * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
	 */
	public function handle(Request $request, Closure $next)
	{
		$key = config('app.api_token');

		if (empty($key) || ($key != $request->header('Authorization'))) {
			return response()->json([
				'status' => 401,
				'errors' => 'Unauthenticated! Can not access api',
			], 401);
		}

		return $next($request);
	}
}
