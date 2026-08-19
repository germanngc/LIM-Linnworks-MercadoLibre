<?php

namespace App\Console\Commands;

use App\Models\LinnworkUser;
use App\Services\UserService;
use App\Services\OrderService;
use App\Services\InventoryService;
use App\Traits\CustomLogger;
use Illuminate\Console\Command;
use Log;

class IntegrationProcessedOrders extends Command
{
    use CustomLogger;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'IntegrationProcessedOrders:task {user_id?} {start_date?} {end_date?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Refresh the info each 15 minutes to check updates.';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $user_id = $this->argument('user_id') ?? null;
        $user_id = $user_id == 'null' ? null : $user_id;
        $start_date = $this->argument('start_date') ?? null;
        $end_date = $this->argument('end_date') ?? null;

        if (!$user_id) {
            $AllUsersLinnworks = LinnworkUser::where('login_status', true)->get();
        } else {
            $AllUsersLinnworks = LinnworkUser::where('user_id', $user_id)->get();
        }

        $LocationService = new InventoryService();
        $ProcessedOrders = new OrderService();

        foreach ($AllUsersLinnworks as $User) {
            $StockLocations = $LocationService->GetStockLocations($User->token, $User->server);

            if ($StockLocations === false) {
                self::log(['file' => __FILE__, 'line' => __LINE__, 'message' => ':Integration error in [StockLocations] for user' . $User->email], 'error', __CLASS__, __FUNCTION__);
                continue;
            }

            foreach ($StockLocations as $Location) {
                $ordersProcessed = $ProcessedOrders->SearchProcessedOrders($User->token, $User->server, $start_date, $end_date);

                if ($ordersProcessed === false) {
                    self::log(['file' => __FILE__, 'line' => __LINE__, 'message' => ':Integration error in [ProcessedOrders] for user ' . $User->email], 'error', __CLASS__, __FUNCTION__);
                    continue;
                }

                $processedData = $ordersProcessed['ProcessedOrders']['Data'] ?? [];

                if ($ordersProcessed['ProcessedOrders']['TotalPages'] > 1) {
                    for ($i = 2; $i <= $ordersProcessed['ProcessedOrders']['TotalPages']; $i++) {
                        $processedData = array_merge(
                            $processedData,
                            $ProcessedOrders->SearchProcessedOrders($User->token, $User->server, $start_date, $end_date, $i)['ProcessedOrders']['Data'] ?? []
                        );

                        if ($ordersProcessed === false) {
                            self::log(['file' => __FILE__, 'line' => __LINE__, 'message' => ':Integration error in [ProcessedOrders] for user ' . $User->email], 'error', __CLASS__, __FUNCTION__);
                            continue;
                        }
                    }
                }
                
                foreach ($processedData as $order) {
                    $ProcessedOrders->compareProcessedOrders($order, $User->token, $User->server, $User->user_id);
                }
            }
        }
    }
}
