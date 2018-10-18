<?php

namespace RRRBAC\Middlewares;

use RRRBAC\Services\RoleService;
use Closure;
use Illuminate\Http\Response;

class RBACMiddleware
{
    public function __construct(RoleService $service)
    {
        $this->service = $service;
    }
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next, $grantType = 'password')
    {
        if (
            (config('app.env') == 'testing' || !app()->runningInConsole())
            && !$this->service->canAccess($request)
        ) {
            return new Response('Forbidden.', Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
