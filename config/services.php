<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
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

    // regras.md RN-004 — destino dos pedidos de demonstração da página pública
    'comercial' => [
        'email' => env('COMERCIAL_EMAIL', 'comercial@contab.test'),
    ],

    // regras.md RN-579 — leitura de documentos por IA no formulário de admissão.
    // Sem chave, a leitura fica desligada e o formulário funciona só com preenchimento manual.
    'anthropic' => [
        'key' => env('ANTHROPIC_API_KEY'),
        'modelo' => env('ANTHROPIC_MODELO', 'claude-opus-5-5'),
    ],

];
