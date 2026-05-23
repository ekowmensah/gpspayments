<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();
        if (!$user) {
            return redirect()->route('login');
        }

        if (empty($roles) || $user->hasRole(...$roles)) {
            return $next($request);
        }

        abort(403, 'You do not have permission to access this page.');
    }
}

