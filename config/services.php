<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'mercadolibre' => [
        'client_id' => env('MELI_CLIENT_ID'),
        'client_secret' => env('MELI_CLIENT_SECRET'),
        'redirect' => env('MELI_REDIRECT_URI'),
        'auth_host' => env('MELI_AUTH_HOST', 'https://auth.mercadolibre.com.mx'),
        'api_url' => env('MELI_API_URL', 'https://api.mercadolibre.com'),
        'linnworks_app_id' => env('MELI_LINNWORKS_APP_ID'),
        'linnworks_app_secret' => env('MELI_LINNWORKS_APP_SECRET'),
        'channel_mode' => env('MELI_CHANNEL_MODE', 'global'), // global = Global Selling (CBT); local = Mexico marketplace
        'gs_sites' => env('MELI_GS_SITES', 'MLM,MLB,MLC,MCO'),
        'gs_logistic' => env('MELI_GS_LOGISTIC', 'remote'),
    ],

];
