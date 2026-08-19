<?php

namespace App\Services;

use App\Models\LinnworkUser;
use App\Traits\ConsumeService;
use App\Traits\CustomLogger;
use Carbon\Carbon;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Database\QueryException;

class UserService
{
    use ConsumeService, CustomLogger;

	protected $baseUri;

	/**
	 * Authorize by Application
	 * 
	 * @param string $token
	 * @param string $klaviyoToken
	 */
	public function AuthorizeByApplication(
		string $token, 
		string $klaviyoToken = '', 
		bool $validateOnly = true,
		string $klaviyoAccessToken = '',
		int $klaviyoExpiresIn = 0,
		string $klaviyoRefreshToken = '',
		string $applicationId = '',
		string $applicationSecret = '',
	)
	{
		$this->baseUri = env('LINNWORKS_API_URL', '');

		try {
			$userData = json_decode($this->request(
				'POST',
				'Auth/AuthorizeByApplication',
				[
					'ApplicationId' => $applicationId ?: env('LINNWORKS_APP_ID', ''),
					'ApplicationSecret' => $applicationSecret ?: env('LINNWORKS_APP_SECRET', ''),
					'Token' => $token
				],
				[
					'Accept' => 'application/json',
					'Connection' => 'keep-alive',
					'Content-Type' => 'application/x-www-form-urlencoded; charset=UTF-8',
					'Accept-Encoding' => 'gzip, deflate',
				],
				true
			), true);

			$user = LinnworkUser::where('email', $userData['Email'])->first();

			if ($user) {
				$updateData = ['token' => $userData['Token'], 'aplication_token' => $token];

				if ($klaviyoToken) {
					$updateData['klaviyo_token'] = $klaviyoToken;
				}

				if ($klaviyoAccessToken) {
					$updateData['klaviyo_token'] = '';
					$updateData['access_token'] = $klaviyoAccessToken;
					$updateData['expires_in'] = Carbon::now()->addSeconds($klaviyoExpiresIn - 60);
					$updateData['refresh_token'] = $klaviyoRefreshToken;
				}

				$user->update($updateData);
			}

			if ($validateOnly) {
				return $user;
			}

			$userData = array_merge_recursive(
				['_custom' => [
					'ApplicationToken' => $token, 
					'KlaviyoToken' => $klaviyoToken ?? '',
					'access_token' => $klaviyoAccessToken ?? '',
					'expires_in' => $klaviyoExpiresIn ?? 0,
					'refresh_token' => $klaviyoRefreshToken ?? '',
				]],
				$userData
			);

			return $this->_saveUserData($userData);
		} catch (RequestException $e) {
			self::log($e, 'error', __CLASS__, __FUNCTION__);
			return false;
		}
	}

	/**
	 * Save User Data
	 * 
	 * @param array $userData Json output from Linnworks API
	 * 
	 * @return array $user
	 */
	private function _saveUserData(array $userData)
	{
		try {
			$validatedData = [
				'customer_id'     => $userData['CustomerId'],
				'user_id'         => $userData['UserId'],
				'expiration_date' => Carbon::parse($userData['ExpirationDate']),
				'email'           => $userData['Email'],
				'login_status'    => true,
				'push_server'     => $userData['PushServer'],
				'server'          => $userData['Server'],
				'token'           => $userData['Token']
			];

			if (isset($userData['_custom']['ApplicationToken'])) {
				$validatedData['aplication_token'] = $userData['_custom']['ApplicationToken'];
			}

			$validatedData['klaviyo_token'] = $userData['_custom']['KlaviyoToken'] ?? '';

			if (isset($userData['_custom']['access_token']) && $userData['_custom']['access_token']) {
				$validatedData['access_token'] = $userData['_custom']['access_token'];
				$validatedData['expires_in'] = Carbon::now()->addSeconds($userData['_custom']['expires_in'] - 60);
				$validatedData['refresh_token'] = $userData['_custom']['refresh_token'];
			}

			unset($userData['_custom']);

			$validatedData['response_obj'] = $userData;

			return LinnworkUser::updateOrCreate(
				['user_id' => $userData['UserId']],
				$validatedData
			);
		} catch(QueryException $e) {
			self::log($e, 'error', __CLASS__, __FUNCTION__);
			return false;
		}
	}
}
