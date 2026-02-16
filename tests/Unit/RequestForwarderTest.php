<?php

use Illuminate\Http\Client\Factory;
use Illuminate\Http\Request as HttpRequest;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Moneo\RequestForwarder\Events\WebhookFailed;
use Moneo\RequestForwarder\Events\WebhookSent;
use Moneo\RequestForwarder\Exceptions\WebhookGroupNameNotFoundException;
use Moneo\RequestForwarder\ProcessRequestForwarder;
use Moneo\RequestForwarder\RequestForwarder;

it('triggers hooks for the default webhook group', function () {
    Http::fake();
    Event::fake();

    $forwarder = app(RequestForwarder::class);
    $forwarder->triggerHooks('https://source.test/webhook', ['key' => 'value']);

    Http::assertSentCount(1);
    Event::assertDispatched(WebhookSent::class);
});

it('triggers hooks for a named webhook group', function () {
    Http::fake();
    Event::fake();

    config()->set('request-forwarder.webhooks.custom-group', [
        'targets' => [
            ['url' => 'https://target-a.test/hook', 'method' => 'POST'],
            ['url' => 'https://target-b.test/hook', 'method' => 'PUT'],
        ],
    ]);

    $forwarder = new RequestForwarder(
        app(Factory::class),
        config('request-forwarder.webhooks'),
    );

    $forwarder->triggerHooks('https://source.test', ['data' => 1], 'custom-group');

    Http::assertSentCount(2);
    Event::assertDispatched(WebhookSent::class, 2);
});

it('throws exception for undefined webhook group name', function () {
    $forwarder = app(RequestForwarder::class);
    $forwarder->triggerHooks('https://source.test', [], 'non-existent-group');
})->throws(WebhookGroupNameNotFoundException::class);

it('dispatches queue job with payload when sendAsync is called', function () {
    Queue::fake();
    $request = HttpRequest::create('https://source.test/webhook?foo=bar', 'POST', ['name' => 'john']);

    $forwarder = app(RequestForwarder::class);
    $forwarder->sendAsync($request);

    Queue::assertPushed(ProcessRequestForwarder::class, function (ProcessRequestForwarder $job) {
        return $job->url === 'https://source.test/webhook'
            && $job->params['name'] === 'john'
            && $job->params['_query']['foo'] === 'bar'
            && $job->webhookName === null;
    });
});

it('dispatches queue job to custom queue when configured', function () {
    Queue::fake();
    config()->set('request-forwarder.queue_name', 'webhooks');

    $request = HttpRequest::create('https://source.test/webhook', 'POST', ['ok' => true]);
    app(RequestForwarder::class)->sendAsync($request, 'custom-group');

    Queue::assertPushed(ProcessRequestForwarder::class, function (ProcessRequestForwarder $job) {
        return $job->queue === 'webhooks' && $job->webhookName === 'custom-group';
    });
});

it('uses default group name when null is passed', function () {
    Http::fake();
    Event::fake();

    $forwarder = app(RequestForwarder::class);
    $forwarder->triggerHooks('https://source.test', ['foo' => 'bar'], null);

    Http::assertSentCount(1);
});

it('uses default group name when empty string is passed', function () {
    Http::fake();
    Event::fake();

    $forwarder = app(RequestForwarder::class);
    $forwarder->triggerHooks('https://source.test', ['foo' => 'bar'], '');

    Http::assertSentCount(1);
});

it('dispatches WebhookFailed event when a provider throws an exception', function () {
    Http::fake(fn () => throw new \Exception('Connection refused'));
    Event::fake();

    $forwarder = app(RequestForwarder::class);
    $forwarder->triggerHooks('https://source.test', ['data' => 'test']);

    Event::assertDispatched(WebhookFailed::class, function (WebhookFailed $event) {
        return $event->exception->getMessage() === 'Connection refused';
    });
    Event::assertNotDispatched(WebhookSent::class);
});

