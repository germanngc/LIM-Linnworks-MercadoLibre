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
		]);

		if ($validator->fails()) {
			return redirect()
				->back()
				->withErrors(['bad_request' => 'Bad request, missing token, please contact support.']);
		}

		$LinnworkUserService = new UserService();
		$LinnworkUser = $LinnworkUserService->AuthorizeByApplication($request->get('token'), false);
		
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
				$LinnworkUser->update(['login_status' => false]);

				return view('disconnected');
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
			return view('uninstalled', compact('errMessage', 'successMessage'));
		}

		$LinnworkUserService = new UserService();
		$LinnworkUser = $LinnworkUserService->AuthorizeByApplication($token);

		if (!$LinnworkUser) {
			$errMessage = 'Invalid token provided in the request.';
			return view('uninstalled', compact('errMessage', 'successMessage'));
		}

		try {
			LinnworkOrder::where('user_id', $LinnworkUser->user_id)->delete();
			$LinnworkUser->delete();
			$successMessage = 'Application uninstalled successfully.';
		} catch (QueryException $e) {
			$errMessage = 'Unable to uninstall, please contact support.';
			self::log($e, 'error', __CLASS__, __FUNCTION__);
		} catch (\Exception $e) {
			$errMessage = $this->getMessage();
			self::log($e, 'error', __CLASS__, __FUNCTION__);
		}

		return view('uninstalled', compact('errMessage', 'successMessage'));
	}
}
