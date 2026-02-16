<?php

namespace Moneo\RequestForwarder\Providers;

use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Response;

class DefaultProvider implements ProviderInterface
{
    /** @var array<int, string> */
    private const ALLOWED_METHODS = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'HEAD', 'OPTIONS'];

    public function __construct(
        private readonly Factory $client,
    ) {}

    /**
     * @throws \Exception
     */
    public function send(string $url, array $params, array $webhook): Response
    {
        $targetUrl = $this->resolveTargetUrl($webhook);
        $method = $this->resolveHttpMethod($webhook);
        $timeout = $this->resolveTimeout($webhook);

        $options = [];

        if (! empty($params)) {
            $options['json'] = $params;
        }

        $headers = $this->resolveHeaders($webhook);
        if ($headers !== []) {
            $options['headers'] = $headers;
        }

        return $this->client
            ->timeout($timeout)
            ->send($method, $targetUrl, $options);
    }

    /**
     * @param  array<string, mixed>  $webhook
     */
    private function resolveTargetUrl(array $webhook): string
    {
        $targetUrl = $webhook['url'] ?? null;
        if (! is_string($targetUrl) || trim($targetUrl) === '' || filter_var($targetUrl, FILTER_VALIDATE_URL) === false) {
            throw new \InvalidArgumentException('Webhook target url must be a valid URL.');
        }

        return $targetUrl;
    }

    /**
     * @param  array<string, mixed>  $webhook
     */
    private function resolveHttpMethod(array $webhook): string
    {
        $method = strtoupper((string) ($webhook['method'] ?? 'POST'));
        if (! in_array($method, self::ALLOWED_METHODS, true)) {
            throw new \InvalidArgumentException("Webhook method '{$method}' is not supported.");
        }

        return $method;
    }

    /**
     * @param  array<string, mixed>  $webhook
     */
    private function resolveTimeout(array $webhook): float
    {
        $timeout = $webhook['timeout'] ?? config('request-forwarder.timeout', 30);

        if (! is_numeric($timeout) || (float) $timeout <= 0) {
            throw new \InvalidArgumentException('Webhook timeout must be a positive number.');
        }

        return (float) $timeout;
    }

    /**
     * @param  array<string, mixed>  $webhook
     * @return array<string, string>
     */
    private function resolveHeaders(array $webhook): array
    {
        if (! array_key_exists('headers', $webhook) || $webhook['headers'] === null) {
            return [];
        }

        if (! is_array($webhook['headers'])) {
            throw new \InvalidArgumentException('Webhook headers must be an array.');
        }

        $headers = [];
        foreach ($webhook['headers'] as $key => $value) {
            if (! is_string($key) || trim($key) === '') {
                throw new \InvalidArgumentException('Webhook header names must be non-empty strings.');
            }

            if (! is_scalar($value)) {
                throw new \InvalidArgumentException("Webhook header '{$key}' must be a scalar value.");
            }

            $headers[$key] = (string) $value;
        }

        return $headers;
    }
}
