<?php

return [
    'default' => 'client-one',

    'clients' => [
        'client-one' => [
            'app_id'         => env('SIGN_CLIENT_ONE_APP_ID', 'app id'),
            'app_secret'     => env('SIGN_CLIENT_ONE_APP_SECRET', 'app secret'),
            'scheme'         => env('SIGN_CLIENT_ONE_SCHEME', 'http'),
            'host'           => env('SIGN_CLIENT_ONE_HOST', ''),
            'ip'             => env('SIGN_CLIENT_ONE_IP', ''),
            'port'           => env('SIGN_CLIENT_ONE_PORT', 80),
            'https_cert_pem' => env('SIGN_CLIENT_ONE_HTTPS_CERT_PEM', false),
            'enable_log'     => true,
        ],

        'another-client' => [
            'app_id'         => env('SIGN_ANOTHER_CLIENT_APP_ID', 'app id'),
            'app_secret'     => env('SIGN_ANOTHER_CLIENT_APP_SECRET', 'app secret'),
            'scheme'         => env('SIGN_ANOTHER_CLIENT_SCHEME', 'http'),
            'host'           => env('SIGN_ANOTHER_CLIENT_HOST', ''),
            'ip'             => env('SIGN_ANOTHER_CLIENT_IP', ''),
            'port'           => env('SIGN_ANOTHER_CLIENT_PORT', 80),
            'https_cert_pem' => env('SIGN_ANOTHER_CLIENT_HTTPS_CERT_PEM', false), // true , false, cert.pem path
            'enable_log'     => true,
        ],
        //... more clients
    ],

    'identity' => 'mitoop-dev-server',
];
