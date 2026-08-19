<?php

namespace App\Console\Commands;

use App\Http\Controllers\KlaviyoOAuthController;
use App\Models\LinnworkUser;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class KlaviyoTokenRefreshTask extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'klaviyo:refresh-token';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Refresh Klaviyo OAuth access tokens for all users.';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(KlaviyoOAuthController $klaviyoOAuthController)
    {
        Log::channel('klaviyo_refresh_token')->info('Starting Klaviyo token refresh task...');
        $this->info('Starting Klaviyo token refresh task...');

        // Get users whose tokens expire in the next hour or are already expired
        $users = LinnworkUser::whereNotNull('refresh_token')
            ->where('expires_in', '<=', Carbon::now()->addMinutes(15))
            ->get();

        if ($users->isEmpty()) {
            Log::channel('klaviyo_refresh_token')->info('No tokens to refresh at this time.');
            $this->info('No tokens to refresh at this time.');
            return 0;
        }

        Log::channel('klaviyo_refresh_token')->info("Found {$users->count()} users to refresh.");
        $this->info("Found {$users->count()} users to refresh.");

        foreach ($users as $user) {
            Log::channel('klaviyo_refresh_token')->info("Refreshing token for user ID: {$user->id}...");
            $this->info("Refreshing token for user ID: {$user->id}...");

            try {
                $success = $klaviyoOAuthController->refreshAccessToken($user);

                if ($success) {
                    Log::channel('klaviyo_refresh_token')->info("Token for user ID: {$user->id} refreshed successfully.");
                    $this->info("Token for user ID: {$user->id} refreshed successfully.");
                } else {
                    Log::channel('klaviyo_refresh_token')->error("Failed to refresh token for user ID: {$user->id}.");
                    $this->warn("Failed to refresh token for user ID: {$user->id}.");
                }
            } catch (\Exception $e) {
                Log::channel('klaviyo_refresh_token')->error("Error refreshing token for user ID: {$user->id} - " . $e->getMessage());
                $this->error("An error occurred for user ID: {$user->id}. Check logs.");
            }
        }

        Log::channel('klaviyo_refresh_token')->info('Klaviyo token refresh task finished.');
        $this->info('Klaviyo token refresh task finished.');
        return 0;
    }
} 