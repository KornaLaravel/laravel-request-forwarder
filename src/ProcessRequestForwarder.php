<?php

namespace Moneo\RequestForwarder;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessRequestForwarder implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries;

    /**
     * The number of seconds to wait before retrying the job.
     *
     * @var array<int, int>|int
     */
    public array|int $backoff;

    /**
     * Create a new job instance.
     *
     * @param  array<string, mixed>  $params
     */
    public function __construct(
        public readonly string $url,
        public readonly array $params,
        public readonly ?string $webhookName = null,
    ) {
        $this->tries = $this->resolveTries();
        $this->backoff = $this->resolveBackoff();
    }

    /**
     * Execute the job.
     */
    public function handle(RequestForwarder $requestForwarder): void
    {
        $requestForwarder->triggerHooks($this->url, $this->params, $this->webhookName);
    }

    /**
     * Handle a job failure.
     */
    public function failed(?\Throwable $exception): void
    {
        if (config('request-forwarder.log_failures', true)) {
            Log::error('Request Forwarder: Queue job failed permanently', [
                'url' => $this->url,
                'webhook_name' => $this->webhookName,
                'error' => $exception?->getMessage(),
            ]);
        }
    }

    private function resolveTries(): int
    {
        $tries = config('request-forwarder.tries', 3);
        if (! is_numeric($tries) || (int) $tries < 1) {
            return 3;
        }

        return (int) $tries;
    }

    /**
     * @return int|array<int, int>
     */
    private function resolveBackoff(): int|array
    {
        $backoff = config('request-forwarder.backoff', [5, 30, 60]);

        if (is_numeric($backoff) && (int) $backoff > 0) {
            return (int) $backoff;
        }

        if (! is_array($backoff) || $backoff === []) {
            return [5, 30, 60];
        }

        foreach ($backoff as $value) {
            if (! is_numeric($value) || (int) $value <= 0) {
                return [5, 30, 60];
            }
        }

        return array_map(static fn ($value): int => (int) $value, $backoff);
    }
}
