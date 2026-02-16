<?php

use Illuminate\Http\Client\Factory;
use Moneo\RequestForwarder\ProcessRequestForwarder;
use Moneo\RequestForwarder\Providers\ProviderInterface;
use Moneo\RequestForwarder\RequestForwarder;

it('has a valid default webhook group name', function () {
    expect(config('request-forwarder.default_webhook_group_name'))
        ->toBe('default');
});

it('has webhooks configuration as an array', function () {
    expect(config('request-forwarder.webhooks'))
        ->toBeArray()
        ->not->toBeEmpty();
});

it('has valid webhook group structure', function () {
    $webhooks = config('request-forwarder.webhooks');

    foreach ($webhooks as $groupName => $group) {
        expect($groupName)->toBeString();
        expect($group)->toBeArray()->toHaveKey('targets');
        expect($group['targets'])->toBeArray();

        foreach ($group['targets'] as $target) {
            expect($target)->toHaveKey('url');
            expect($target['url'])->toBeString();
            expect($target['method'] ?? 'POST')->toBeString();
        }
    }
});

it('validates that custom providers implement ProviderInterface', function () {
    config()->set('request-forwarder.webhooks.test-provider', [
        'targets' => [
            [
                'url' => 'https://discord.com/api/webhooks/test',
                'method' => 'POST',
                'provider' => \Moneo\RequestForwarder\Providers\Discord::class,
            ],
        ],
    ]);

    $webhooks = config('request-forwarder.webhooks');
    $foundProvider = false;

    foreach ($webhooks as $group) {
        foreach ($group['targets'] as $target) {
            if (isset($target['provider'])) {
                $foundProvider = true;
                expect($target['provider'])->toBeString();
                expect(
                    in_array(ProviderInterface::class, class_implements($target['provider']) ?: [])
                )->toBeTrue();
            }
        }
    }

    expect($foundProvider)->toBeTrue();
});

it('has a valid timeout configuration', function () {
    expect(config('request-forwarder.timeout'))
        ->toBeInt()
        ->toBeGreaterThan(0);
});

it('has a valid tries configuration', function () {
    expect(config('request-forwarder.tries'))
        ->toBeInt()
        ->toBeGreaterThanOrEqual(1);
});

it('has a valid backoff configuration', function () {
    $backoff = config('request-forwarder.backoff');

    expect($backoff)->toBeArray();

    foreach ($backoff as $seconds) {
        expect($seconds)->toBeInt()->toBeGreaterThan(0);
    }
});

it('has a valid queue class configuration', function () {
    $queueClass = config('request-forwarder.queue_class');

    expect($queueClass)->toBe(ProcessRequestForwarder::class);
    expect(class_exists($queueClass))->toBeTrue();
});

it('has a log_failures configuration as boolean', function () {
    expect(config('request-forwarder.log_failures'))
        ->toBeBool();
});

it('fails fast when webhook group is missing targets key', function () {
    config()->set('request-forwarder.webhooks.invalid-shape', []);

    $forwarder = new RequestForwarder(app(Factory::class), config('request-forwarder.webhooks'));
    $forwarder->triggerHooks('https://source.test', [], 'invalid-shape');
})->throws(\InvalidArgumentException::class);

it('fails fast when webhook group has empty targets array', function () {
    config()->set('request-forwarder.webhooks.invalid-empty', ['targets' => []]);

    $forwarder = new RequestForwarder(app(Factory::class), config('request-forwarder.webhooks'));
    $forwarder->triggerHooks('https://source.test', [], 'invalid-empty');
})->throws(\InvalidArgumentException::class);

it('rejects invalid tries configuration at runtime', function () {
    config()->set('request-forwarder.tries', -2);
    $job = new ProcessRequestForwarder('https://source.test', []);

    expect($job->tries)->toBe(3);
});

it('rejects invalid backoff configuration at runtime', function () {
    config()->set('request-forwarder.backoff', [-1, 0]);
    $job = new ProcessRequestForwarder('https://source.test', []);

    expect($job->backoff)->toBe([5, 30, 60]);
});
