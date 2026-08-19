<?php

namespace App\Http\Controllers;

use App\Models\LinnworkUser;
use App\Services\UserService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;


class KlaviyoOAuthController extends Controller
{
    // Endpoint para iniciar el flujo OAuth2 con PKCE
    public function redirectToKlaviyo(Request $request)
    {
        $linnworksToken = $request->get('token');
        if (!$linnworksToken) {
            return response('Token de Linnworks requerido. Abre la app con ?token=... en la URL.', 400);
        }

        $clientId = config('services.klaviyo.client_id');
        $redirectUri = config('services.klaviyo.redirect');
        $state = bin2hex(random_bytes(16));
        $codeVerifier = bin2hex(random_bytes(64));
        $codeChallenge = rtrim(strtr(base64_encode(hash('sha256', $codeVerifier, true)), '+/', '-_'), '=');

        // Cache (no sesión): el popup pierde la cookie al volver desde Klaviyo
        Cache::put('klaviyo_oauth:'.$state, [
            'code_verifier' => $codeVerifier,
            'linnworks_token' => $linnworksToken,
        ], now()->addMinutes(15));

        $authUrl = 'https://www.klaviyo.com/oauth/authorize?'.http_build_query([
            'response_type' => 'code',
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'state' => $state,
            'code_challenge' => $codeChallenge,
            'code_challenge_method' => 'S256',
            // 'scope' => 'accounts:read events:read events:write lists:read lists:write tags:read tags:write flows:read profiles:read profiles:write subscriptions:read subscriptions:write metrics:read metrics:write',
            'scope' => 'accounts:read events:write lists:write profiles:write subscriptions:write metrics:write',
        ]);

        return redirect()->away($authUrl);
    }

    // Endpoint para recibir el callback y mostrar el access token
    public function handleCallback(Request $request, UserService $userService)
    {
        $state = $request->get('state');
        if (!$state) {
            return response('Estado OAuth inválido.', 400);
        }

        $oauthData = Cache::pull('klaviyo_oauth:'.$state);
        if (!$oauthData) {
            return response(
                'El flujo OAuth expiró. Cierra el popup, recarga http://localhost:9001/?token=TU_TOKEN y vuelve a Authorize.',
                400
            );
        }

        $codeVerifier = $oauthData['code_verifier'] ?? null;
        $linnworksToken = $oauthData['linnworks_token'] ?? null;

        if (!$linnworksToken) {
            return response('No se encontró el token de Linnworks. Vuelve a abrir la app con ?token=... en la URL.', 400);
        }

        $code = $request->get('code');

        // Nueva validación para permisos denegados
        if ($request->get('error') === 'access_denied') {
            return response()->view('callback', [
                'success' => false,
                'message' => 'Authorization denied. Please click "Allow" to enable the integration.',
            ]);
        }

        if (!$code) {
            return response('No se recibió el código de autorización', 400);
        }

        if (!$codeVerifier) {
            return response('Sesión OAuth expirada. Cierra el popup, recarga la página con el token de Linnworks e intenta de nuevo.', 400);
        }

        $clientId = config('services.klaviyo.client_id');
        $clientSecret = config('services.klaviyo.client_secret');
        $redirectUri = config('services.klaviyo.redirect');
        $server = 'https://a.klaviyo.com';

        $response = Http::withBasicAuth($clientId, $clientSecret)
            ->withHeaders(['User-Agent' => 'Klaviyo/1.0'])
            ->asForm()
            ->post($server . '/oauth/token', [
                'grant_type' => 'authorization_code',
                'code' => $code,
                'code_verifier' => $codeVerifier,
                'redirect_uri' => $redirectUri,
            ]);

        if ($response->failed()) {
            $arrResponse = [
                'error' => 'Error al obtener el access token de Klaviyo',
                'message' => $response->body(),         
            ];
            return response()->json($arrResponse, 400);
        }

        $data = $response->json();
        
        if (isset($data['access_token'])) {
            $linnworkUser = $userService->AuthorizeByApplication(
                $linnworksToken,
                '',
                false,
                $data['access_token'],
                $data['expires_in'] ?? 0,
                $data['refresh_token'] ?? ''
            );
            
            $response = Http::asForm()
            ->withHeaders([
                'Authorization' => 'Bearer ' . $data['access_token'],
                'revision' => '2025-04-15',
            ])
            ->get($server . '/api/accounts');

        }

        return response()->view('callback', [
            'success' => isset($data['access_token']) && !empty($data['access_token']) ? true : false,
            'message' => isset($data['access_token']) && !empty($data['access_token']) 
                ? 'Successfully authorized with Klaviyo' 
                : 'Authorization denied. Please click "Allow" to enable the integration.',
        ]);
    }

    public function refreshAccessToken(LinnworkUser $user)
    {
        if (!$user->refresh_token) {
            return false;
        }

        $clientId = config('services.klaviyo.client_id');
        $clientSecret = config('services.klaviyo.client_secret');
        $server = 'https://a.klaviyo.com';

        $response = Http::withBasicAuth($clientId, $clientSecret)
            ->asForm()
            ->post($server . '/oauth/token', [
                'grant_type' => 'refresh_token',
                'refresh_token' => $user->refresh_token,
            ]);

        if ($response->successful()) {
            $data = $response->json();
            $user->update([
                'access_token' => $data['access_token'],
                'refresh_token' => $data['refresh_token'],
                'expires_in' => Carbon::now()->addSeconds($data['expires_in'] - 60),
            ]);
            return true;
        }

        // Log error if refresh fails
        Log::error('Klaviyo refresh token failed for user: ' . $user->id, [
            'status' => $response->status(),
            'body' => $response->body(),
        ]);

        return false;
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
     * Logout y revoca el refresh_token de Klaviyo
     */
    public function logout(Request $request)
    {
        $email = $request->get('email');

        if (!$email) {
            Log::warning('Email is required for logout.');
            return redirect()->back()->withErrors(['email' => 'Email is required for logout.']);
        }

        $LinnworkUser = LinnworkUser::where('email', $email)->where('login_status', true)->first();
        if ($LinnworkUser && $LinnworkUser->refresh_token) {
            $this->revokeToken($LinnworkUser->refresh_token);
            $LinnworkUser->update(['login_status' => false, 'access_token' => null, 'refresh_token' => null]);
            return view('klaviyo.disconnected');
        } else {
            Log::warning('User not found or already logged out.');
            return redirect()->back()->withErrors(['email' => 'User not found or already logged out.']);
        }

        return redirect()->back()->withErrors(['email' => 'Logout request failed.']);
    }
}