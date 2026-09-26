<?php

namespace App\Http\Controllers;

use App\Models\MercadoLibreAccount;
use App\Models\MercadoLibreListing;
use App\Models\MercadoLibreOrder;
use App\Services\MercadoLibreService;
use App\Services\UserService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Session;

class MercadoLibreOAuthController extends Controller
{
	public function redirect(Request $request)
	{
		$clientId = config('services.mercadolibre.client_id');
		$redirectUri = config('services.mercadolibre.redirect');
		$authHost = rtrim(config('services.mercadolibre.auth_host'), '/');

		if (!$clientId || !$redirectUri) {
			return response('Falta MELI_CLIENT_ID o MELI_REDIRECT_URI en .env', 500);
		}

		$state = bin2hex(random_bytes(16));
		Cache::put('meli_oauth:' . $state, [
			'linnwork_user_id' => $request->get('linnwork_user_id') ?: Session::get('linnworks_user_id'),
		], now()->addMinutes(15));

		$url = $authHost . '/authorization?' . http_build_query([
			'response_type' => 'code',
			'client_id' => $clientId,
			'redirect_uri' => $redirectUri,
			'state' => $state,
		]);

		return redirect()->away($url);
	}

	public function callback(Request $request, MercadoLibreService $meli)
	{
		$state = $request->get('state');
		$code = $request->get('code');

		if (!$code) {
			$mlError = $request->get('error_description') ?: $request->get('error') ?: $request->get('message');

			return response(
				"No se recibió el código de Mercado Libre.\n\n"
				.($mlError ? "ML dijo: {$mlError}\n\n" : "ML no mandó ?code= (suele ser redirect_uri distinto al de la app).\n\n")
				.'En Mis aplicaciones → Maximiliano el Redirect URI tiene que ser exactamente:'."\n"
				.config('services.mercadolibre.redirect'),
				400
			);
		}

		$oauth = $state ? Cache::pull('meli_oauth:' . $state) : [];

		try {
			$tokenData = $meli->exchangeCode($code);

			if (!$tokenData || empty($tokenData['access_token'])) {
				return response('Error al obtener el access token de Mercado Libre. Revisa MELI_CLIENT_SECRET y MELI_REDIRECT_URI.', 400);
			}

			$linnworkUserId = $oauth['linnwork_user_id'] ?? null;
			if ($linnworkUserId === '') {
				$linnworkUserId = null;
			}

			$account = $meli->saveAccount($tokenData, null, $linnworkUserId);
		} catch (\Throwable $e) {
			\Illuminate\Support\Facades\Log::error('ML oauth callback failed', [
				'message' => $e->getMessage(),
			]);

			return response(
				'OAuth de ML ok, pero falló al guardar: '.$e->getMessage()."\n\nSi la tabla no existe, corre:\ndocker-compose exec app php artisan migrate",
				500
			);
		}

		return redirect('/mercadolibre');
	}

	public function dashboard(Request $request, ?string $token = null)
	{
		// Linnworks EUI passes token in path (/mercadolibre/[{TOKEN}]) or ?token=
		$token = $token ?: $request->get('token');
		$user = null;

		if ($token) {
			$user = (new UserService())->AuthorizeByApplication(
				$token,
				false,
				(string) config('services.mercadolibre.linnworks_app_id'),
				(string) config('services.mercadolibre.linnworks_app_secret'),
			);
			if ($user) {
				Session::put('linnworks_iframe_token', $token);
				Session::put('linnworks_user_id', $user->user_id);
			}
		}

		$account = MercadoLibreAccount::query()->latest()->first();
		$orders = collect();
		$listings = collect();
		$closedOrdersCount = 0;

		if ($account) {
			if ($user && $account->linnwork_user_id !== $user->user_id) {
				$account->update(['linnwork_user_id' => $user->user_id]);
			}
			$all = MercadoLibreOrder::where('mercadolibre_account_id', $account->id)->latest()->limit(200)->get();
			$orders = $all->filter(fn (MercadoLibreOrder $o) => $o->isOpen())->values();
			$closedOrdersCount = $all->count() - $orders->count();
			$listings = MercadoLibreListing::where('mercadolibre_account_id', $account->id)
				->orderByRaw('ml_item_id IS NULL, ml_item_id = ""')
				->latest()
				->limit(100)
				->get();
		}

		$linnworkUserId = Session::get('linnworks_user_id');

		return view('mercadolibre.dashboard', compact('account', 'orders', 'listings', 'closedOrdersCount', 'linnworkUserId'));
	}

