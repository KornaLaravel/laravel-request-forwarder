<?php

namespace Moneo\RequestForwarder;

use Illuminate\Http\Client\Factory;
use Illuminate\Routing\Router;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class RequestForwarderServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('request-forwarder')
            ->hasConfigFile();
    }

    public function registeringPackage(): void
    {
        $this->app->bind('laravel_request_forwarder.client', function ($app): Factory {
            return $app->make(Factory::class);
        });

        $this->app->singleton(RequestForwarder::class, function ($app): RequestForwarder {
            return new RequestForwarder(
                $app->make('laravel_request_forwarder.client'),
                [],
            );
        });
    }

    public function bootingPackage(): void
    {
        /** @var Router $router */
        $router = $this->app->make(Router::class);
        $router->aliasMiddleware('request-forwarder', RequestForwarderMiddleware::class);
    }
}