it('dispatches failed event when provider class does not exist', function () {
    Event::fake();
    Http::fake();
    config()->set('request-forwarder.webhooks.invalid-provider', [
        'targets' => [
            [
                'url' => 'https://target.test/hook',
                'provider' => 'App\\Invalid\\Provider',
            ],
        ],
    ]);

    app(RequestForwarder::class)->triggerHooks('https://source.test', ['foo' => 'bar'], 'invalid-provider');

    Event::assertDispatched(WebhookFailed::class);
});

it('continues processing remaining targets when one fails', function () {
    $callCount = 0;
    Http::fake(function () use (&$callCount) {
        $callCount++;
        if ($callCount === 1) {
            throw new \Exception('First target failed');
        }

        return Http::response('ok', 200);
    });
    Event::fake();

    config()->set('request-forwarder.webhooks.multi', [
        'targets' => [
            ['url' => 'https://target-a.test/hook', 'method' => 'POST'],
            ['url' => 'https://target-b.test/hook', 'method' => 'POST'],
        ],
    ]);

    $forwarder = new RequestForwarder(
        app(Factory::class),
        config('request-forwarder.webhooks'),
    );

    $forwarder->triggerHooks('https://source.test', [], 'multi');

    Event::assertDispatched(WebhookFailed::class, 1);
    Event::assertDispatched(WebhookSent::class, 1);
});

it('logs failures when log_failures is enabled', function () {
    Http::fake(fn () => throw new \Exception('Network error'));
    Event::fake();
    config()->set('request-forwarder.log_failures', true);

    Log::spy();

    $forwarder = app(RequestForwarder::class);
    $forwarder->triggerHooks('https://source.test', []);

    Log::shouldHaveReceived('error')
        ->once()
        ->withArgs(function ($message, $context) {
            return str_contains($message, 'Failed to forward webhook')
                && $context['error'] === 'Network error';
        });
});

it('does not log failures when log_failures is disabled', function () {
    Http::fake(fn () => throw new \Exception('Network error'));
    Event::fake();
    config()->set('request-forwarder.log_failures', false);

    Log::spy();

    $forwarder = app(RequestForwarder::class);
    $forwarder->triggerHooks('https://source.test', []);

    Log::shouldNotHaveReceived('error');
});

it('accepts whitespace webhook group and falls back to default', function () {
    Http::fake();
    Event::fake();

    app(RequestForwarder::class)->triggerHooks('https://source.test', ['x' => 1], '   ');

    Http::assertSentCount(1);
    Event::assertDispatched(WebhookSent::class);
});

it('throws when webhook group has no targets key', function () {
    config()->set('request-forwarder.webhooks.missing-targets', []);

    app(RequestForwarder::class)->triggerHooks('https://source.test', [], 'missing-targets');
})->throws(\InvalidArgumentException::class);

it('throws when webhook group has empty targets', function () {
    config()->set('request-forwarder.webhooks.empty-targets', ['targets' => []]);

    app(RequestForwarder::class)->triggerHooks('https://source.test', [], 'empty-targets');
})->throws(\InvalidArgumentException::class);

it('dispatches failed event when target url is missing', function () {
    Event::fake();
    Http::fake();
    config()->set('request-forwarder.webhooks.missing-url', [
        'targets' => [
            ['method' => 'POST'],
        ],
    ]);

    app(RequestForwarder::class)->triggerHooks('https://source.test', ['a' => 1], 'missing-url');

    Event::assertDispatched(WebhookFailed::class);
    Event::assertNotDispatched(WebhookSent::class);
});

it('dispatches sent event for non-2xx response status', function () {
    Event::fake();
    Http::fake(['*' => Http::response('bad', 400)]);

    app(RequestForwarder::class)->triggerHooks('https://source.test', ['a' => 1]);

    Event::assertDispatched(WebhookSent::class, function (WebhookSent $event) {
        return $event->statusCode === 400;
    });
});
