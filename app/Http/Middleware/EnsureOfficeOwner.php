<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureOfficeOwner
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->isOfficeOwner()) {
            abort(403);
        }

        return $next($request);
    }
}
