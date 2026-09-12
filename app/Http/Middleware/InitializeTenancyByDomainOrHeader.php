<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Resolvers\DomainTenantResolver;
use Stancl\Tenancy\Tenancy;
use Symfony\Component\HttpFoundation\Response;

/**
 * Initialize tenancy from the request Host, or from X-Tenant-Domain on central hosts.
 *
 * Supports a Next.js BFF that forwards tenant traffic to the central API origin
 * with an X-Tenant-Domain header when per-tenant DNS is unavailable.
 */
class InitializeTenancyByDomainOrHeader extends InitializeTenancyByDomain
{
    public const HEADER = 'X-Tenant-Domain';

    public function __construct(Tenancy $tenancy, DomainTenantResolver $resolver)
    {
        parent::__construct($tenancy, $resolver);

        static::$onFail ??= function ($exception, Request $request, Closure $next): Response {
            return response()->json([
                'message' => 'Tenant could not be identified on this domain.',
            ], 404);
        };
    }

    /**
     * Handle an incoming request.
     *
     * @param  Request  $request
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        return $this->initializeTenancy(
            $request,
            $next,
            $this->resolveDomain($request)
        );
    }

    /**
     * Resolve the tenant hostname from Host or X-Tenant-Domain.
     */
    public function resolveDomain(Request $request): string
    {
        $host = $this->normalizeDomain($request->getHost());
        $central = $this->centralDomains();

        if (! in_array($host, $central, true)) {
            return $host;
        }

        $header = trim((string) $request->header(self::HEADER, ''));

        if ($header === '') {
            return $host;
        }

        return $this->normalizeDomain($header);
    }

    /**
     * Normalize a host string for Domain table lookup.
     */
    public function normalizeDomain(string $domain): string
    {
        $value = Str::lower(trim($domain));

        if ($value === '') {
            return '';
        }

        if (str_contains($value, '://')) {
            $host = parse_url($value, PHP_URL_HOST);
            $port = parse_url($value, PHP_URL_PORT);

            if (is_string($host) && $host !== '') {
                return $port ? "{$host}:{$port}" : $host;
            }
        }

        $value = rtrim($value, '.');

        if (preg_match('/^\[(.+)\]:(\d+)$/', $value, $matches) === 1) {
            return $matches[1];
        }

        if (preg_match('/^([^:]+):(\d+)$/', $value, $matches) === 1) {
            return $matches[1];
        }

        return $value;
    }

    /**
     * @return list<string>
     */
    private function centralDomains(): array
    {
        return array_map(
            fn (mixed $domain): string => Str::lower((string) $domain),
            config('tenancy.central_domains', []),
        );
    }
}
