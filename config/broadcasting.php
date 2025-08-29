<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Broadcaster
    |--------------------------------------------------------------------------
    |
    | This option controls the default broadcaster that will be used by the
    | framework when an event needs to be broadcast. You may set this to
    | any of the connections defined in the "connections" array below.
    |
    | Supported: "pusher", "ably", "redis", "log", "null"
    |
    */

    'default' => env('BROADCAST_DRIVER', 'null'),

    /*
    |--------------------------------------------------------------------------
    | Broadcast Connections
    |--------------------------------------------------------------------------
    |
    | Here you may define all of the broadcast connections that will be used
    | to broadcast events to other systems or over websockets. Samples of
    | each available type of connection are provided inside this array.
    |
    */

    'connections' => [

        'pusher' => [
            'driver'  => 'pusher',
            'key'     => env('PUSHER_APP_KEY'),
            'secret'  => env('PUSHER_APP_SECRET'),
            'app_id'  => env('PUSHER_APP_ID'),
            'options' => [

                // For Pusher Cloud, set your cluster and keep host/port unset.
                'cluster' => env('PUSHER_APP_CLUSTER', 'mt1'),
                'useTLS'  => filter_var(env('PUSHER_USE_TLS', true), FILTER_VALIDATE_BOOLEAN),
                'encrypted' => filter_var(env('PUSHER_ENCRYPTED', true), FILTER_VALIDATE_BOOLEAN),

                // For local laravel-websockets, set these (and BROADCAST_DRIVER=pusher):
                // Example:
                // PUSHER_HOST=127.0.0.1
                // PUSHER_PORT=6001
                // PUSHER_SCHEME=http
                //'host'   => env('PUSHER_HOST'),
                //'port'   => env('PUSHER_PORT'),
              //  'scheme' => env('PUSHER_SCHEME', 'https'),

                // If using TLS with self-signed certs (websockets server), you can control verification
                // via client_options below (curl_options for the server-side PHP pusher client).
            ],

            // Extra options for the Pusher PHP server client (used when your app broadcasts).
            // See: https://github.com/pusher/pusher-http-php#ssl-settings
            'client_options' => [
                // Example to disable SSL verification locally (NOT recommended for production):
                // 'verify' => false,
                //
                // If you must pass custom cURL opts:
                // 'curl_options' => [
                //     CURLOPT_SSL_VERIFYHOST => 0,
                //     CURLOPT_SSL_VERIFYPEER => 0,
                // ],
            ],
        ],

        'redis' => [
            'driver'     => 'redis',
            'connection' => env('BROADCAST_REDIS_CONNECTION', 'default'),
        ],

        'log' => [
            'driver' => 'log',
        ],

        'null' => [
            'driver' => 'null',
        ],
    ],

];
