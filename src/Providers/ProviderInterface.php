<?php

namespace Moneo\RequestForwarder\Providers;

use Illuminate\Http\Client\Response;

interface ProviderInterface
{
    /**
     * Send the forwarded request to the target webhook.
     *
     * @param  string  $url  The original request URL.
     * @param  array<string, mixed>  $params  The original request parameters.
     * @param  array<string, mixed>  $webhook  The webhook target configuration.
     */
    public function send(string $url, array $params, array $webhook): Response;
}
