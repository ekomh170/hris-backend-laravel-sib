<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * Handle incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param string ...$roles
     * @return Response
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = Auth::guard('api')->user();
        
        abort_unless($user, 401, 'Unauthenticated');

        // Support enum string and cast
        $userRole = is_object($user->role) ? $user->role->value : $user->role;        if (!in_array($userRole, $roles, true)) {
            abort(403, 'Forbidden');
        }

        return $next($request);
    }
}