	public function sync()
	{
		if (!MercadoLibreAccount::query()->exists()) {
			return redirect('/mercadolibre')->withErrors(['sync' => 'Connect Mercado Libre first.']);
		}

		Artisan::call('MercadoLibreSync:task');
		$output = trim(Artisan::output());

		return redirect('/mercadolibre')->with('status', $output !== '' ? 'Sync complete. '.$output : 'Orders synced.');
	}

	public function syncInventory()
	{
		if (!MercadoLibreAccount::query()->exists()) {
			return redirect('/mercadolibre')->withErrors(['sync' => 'Connect Mercado Libre first.']);
		}

		Artisan::call('MercadoLibreInventorySync:task');
		$output = trim(Artisan::output());

		return redirect('/mercadolibre')->with('status', $output !== '' ? 'Sync complete. '.$output : 'Inventory synced.');
	}

	public function createListings(Request $request)
	{
		if (!MercadoLibreAccount::query()->exists()) {
			return redirect('/mercadolibre')->withErrors(['sync' => 'Connect Mercado Libre first.']);
		}

		set_time_limit(120);
		$sku = trim((string) $request->get('sku', ''));
		$params = ['--create-listings' => true];
		if ($sku !== '') {
			$params['--sku'] = $sku;
		}
		Artisan::call('MercadoLibreInventorySync:task', $params);
		$output = trim(Artisan::output());

		return redirect('/mercadolibre')->with('status', $output !== '' ? 'Sync complete. '.$output : 'Listings created.');
	}

	public function pauseListing(MercadoLibreListing $listing, MercadoLibreService $meli)
	{
		return $this->setListingStatus($listing, $meli, 'paused');
	}

	public function activateListing(MercadoLibreListing $listing, MercadoLibreService $meli)
	{
		return $this->setListingStatus($listing, $meli, 'active');
	}

	private function setListingStatus(MercadoLibreListing $listing, MercadoLibreService $meli, string $status)
	{
		$account = MercadoLibreAccount::query()->latest()->first();
		if (!$account || !$listing->isListed()) {
			return redirect('/mercadolibre')->withErrors(['sync' => 'Listing not found.']);
		}
		if ($listing->mercadolibre_account_id !== $account->id) {
			return redirect('/mercadolibre')->withErrors(['sync' => 'Listing not found.']);
		}
		if (!$meli->ensureToken($account)) {
			return redirect('/mercadolibre')->withErrors(['sync' => 'Cannot refresh Mercado Libre token.']);
		}

		$ok = $meli->updateItemStatus($account, $listing->ml_item_id, $status, $listing->sku);
		$actualById = $meli->lastStatusByItemId;
		foreach ($actualById as $mlItemId => $actualStatus) {
			MercadoLibreListing::query()
				->where('mercadolibre_account_id', $account->id)
				->where('ml_item_id', $mlItemId)
				->update([
					'ml_status' => $actualStatus,
					'last_sync_direction' => 'lw_to_ml',
					'last_error' => $ok ? null : mb_substr($meli->lastError ?: 'Status update failed', 0, 1000),
				]);
		}
		if (!$ok) {
			if (!$actualById) {
				$listing->update(['last_error' => mb_substr($meli->lastError ?: 'Status update failed', 0, 1000)]);
			}
			return redirect('/mercadolibre')->withErrors(['sync' => $meli->lastError ?: 'Could not update listing status.']);
		}

		$ids = $meli->lastStatusItemIds ?: [$listing->ml_item_id];
		$verb = $status === 'paused' ? 'paused' : 'activated';
		$msg = 'Listing '.$verb.'.';
		if (count($ids) > 1) {
			$msg = 'Listing '.$verb.' on '.count($ids).' Mercado Libre channels.';
		}

		return redirect('/mercadolibre')->with('status', $msg);
	}

	public function disconnect()
	{
		MercadoLibreAccount::query()->delete();

		return redirect('/mercadolibre')->with('status', 'Mercado Libre disconnected. Connect again to start the demo.');
	}
}
