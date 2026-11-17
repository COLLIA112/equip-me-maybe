<?php

namespace App\Http\Middleware;

use App\Enums\Role;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireWebRole
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_if($request->user()?->hasRole(Role::SecurityAuditor), 403);

        return $next($request);
    }
}
