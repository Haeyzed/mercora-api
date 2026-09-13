<?php

declare(strict_types=1);

use Dedoc\Scramble\Scramble;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;
use Illuminate\Support\Str;

test('default scramble docs route is disabled', function (): void {
    $this->get('/docs/api')->assertNotFound();
});

test('landlord and tenant apis are registered with scramble', function (): void {
    $apis = array_keys(Scramble::getConfigurationsInstance()->all());

    expect($apis)->toContain('landlord', 'tenant');
});

test('landlord openapi document serves from cache', function (): void {
    cache()->store(config('scramble.cache.store'))->forever(
        config('scramble.cache.key').':landlord',
        [
            'openapi' => '3.1.0',
            'info' => ['title' => 'Landlord API', 'version' => '1.0.0'],
            'paths' => [],
        ],
    );

    $this->get('/docs/landlord.json')
        ->assertOk()
        ->assertJsonPath('info.title', 'Landlord API');
});

test('tenant openapi document serves from cache', function (): void {
    cache()->store(config('scramble.cache.store'))->forever(
        config('scramble.cache.key').':tenant',
        [
            'openapi' => '3.1.0',
            'info' => ['title' => 'Tenant API', 'version' => '1.0.0'],
            'paths' => [],
        ],
    );

    $this->get('/docs/tenant.json')
        ->assertOk()
        ->assertJsonPath('info.title', 'Tenant API');
});

test('landlord docs include landlord routes and exclude tenant routes', function (): void {
    $filter = Scramble::getGeneratorConfig('landlord')->routes();
    $documented = collect(RouteFacade::getRoutes())->filter(fn (Route $route): bool => $filter($route));

    expect($documented->isNotEmpty())->toBeTrue()
        ->and($documented->every(fn (Route $route): bool => Str::startsWith($route->uri, 'api/landlord')))->toBeTrue()
        ->and($documented->contains(fn (Route $route): bool => Str::startsWith($route->uri, 'api/tenant')))->toBeFalse();
});

test('tenant docs include tenant and public routes and exclude landlord routes', function (): void {
    $filter = Scramble::getGeneratorConfig('tenant')->routes();
    $documented = collect(RouteFacade::getRoutes())->filter(fn (Route $route): bool => $filter($route));

    expect($documented->isNotEmpty())->toBeTrue()
        ->and($documented->every(function (Route $route): bool {
            $uri = $route->uri;

            return Str::startsWith($uri, 'api/tenant') || Str::startsWith($uri, 'api/public');
        }))->toBeTrue()
        ->and($documented->contains(fn (Route $route): bool => Str::startsWith($route->uri, 'api/public')))->toBeTrue()
        ->and($documented->contains(fn (Route $route): bool => Str::startsWith($route->uri, 'api/landlord')))->toBeFalse();
});

test('tenant openapi documents X-Tenant-Domain on tenant paths only', function (): void {
    cache()->store(config('scramble.cache.store'))->forget(config('scramble.cache.key').':tenant');

    $document = $this->get('/docs/tenant.json')->assertOk()->json();

    $loginParameters = collect($document['paths']['/tenant/auth/login']['post']['parameters'] ?? []);
    $publicParameters = collect($document['paths']['/public/tenant']['get']['parameters'] ?? []);

    expect($loginParameters->contains(fn (array $parameter): bool => ($parameter['name'] ?? null) === 'X-Tenant-Domain'))->toBeTrue()
        ->and($publicParameters->contains(fn (array $parameter): bool => ($parameter['name'] ?? null) === 'X-Tenant-Domain'))->toBeFalse();
});
