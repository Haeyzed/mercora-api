<?php

declare(strict_types=1);

namespace App\Providers;

use App\Http\Middleware\InitializeTenancyByDomainOrHeader;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\Parameter;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\Types\StringType;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

/**
 * Separate Scramble documentation sites for landlord and tenant APIs.
 *
 * @see https://scramble.dedoc.co/usage/multiple-docs
 */
class ScrambleDocumentationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        Scramble::ignoreDefaultRoutes();
    }

    public function boot(): void
    {
        Gate::define('viewApiDocs', function ($user = null): bool {
            return ! app()->isProduction();
        });

        $host = parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'localhost';

        Scramble::registerApi('landlord', [
            'api_path' => 'api',
            'api_domain' => $host,
            'info' => [
                'description' => 'Platform (landlord) API. Authenticate via `POST /api/landlord/auth/login`, then send `Authorization: Bearer {token}` on protected endpoints.',
            ],
            'ui' => [
                'title' => 'Landlord API',
            ],
            'servers' => [
                'Live' => 'api',
            ],
        ])
            ->routes(function (Route $route): bool {
                return Str::startsWith($route->uri, 'api/landlord');
            })
            ->expose(
                ui: '/docs/landlord',
                document: '/docs/landlord.json',
            );

        Scramble::registerApi('tenant', [
            'api_path' => 'api',
            'api_domain' => $host,
            'info' => [
                'description' => <<<'MD'
Tenant store API.

**Identification (required for `/tenant/*`):** Try-it runs on the central host, so set header `X-Tenant-Domain` to the tenant domain (e.g. `acme.mercora-api.test`). Alternatively call the tenant Host directly (e.g. `https://acme.mercora-api.test/api/tenant/...`) without the header.

Authenticate via `POST /tenant/auth/login`, then send `Authorization: Bearer {token}`.

`GET /public/tenant?domain=` resolves branding without initializing tenancy and does not need `X-Tenant-Domain`.
MD,
            ],
            'ui' => [
                'title' => 'Tenant API',
            ],
            'servers' => [
                'Live' => 'api',
            ],
        ])
            ->routes(function (Route $route): bool {
                $uri = $route->uri;

                return Str::startsWith($uri, 'api/tenant')
                    || Str::startsWith($uri, 'api/public');
            })
            ->afterOpenApiGenerated(function (OpenApi $openApi): void {
                $header = Parameter::make(InitializeTenancyByDomainOrHeader::HEADER, 'header')
                    ->description('Required when Host is the central API. Use the tenant domain registered on the tenant (e.g. acme.mercora-api.test).')
                    ->setSchema(Schema::fromType(new StringType))
                    ->example('acme.mercora-api.test')
                    ->required(true);

                foreach ($openApi->paths as $path) {
                    if (! Str::startsWith($path->path, '/tenant')) {
                        continue;
                    }

                    foreach ($path->operations as $operation) {
                        $operation->addParameters([$header]);
                    }
                }
            })
            ->expose(
                ui: '/docs/tenant',
                document: '/docs/tenant.json',
            );
    }
}
