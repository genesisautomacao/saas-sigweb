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

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    // Login social do cidadão (app de Chamados). Opcionais: se preenchidos, o backend
    // exige que o token recebido pertença a este app (checagem do 'aud' do Google).
    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
    ],

    'facebook' => [
        'client_id' => env('FACEBOOK_CLIENT_ID'),
        'client_secret' => env('FACEBOOK_CLIENT_SECRET'),
    ],

    // Basemaps do mapa (Azure Road/Satélite) — a chave cadastrada no /admin
    // (ApiSetting "Azure Maps") SOBRESCREVE este fallback do .env no boot.
    'azure_maps' => [
        'key' => env('AZURE_MAPS_KEY'),
    ],

    // Street View / visualizador 360 + perfil altimétrico (Elevation API).
    // Idem: ApiSetting "Google Maps" sobrescreve no boot.
    'google_maps' => [
        'key' => env('GOOGLE_MAPS_API_KEY'),
    ],

];
