<?php

namespace App\Http\Middleware;

use App\Models\ChannelTenant;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ResolveChannelToken
{
	public function handle(Request $request, Closure $next)
	{
		$token = (string) (
			$request->input('AuthorizationToken')
			?? $request->input('authorizationToken')
			?? $request->header('AuthorizationToken')
			?? $request->header('X-AuthorizationToken')
			?? ''
		);

		if ($token === '') {
			Log::warning('Channel missing AuthorizationToken', ['path' => $request->path()]);

			return response()->json(['Error' => 'Missing AuthorizationToken'], 401);
		}

		$tenant = ChannelTenant::query()
			->where('authorization_token', $token)
			->where('active', true)
			->first();

		if (!$tenant) {
			return response()->json(['Error' => 'Invalid or inactive AuthorizationToken'], 401);
		}

		$request->attributes->set('channel_tenant', $tenant);

		return $next($request);
	}
}
