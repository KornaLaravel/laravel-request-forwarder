<?php

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Moneo\RequestForwarder\Events\WebhookSent;
use Moneo\RequestForwarder\ProcessRequestForwarder;

it('dispatches the job to the configured queue', function () {
    Queue::fake();

    ProcessRequestForwarder::dispatch('https://source.test', ['key' => 'value'], 'default')
        ->onQueue(config('request-forwarder.queue_name'));

    Queue::assertPushed(ProcessRequestForwarder::class, function ($job) {
        return $job->url === 'https://source.test'
            && $job->params === ['key' => 'value']
            && $job->webhookName === 'default';
    });
});

it('executes triggerHooks when the job is handled', function () {
    Http::fake();
    Event::fake();

    $job = new ProcessRequestForwarder('https://source.test', ['data' => 'test']);
    $job->handle(app(\Moneo\RequestForwarder\RequestForwarder::class));

    Http::assertSentCount(1);
    Event::assertDispatched(WebhookSent::class);
});

it('reads retry configuration from config', function () {
    config()->set('request-forwarder.tries', 5);
    config()->set('request-forwarder.backoff', [10, 20, 30]);

    $job = new ProcessRequestForwarder('https://source.test', []);

    expect($job->tries)->toBe(5)
        ->and($job->backoff)->toBe([10, 20, 30]);
});

it('stores url, params, and webhookName correctly', function () {
    $job = new ProcessRequestForwarder(
        'https://source.test/endpoint',
        ['foo' => 'bar', 'nested' => ['a' => 1]],
        'my-group',
    );

    expect($job->url)->toBe('https://source.test/endpoint')
        ->and($job->params)->toBe(['foo' => 'bar', 'nested' => ['a' => 1]])
        ->and($job->webhookName)->toBe('my-group');
});

it('defaults webhookName to null when not provided', function () {
    $job = new ProcessRequestForwarder('https://source.test', []);

    expect($job->webhookName)->toBeNull();
});

it('logs failed job when log_failures is enabled', function () {
    config()->set('request-forwarder.log_failures', true);
    Log::shouldReceive('error')
        ->once()
        ->with('Request Forwarder: Queue job failed permanently', [
            'url' => 'https://source.test',
            'webhook_name' => 'demo',
            'error' => 'queue failed',
        ]);

    $job = new ProcessRequestForwarder('https://source.test', [], 'demo');
    $job->failed(new \RuntimeException('queue failed'));
});

it('does not log failed job when log_failures is disabled', function () {
    config()->set('request-forwarder.log_failures', false);
    Log::shouldReceive('error')->never();

    $job = new ProcessRequestForwarder('https://source.test', [], 'demo');
    $job->failed(new \RuntimeException('queue failed'));
});

it('handles null throwable in failed job logging', function () {
    config()->set('request-forwarder.log_failures', true);
    Log::shouldReceive('error')
        ->once()
        ->with('Request Forwarder: Queue job failed permanently', [
            'url' => 'https://source.test',
            'webhook_name' => null,
            'error' => null,
        ]);

    $job = new ProcessRequestForwarder('https://source.test', []);
    $job->failed(null);
});
