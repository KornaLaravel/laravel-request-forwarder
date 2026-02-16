<?php

use Illuminate\Http\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Moneo\RequestForwarder\Facades\RequestForwarder;
use Moneo\RequestForwarder\ProcessRequestForwarder;
use Moneo\RequestForwarder\RequestForwarder as RequestForwarderClass;

it('resolves the facade to the RequestForwarder class', function () {
    $instance = RequestForwarder::getFacadeRoot();

    expect($instance)->toBeInstanceOf(RequestForwarderClass::class);
});

it('can call triggerHooks through the facade', function () {
    Http::fake();

    RequestForwarder::triggerHooks('https://source.test', ['data' => 'test']);

    Http::assertSentCount(1);
});

it('can call sendAsync through the facade', function () {
    Queue::fake();
    $request = HttpRequest::create('https://source.test/webhook?foo=bar', 'POST', ['name' => 'ali']);

    RequestForwarder::sendAsync($request, 'custom-group');

    Queue::assertPushed(ProcessRequestForwarder::class, function (ProcessRequestForwarder $job) {
        return $job->webhookName === 'custom-group'
            && $job->params['name'] === 'ali'
            && $job->params['_query']['foo'] === 'bar';
    });
});
