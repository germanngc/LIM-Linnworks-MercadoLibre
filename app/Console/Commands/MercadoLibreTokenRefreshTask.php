<?php

namespace App\Console\Commands;

use App\Models\MercadoLibreAccount;
use App\Services\MercadoLibreService;
use App\Traits\CustomLogger;
use Illuminate\Console\Command;

class MercadoLibreTokenRefreshTask extends Command
{
	use CustomLogger;

	protected $signature = 'MercadoLibreTokenRefresh:task';
	protected $description = 'Refresh Mercado Libre access tokens before they expire.';

	public function handle(MercadoLibreService $meli)
	{
		$accounts = MercadoLibreAccount::query()->get();

		foreach ($accounts as $account) {
			if (!$account->tokenExpired()) {
				continue;
			}

			if (!$meli->refreshToken($account)) {
				self::log([
					'file' => __FILE__,
					'line' => __LINE__,
					'message' => 'ML token refresh failed for user ' . $account->ml_user_id,
				], 'error', __CLASS__, __FUNCTION__);
				$this->error('Failed refresh for ML user ' . $account->ml_user_id);
			} else {
				$this->info('Refreshed ML user ' . $account->ml_user_id);
			}
		}

		return self::SUCCESS;
	}
}
