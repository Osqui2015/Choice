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

    'whatsapp' => [
        // Número del admin (Oscar) en formato internacional sin '+' ni espacios.
        // Se usa para armar el link wa.me/{numero}?text={mensaje} que el user
        // abre para pedir un plan.
        'premium_number'  => env('WHATSAPP_PREMIUM_NUMBER', '5491100000000'),
        'premium_message' => env(
            'WHATSAPP_PREMIUM_MESSAGE',
            'Hola! Quiero activar mi suscripcion en Choice.'
        ),
    ],

];
