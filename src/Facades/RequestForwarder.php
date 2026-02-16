<?php

namespace Moneo\RequestForwarder\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static void sendAsync(\Illuminate\Http\Request $request, ?string $webhookGroupName = null)
 * @method static void triggerHooks(string $url, array $params, ?string $webhookGroupName = null)
 *
 * @see \Moneo\RequestForwarder\RequestForwarder
 */
class RequestForwarder extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Moneo\RequestForwarder\RequestForwarder::class;
    }
}
