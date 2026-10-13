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
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    /*
     * La API de Voluntariados Chile (oportunidades del Día del Patrimonio
     * Social). La sincronización de /voluntariados lee la clave del .env
     * (`VCH_API_KEY`). La pantalla de prueba del panel sigue pidiéndola en
     * pantalla y no la guarda en ningún sitio.
     */
    'voluntariados_chile' => [
        'url' => env('VCH_API_URL', 'https://wepgoxkyujickiwvldrs.supabase.co/functions/v1/patrimonio-social-opportunities'),
        // La que usa la sincronización de la sección de voluntariados. Sólo en
        // el .env de cada servidor, nunca en el repositorio. El probador del
        // panel no la usa: pide la suya en pantalla.
        'key' => env('VCH_API_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
