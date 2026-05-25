<?php

namespace App\Http\Middleware;

use App\UserRole;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RedirectToCorrectPanel
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): mixed  $next
     */
    public function handle(Request $request, Closure $next): mixed
    {
        $user = Auth::user();

        if ($user && ($user->role?->value ?? $user->role) !== UserRole::Admin->value) {
            $role = $user->role instanceof UserRole ? $user->role : UserRole::from($user->role);

            return new RedirectResponse($role->getRedirectPath());
        }

        return $next($request);
    }
}
