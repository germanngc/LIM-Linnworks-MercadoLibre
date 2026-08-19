<?php

namespace App\Console\Commands;

use App\Models\LinnworkUser;
use App\Services\UserService;
use App\Services\OrderService;
use App\Services\InventoryService;
use App\Traits\CustomLogger;
use Illuminate\Console\Command;

class IntegrationRefreshTask extends Command
{
    use CustomLogger;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'IntegrationRefresh:task {user_id?} {start_date?} {end_date?}';

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
        $AllOpenOrders = new OrderService();

        foreach ($AllUsersLinnworks as $User) {
            $StockLocations = $LocationService->GetStockLocations($User->token, $User->server);

            if ($StockLocations === false) {
                self::log(['file' => __FILE__, 'line' => __LINE__, 'message' => ':Integration error in [StockLocations] for user' . $User->email], 'error', __CLASS__, __FUNCTION__);
                continue;
            }

            foreach ($StockLocations as $Location) {
                $ordersId = $AllOpenOrders->GetAllOpenOrders($User->token, $User->server, $Location['StockLocationId'], $start_date, $end_date);

                if ($ordersId === false) {
                    self::log(['file' => __FILE__, 'line' => __LINE__, 'message' => ':Integration error in [AllOpenOrders] for user ' . $User->email], 'error', __CLASS__, __FUNCTION__);
                    continue;
                }
                
                $orders = $AllOpenOrders->GetOrders($User->token, $User->server, $ordersId);

                if ($orders === false) {
                    self::log(['file' => __FILE__, 'line' => __LINE__, 'message' => ':Integration error in [OrderDetails] for user' . $User->email], 'error', __CLASS__, __FUNCTION__);
                    continue;
                }

                foreach ($orders as $order) {
                    if (strpos($order['CustomerInfo']['Address']['EmailAddress'], '@marketplace.amazon') !== false) {
                        continue;
                    }

                    $AllOpenOrders->_saveOrdersData($order, $User->user_id);
                }
            }
        }
    }
}
