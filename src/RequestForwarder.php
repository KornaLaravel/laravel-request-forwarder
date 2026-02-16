<?php

namespace Moneo\RequestForwarder;

use Illuminate\Http\Client\Factory;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Moneo\RequestForwarder\Events\WebhookFailed;
use Moneo\RequestForwarder\Events\WebhookSent;
use Moneo\RequestForwarder\Exceptions\WebhookGroupNameNotFoundException;
use Moneo\RequestForwarder\Providers\DefaultProvider;
use Moneo\RequestForwarder\Providers\ProviderInterface;

class RequestForwarder
{
    public function __construct(
        private readonly Factory $client,
        private readonly array $webhooks = [],
    ) {}

    public function sendAsync(Request $request, ?string $webhookGroupName = null): void
    {
        /** @var class-string<ProcessRequestForwarder> $queueClass */
        $queueClass = config('request-forwarder.queue_class', ProcessRequestForwarder::class);

        $payload = $request->all();
        if (! empty($request->query())) {
            $payload['_query'] = $request->query();
        }

        $dispatched = $queueClass::dispatch($request->url(), $payload, $webhookGroupName);

        $queueName = config('request-forwarder.queue_name');
        if (is_string($queueName) && trim($queueName) !== '') {
            $dispatched->onQueue($queueName);
        }
    }

    /**
     * @throws WebhookGroupNameNotFoundException
     */
    public function triggerHooks(string $url, array $params, ?string $webhookGroupName = null): void
    {
        foreach ($this->getWebhookTargets($webhookGroupName) as $webhook) {
            try {
                $providerClass = $this->resolveProviderClass($webhook);

                /** @var ProviderInterface $provider */
                $provider = app()->make($providerClass, ['client' => $this->client]);
                $response = $provider->send($url, $params, $webhook);

                event(new WebhookSent($url, (string) Arr::get($webhook, 'url', 'unknown'), $response->status()));
            } catch (\Throwable $e) {
                if (config('request-forwarder.log_failures', true)) {
                    Log::error('Request Forwarder: Failed to forward webhook', [
                        'url' => $url,
                        'target' => $webhook['url'] ?? 'unknown',
                        'error' => $e->getMessage(),
                    ]);
                }

                event(new WebhookFailed($url, $webhook['url'] ?? 'unknown', $e));
            }
        }
    }

    /**
     * @throws WebhookGroupNameNotFoundException
     */
    private function getWebhookInfo(?string $webhookGroupName = null): array
    {
        if ($webhookGroupName === null || trim($webhookGroupName) === '') {
            $webhookGroupName = config('request-forwarder.default_webhook_group_name');
        }

        $webhooks = config('request-forwarder.webhooks', $this->webhooks);

        return $webhooks[$webhookGroupName] ?? throw new WebhookGroupNameNotFoundException(
            "Webhook group name '{$webhookGroupName}' is not defined in the config file."
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     *
     * @throws WebhookGroupNameNotFoundException
     */
    private function getWebhookTargets(?string $webhookGroupName = null): array
    {
        $webhookInfo = $this->getWebhookInfo($webhookGroupName);
        $targets = Arr::get($webhookInfo, 'targets');

        if (! is_array($targets)) {
            throw new \InvalidArgumentException("Webhook group '{$webhookGroupName}' must define a valid 'targets' array.");
        }

        if ($targets === []) {
            throw new \InvalidArgumentException("Webhook group '{$webhookGroupName}' has no webhook targets.");
        }

        return $targets;
    }

    /**
     * @param  array<string, mixed>  $webhook
     * @return class-string<ProviderInterface>
     */
    private function resolveProviderClass(array $webhook): string
    {
        $providerClass = Arr::get($webhook, 'provider', DefaultProvider::class);

        if (! is_string($providerClass) || $providerClass === '') {
            throw new \InvalidArgumentException('Provider class must be a non-empty string.');
        }

        if (! class_exists($providerClass)) {
            throw new \InvalidArgumentException("Provider class '{$providerClass}' does not exist.");
        }

        if (! is_subclass_of($providerClass, ProviderInterface::class)) {
            throw new \InvalidArgumentException("Provider class '{$providerClass}' must implement ".ProviderInterface::class.'.');
        }

        return $providerClass;
    }
}
