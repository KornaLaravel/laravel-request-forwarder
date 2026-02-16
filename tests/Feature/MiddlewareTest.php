<?php

use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Moneo\RequestForwarder\Events\WebhookSent;
use Moneo\RequestForwarder\Exceptions\WebhookGroupNameNotFoundException;
use Moneo\RequestForwarder\RequestForwarder;

use function Pest\Laravel\delete;
use function Pest\Laravel\get;
use function Pest\Laravel\patch;
use function Pest\Laravel\post;
use function Pest\Laravel\put;

it('does not forward requests on routes without the middleware', function () {
    Http::fake();

    get('/')->assertStatus(200);
    post('/')->assertStatus(200);

    Http::assertNothingSent();
});

it('forwards requests on routes with the middleware', function () {
    Http::fake();
    Event::fake();

    get('/middleware')->assertStatus(200);
    Http::assertSentCount(1);

    Event::assertDispatched(WebhookSent::class);
});

it('forwards POST request data through the middleware', function () {
    Http::fake();
    Event::fake();

    post('/middleware', ['name' => 'test', 'amount' => 100])->assertStatus(200);

    Http::assertSent(function ($request) {
        return $request['name'] === 'test' && $request['amount'] === 100;
    });
});

it('accumulates forwarded requests across multiple calls', function () {
    Http::fake();

    get('/middleware')->assertStatus(200);
    Http::assertSentCount(1);

    get('/middleware')->assertStatus(200);
    Http::assertSentCount(2);
});

it('forwards to a custom webhook group when specified in middleware parameter', function () {
    Http::fake();
    Event::fake();

    config()->set('request-forwarder.webhooks.custom-group', [
        'targets' => [
            ['url' => 'https://custom-target.test/hook', 'method' => 'POST'],
        ],
    ]);

    app()->singleton(RequestForwarder::class, function ($app) {
        return new RequestForwarder(
            $app->make(Factory::class),
            config('request-forwarder.webhooks'),
        );
    });

    get('/custom-group')->assertStatus(200);

    Http::assertSent(function ($request) {
        return $request->url() === 'https://custom-target.test/hook';
    });
});

it('throws WebhookGroupNameNotFoundException for an invalid group name', function () {
    Http::fake();

    $response = get('/wrong-webhook-group-name', ['Accept' => 'application/json']);

    $response->assertStatus(500);

    expect($response->json('exception'))->toBe(WebhookGroupNameNotFoundException::class);
});

it('returns the original response even after forwarding', function () {
    Http::fake();

    $response = get('/middleware');

    $response->assertStatus(200);
    $response->assertSee('With Middleware');
});

it('forwards PUT request data through middleware', function () {
    Http::fake();

    put('/middleware', ['status' => 'updated'])->assertStatus(200);

    Http::assertSent(function ($request) {
        return $request->method() === 'POST'
            && $request['status'] === 'updated';
    });
});

it('forwards PATCH request data through middleware', function () {
    Http::fake();

    patch('/middleware', ['active' => false])->assertStatus(200);

    Http::assertSent(function ($request) {
        return $request['active'] === false;
    });
});

it('forwards DELETE request through middleware', function () {
    Http::fake();

    delete('/middleware')->assertStatus(200);

    Http::assertSentCount(1);
});

it('includes query string in forwarded payload metadata', function () {
    Http::fake();

    get('/middleware?sort=desc&limit=10')->assertStatus(200);

    Http::assertSent(function ($request) {
        return isset($request['_query'])
            && $request['_query']['sort'] === 'desc'
            && $request['_query']['limit'] === '10';
    });
});
