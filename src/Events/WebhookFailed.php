<?php

namespace Moneo\RequestForwarder\Events;

use Illuminate\Foundation\Events\Dispatchable;

class WebhookFailed
{
    use Dispatchable;

    public function __construct(
        public readonly string $sourceUrl,
        public readonly string $targetUrl,
        public readonly \Throwable $exception,
    ) {}
}
