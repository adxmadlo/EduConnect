<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Reprend le timeout de 900 s de Config/session_check.php :
 * l'utilisateur inactif est déconnecté et renvoyé vers la connexion avec un message.
 */
class ExpireSessionInactivite
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::check()) {
            return $next($request);
        }

        $timeout = (int) config('educonnect.session_timeout', 900);
        $derniere = (int) $request->session()->get('derniere_activite', 0);

        if ($derniere > 0 && (time() - $derniere) > $timeout) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->with('timeout', 'Votre session a expiré après 15 minutes d\'inactivité.');
        }

        $request->session()->put('derniere_activite', time());

        return $next($request);
    }
}
