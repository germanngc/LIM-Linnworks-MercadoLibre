<?php

namespace App\Console\Commands;

use App\Models\LinnworkUser;
use App\Services\LinnworkApiMainPing;
use App\Traits\CustomLogger;
use Illuminate\Console\Command;
use Log;

class LinnworksTokenRefreshTask extends Command
{
	use CustomLogger;

	/**
	 * The name and signature of the console command.
	 *
	 * @var string
	 */
	protected $signature = 'LinnworksTokenRefresh:task {user_id?}';

	/**
	 * The console command description.
	 *
	 * @var string
	 */
	protected $description = 'Refresh linnworks token each 15 minutes.';

	/**
	 * Execute the console command.
	 *
	 * @return int
	 */
	public function handle()
	{
		$user_id = $this->argument('user_id') ?? null;

		$this->refreshLinnworkUser();
	}

	/**
	 * Refresh Gorgias Linnworks User Tokens
	 * 
	 * @param mixed $users_id The user Id in the database.
	 * @return void
	 */
	private function refreshLinnworkUser(mixed $user_id = null): void
	{
		if ($user_id) {
			$LinnworksUsers = LinnworkUser::where('id', $user_id)->get();
		} else {
			$LinnworksUsers = LinnworkUser::where('login_status', true)->get();
		}

		$LinnworkApiMainPing = new LinnworkApiMainPing();

		foreach ($LinnworksUsers AS $User) {
			if (!$LinnworkApiMainPing->_linnworksPing($User->token, $User->server)) {
				self::log(['file' => __FILE__, 'line' => __LINE__, 'message' => ':Integration error in [RefreshLinworksToken] for user' . $User->email], 'error', __CLASS__, __FUNCTION__);
			}
		}
	}
}
