<?php

namespace Moneo\RequestForwarder\Providers;

use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Response;

class Discord implements ProviderInterface
{
    public function __construct(
        private readonly Factory $client,
    ) {}

    /**
     * @throws \Exception
     */
    public function send(string $url, array $params, array $webhook): Response
    {
        $targetUrl = $this->resolveTargetUrl($webhook);
        $timeout = $this->resolveTimeout($webhook);

        $content = $url.PHP_EOL;
        try {
            $content .= json_encode($params, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new \InvalidArgumentException('Unable to encode webhook payload for Discord provider.', 0, $exception);
        }

        return $this->client
            ->timeout($timeout)
            ->send('POST', $targetUrl, [
                'json' => ['content' => $content],
            ]);
    }

    /**
     * @param  array<string, mixed>  $webhook
     */
    private function resolveTargetUrl(array $webhook): string
    {
        $targetUrl = $webhook['url'] ?? null;
        if (! is_string($targetUrl) || trim($targetUrl) === '' || filter_var($targetUrl, FILTER_VALIDATE_URL) === false) {
            throw new \InvalidArgumentException('Discord webhook target url must be a valid URL.');
        }

        return $targetUrl;
    }

    /**
     * @param  array<string, mixed>  $webhook
     */
    private function resolveTimeout(array $webhook): float
    {
        $timeout = $webhook['timeout'] ?? config('request-forwarder.timeout', 30);

        if (! is_numeric($timeout) || (float) $timeout <= 0) {
            throw new \InvalidArgumentException('Discord webhook timeout must be a positive number.');
        }

        return (float) $timeout;
    }
}
