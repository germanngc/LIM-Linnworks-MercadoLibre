<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\URL;

class AppServiceProvider extends ServiceProvider
{
	/**
	 * Register any application services.
	 *
	 * @return void
	 */
	public function register()
	{
		//
	}

	/**
	 * Bootstrap any application services.
	 *
	 * @return void
	 */
	public function boot()
	{
		if ($this->app->runningInConsole()) {
			return;
		}

		$host = request()->getHost();
		if (str_contains($host, 'ngrok')) {
			URL::forceRootUrl(request()->getSchemeAndHttpHost());
			URL::forceScheme('https');
			return;
		}

		if ($this->app->environment('production') && !request()->is('localhost*') && !request()->is('127.0.0.1*')) {
			URL::forceScheme('https');
		}
	}
}
