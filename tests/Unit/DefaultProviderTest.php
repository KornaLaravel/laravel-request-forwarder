<?php

use Illuminate\Support\Facades\Http;
use Moneo\RequestForwarder\Providers\DefaultProvider;

it('sends request body as JSON to the target URL', function () {
    Http::fake();

    $provider = new DefaultProvider(Http::getFacadeRoot());
    $provider->send('https://source.test/hook', ['name' => 'test', 'value' => 42], [
        'url' => 'https://target.test/webhook',
        'method' => 'POST',
    ]);

    Http::assertSent(function ($request) {
        return $request->url() === 'https://target.test/webhook'
            && $request->method() === 'POST'
            && $request['name'] === 'test'
            && $request['value'] === 42;
    });
});

it('sends empty body when params are empty', function () {
    Http::fake();

    $provider = new DefaultProvider(Http::getFacadeRoot());
    $provider->send('https://source.test', [], [
        'url' => 'https://target.test/webhook',
        'method' => 'POST',
    ]);

    Http::assertSent(function ($request) {
        return $request->url() === 'https://target.test/webhook'
            && $request->method() === 'POST';
    });
});

it('uses the HTTP method specified in webhook config', function () {
    Http::fake();

    $provider = new DefaultProvider(Http::getFacadeRoot());
    $provider->send('https://source.test', ['data' => true], [
        'url' => 'https://target.test/webhook',
        'method' => 'PUT',
    ]);

    Http::assertSent(function ($request) {
        return $request->method() === 'PUT';
    });
});

it('defaults to POST when no method is specified', function () {
    Http::fake();

    $provider = new DefaultProvider(Http::getFacadeRoot());
    $provider->send('https://source.test', ['data' => true], [
        'url' => 'https://target.test/webhook',
    ]);

    Http::assertSent(function ($request) {
        return $request->method() === 'POST';
    });
});

it('forwards custom headers when specified in webhook config', function () {
    Http::fake();

    $provider = new DefaultProvider(Http::getFacadeRoot());
    $provider->send('https://source.test', ['data' => true], [
        'url' => 'https://target.test/webhook',
        'method' => 'POST',
        'headers' => ['X-Custom-Header' => 'custom-value'],
    ]);

    Http::assertSent(function ($request) {
        return $request->hasHeader('X-Custom-Header', 'custom-value');
    });
});

it('supports multiple http methods', function (string $method) {
    Http::fake();

    $provider = new DefaultProvider(Http::getFacadeRoot());
    $provider->send('https://source.test', ['data' => true], [
        'url' => 'https://target.test/webhook',
        'method' => $method,
    ]);

    Http::assertSent(function ($request) use ($method) {
        return $request->method() === strtoupper($method);
    });
})->with(['GET', 'PUT', 'PATCH', 'DELETE', 'OPTIONS', 'HEAD']);

it('throws when webhook url is invalid', function () {
    $provider = new DefaultProvider(Http::getFacadeRoot());

    $provider->send('https://source.test', ['a' => 1], [
        'url' => 'invalid-url',
        'method' => 'POST',
    ]);
})->throws(\InvalidArgumentException::class);

it('throws when webhook method is invalid', function () {
    $provider = new DefaultProvider(Http::getFacadeRoot());

    $provider->send('https://source.test', ['a' => 1], [
        'url' => 'https://target.test/webhook',
        'method' => 'NOPE',
    ]);
})->throws(\InvalidArgumentException::class);

it('throws when timeout is not positive', function () {
    $provider = new DefaultProvider(Http::getFacadeRoot());

    $provider->send('https://source.test', ['a' => 1], [
        'url' => 'https://target.test/webhook',
        'method' => 'POST',
        'timeout' => 0,
    ]);
})->throws(\InvalidArgumentException::class);

it('throws when headers is not an array', function () {
    $provider = new DefaultProvider(Http::getFacadeRoot());

    $provider->send('https://source.test', ['a' => 1], [
        'url' => 'https://target.test/webhook',
        'method' => 'POST',
        'headers' => 'x-header: 1',
    ]);
})->throws(\InvalidArgumentException::class);

it('forwards nested payload and unicode correctly', function () {
    Http::fake();

    $provider = new DefaultProvider(Http::getFacadeRoot());
    $provider->send('https://source.test', [
        'user' => ['name' => 'Cagri', 'active' => true],
        'items' => [['id' => 1], ['id' => 2]],
    ], [
        'url' => 'https://target.test/webhook',
        'method' => 'POST',
        'headers' => [
            'X-Api-Key' => 'abc',
            'X-Request-Id' => 10,
        ],
    ]);

    Http::assertSent(function ($request) {
        return $request['user']['name'] === 'Cagri'
            && $request['user']['active'] === true
            && count($request['items']) === 2
            && $request->hasHeader('X-Api-Key', 'abc')
            && $request->hasHeader('X-Request-Id', '10');
    });
});
