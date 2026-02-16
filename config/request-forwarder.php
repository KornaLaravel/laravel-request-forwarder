<?php

// config for Moneo/RequestForwarder
return [

    /*
    |--------------------------------------------------------------------------
    | Default Webhook Group Name
    |--------------------------------------------------------------------------
    |
    | Decides which webhook group to use if no group name is specified
    | when using the middleware.
    |
    */

    'default_webhook_group_name' => 'default',

    /*
    |--------------------------------------------------------------------------
    | Webhook Groups
    |--------------------------------------------------------------------------
    |
    | Define your webhook groups here. Each group can have multiple targets.
    | Each target requires a 'url' and 'method'. Optionally, you can specify
    | a custom 'provider' class, 'headers', and 'timeout' per target.
    |
    */

    'webhooks' => [
        'default' => [
            'targets' => [
                [
                    'url' => env('REQUEST_FORWARDER_DEFAULT_URL', 'https://example.com/webhook'),
                    'method' => 'POST',
                ],
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | HTTP Timeout
    |--------------------------------------------------------------------------
    |
    | The default timeout (in seconds) for outgoing HTTP requests.
    | Can be overridden per target in the webhook configuration.
    |
    */

    'timeout' => 30,

    /*
    |--------------------------------------------------------------------------
    | Queue Configuration
    |--------------------------------------------------------------------------
    |
    | Configure the queue name and job class used for async forwarding.
    | You can specify a custom job class that extends ProcessRequestForwarder.
    |
    */

    'queue_name' => env('REQUEST_FORWARDER_QUEUE', ''),

    'queue_class' => Moneo\RequestForwarder\ProcessRequestForwarder::class,

    /*
    |--------------------------------------------------------------------------
    | Queue Retry Configuration
    |--------------------------------------------------------------------------
    |
    | Configure how many times a failed webhook delivery should be retried
    | and the backoff strategy (in seconds) between retries.
    |
    */

    'tries' => 3,

    'backoff' => [5, 30, 60],

    /*
    |--------------------------------------------------------------------------
    | Logging
    |--------------------------------------------------------------------------
    |
    | When enabled, failed webhook deliveries will be logged.
    |
    */

    'log_failures' => true,

];
