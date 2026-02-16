<?php

use Illuminate\Support\Facades\Http;
use Moneo\RequestForwarder\Providers\Discord;

it('sends a Discord-formatted message with URL and params', function () {
    Http::fake();

    $provider = new Discord(Http::getFacadeRoot());
    $provider->send('https://source.test/webhook', ['event' => 'order.created'], [
        'url' => 'https://discord.com/api/webhooks/test',
        'method' => 'POST',
    ]);

    Http::assertSent(function ($request) {
        $body = $request->data();

        return $request->url() === 'https://discord.com/api/webhooks/test'
            && $request->method() === 'POST'
            && isset($body['content'])
            && str_contains($body['content'], 'https://source.test/webhook')
            && str_contains($body['content'], '"event":"order.created"');
    });
});

it('always uses POST method for Discord webhooks', function () {
    Http::fake();

    $provider = new Discord(Http::getFacadeRoot());
    $provider->send('https://source.test', ['data' => true], [
        'url' => 'https://discord.com/api/webhooks/test',
        'method' => 'GET',
    ]);

    Http::assertSent(function ($request) {
        return $request->method() === 'POST';
    });
});

it('handles empty params gracefully', function () {
    Http::fake();

    $provider = new Discord(Http::getFacadeRoot());
    $provider->send('https://source.test', [], [
        'url' => 'https://discord.com/api/webhooks/test',
        'method' => 'POST',
    ]);

    Http::assertSent(function ($request) {
        $body = $request->data();

        return isset($body['content'])
            && str_contains($body['content'], 'https://source.test');
    });
});

it('supports null and boolean payload values', function () {
    Http::fake();

    $provider = new Discord(Http::getFacadeRoot());
    $provider->send('https://source.test', ['flag' => true, 'deleted' => false, 'meta' => null], [
        'url' => 'https://discord.com/api/webhooks/test',
        'method' => 'POST',
    ]);

    Http::assertSent(function ($request) {
        $body = $request->data();

        return str_contains($body['content'], '"flag":true')
            && str_contains($body['content'], '"deleted":false')
            && str_contains($body['content'], '"meta":null');
    });
});

it('throws when discord webhook url is invalid', function () {
    $provider = new Discord(Http::getFacadeRoot());

    $provider->send('https://source.test', ['a' => 1], [
        'url' => 'not-a-url',
    ]);
})->throws(\InvalidArgumentException::class);

it('throws when timeout is not positive', function () {
    $provider = new Discord(Http::getFacadeRoot());

    $provider->send('https://source.test', ['a' => 1], [
        'url' => 'https://discord.com/api/webhooks/test',
        'timeout' => -1,
    ]);
})->throws(\InvalidArgumentException::class);

it('handles long source urls', function () {
    Http::fake();

    $longUrl = 'https://source.test/'.str_repeat('a', 1024);
    $provider = new Discord(Http::getFacadeRoot());
    $provider->send($longUrl, ['event' => 'x'], [
        'url' => 'https://discord.com/api/webhooks/test',
        'method' => 'POST',
    ]);

    Http::assertSentCount(1);
});
