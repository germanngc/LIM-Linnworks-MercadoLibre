<?php

namespace App\Http\Controllers;

use App\Models\LinnworkUser;
use App\Models\LinnworkOrder;
use App\Services\UserService;
use App\Traits\ConsumeService;
use App\Traits\CustomLogger;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class LinnworkAuthController extends Controller
{
	use ConsumeService, CustomLogger;

	protected $baseUri;

	/**
	 * login
	 * 
	 * @param Request $request
	 * @return view
	 */
	public function auth(Request $request)
	{
		$validator = Validator::make($request->all(), [
			'token' => 'max:36|min:32|required|string',
			'klaviyo_token' => 'max:6|min:6|required|string',
		]);

		if ($validator->fails()) {
			$returnErrrors = [];

			if (count($validator->errors()) > 0) {
				if ($validator->errors()->has('token')) {
					$returnErrrors['bad_request'] = 'Bad request, missing token, please contact support.';
				}

				if ($validator->errors()->has('klaviyo_token')) {
					$returnErrrors['bad_token'] = 'The Klaviyo Public API Token is required, check its format.';
				}

				return redirect()
					->back()
					->withErrors($returnErrrors);
			}
		}

		$LinnworkUserService = new UserService();
		$LinnworkUser = $LinnworkUserService->AuthorizeByApplication($request->get('token'), $request->get('klaviyo_token'), false);
		
		if ($LinnworkUser) {
			Artisan::call('IntegrationRefresh:task', ['user_id' => $LinnworkUser->user_id]);
		}else{
				
			return redirect()
				->back()
				->withErrors(['token_expired' => 'Token expired, please refresh your browser before continue.']);
		}

		return redirect('/?token=' . $request->get('token'));
	}

	/**
	 * login
	 * 
	 * @param Request $request
	 * @return view
	 */
	public function login(Request $request)
	{
		if ($request->has('token')) {
			$LinnworkUserService = new UserService();
			$LinnworkUser = $LinnworkUserService->AuthorizeByApplication($request->get('token'));

			if ($LinnworkUser && $LinnworkUser->login_status) {
				return view('authorized', compact('LinnworkUser'));
			} else {
				return view('oauth2');
			}
		} else {
			Log::warning('No token found in request');
			return view('oauth2');
		}
	}

	/**
	 * Revoca el refresh_token de Klaviyo
	 * @param string $refresh_token
	 * @return bool
	 */
	public function revokeToken(string $refresh_token)
	{
		$clientId = config('services.klaviyo.client_id');
		$clientSecret = config('services.klaviyo.client_secret');
		$server = 'https://a.klaviyo.com';

		$response = Http::withBasicAuth($clientId, $clientSecret)
			->asForm()
			->post($server . '/oauth/revoke', [
				'token_type_hint' => 'refresh_token',
				'token' => $refresh_token,
			]);

		if ($response->successful()) {
			return true;
		}

		Log::error('Klaviyo revoke token failed', [
			'status' => $response->status(),
			'body' => $response->body(),
		]);
		return false;
	}

	/**
	 * loggout
	 * 
	 * @param Request $request
	 * @return Redirect
	 */
	public function logout(Request $request)
	{
		$validator = Validator::make($request->all(), [
			'email' => 'email|required'
		]);

		if ($validator->fails()) {
			$returnErrrors = [];

			return redirect()
				->back()
				->withErrors($validator->errors());
		}

		$errMessage = '';

		try {
			$LinnworkUser = LinnworkUser::where('email', $request->get('email'))->where('login_status', true)->first();

			if ($LinnworkUser) {
				// Revocar el token de Klaviyo si existe
				if ($LinnworkUser->refresh_token) {
					$this->revokeToken($LinnworkUser->refresh_token);
				}
				
				$LinnworkUser->update(['login_status' => false, 'klaviyo_token' => '', 'access_token' => '', 'expires_in' => Carbon::now()->subDays(1), 'refresh_token' => '']);

				return view('klaviyo.disconnected');
			} else {
				throw new \Exception('We cannot log you out... are you sure you are already logged in?');
			}
		} catch (QueryException $e) {
			$errMessage = 'Unable to log you out, please contact support.';
			self::log($e, 'error', __CLASS__, __FUNCTION__);
		} catch (\Exception $e) {
			$errMessage = $this->getMessage();
			self::log($e, 'error', __CLASS__, __FUNCTION__);
		}

		return redirect()
			->back()
			->withErrors(['email' => $errMessage]);
	}

	/**
	 * uninstall
	 * 
	 * @param Request $request
	 * @return Redirect
	 */
	public function uninstall(Request $request)
	{
		$token = $request->get('token');
		$errMessage = '';
		$successMessage = '';

		if (!$token) {
			$errMessage = 'No token provided in the request.';
			return view('klaviyo.uninstalled', compact('errMessage', 'successMessage'));
		}

		$LinnworkUserService = new UserService();
		$LinnworkUser = $LinnworkUserService->AuthorizeByApplication($token);

		if (!$LinnworkUser) {
			$errMessage = 'Invalid token provided in the request.';
			return view('klaviyo.uninstalled', compact('errMessage', 'successMessage'));
		}

		if ($LinnworkUser->refresh_token) {
			$this->revokeToken($LinnworkUser->refresh_token);
		}

		try {
			LinnworkOrder::where('user_id', $LinnworkUser->user_id)->delete();
			$LinnworkUser->delete();
			$successMessage = 'Application uninstalled successfully. Please go to Klaviyo → <a href="https://www.klaviyo.com/integrations">Integrations</a>, find this app, and click "Remove".';
		} catch (QueryException $e) {
			$errMessage = 'Unable to uninstall, please contact support.';
			self::log($e, 'error', __CLASS__, __FUNCTION__);
		} catch (\Exception $e) {
			$errMessage = $this->getMessage();
			self::log($e, 'error', __CLASS__, __FUNCTION__);
		}

		return view('klaviyo.uninstalled', compact('errMessage', 'successMessage'));
	}
}
