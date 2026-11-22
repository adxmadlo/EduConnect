<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Remplace session_role_check() de l'ancienne application.
 * Usage : ->middleware('role:admin,chef_admin')
 */
class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->guest(route('login'));
        }

        if ($user->statut !== 'actif') {
            abort(403, 'Compte non actif.');
        }

        if (! in_array($user->role, $roles, true)) {
            abort(403, 'Accès réservé à un autre rôle.');
        }

        return $next($request);
    }
}
