<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/** Connexion / déconnexion — reprend les règles de Views/Auth/login.php. */
class LoginController extends Controller
{
    public function create()
    {
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }

        // Le panneau d'inscription du container legacy liste les classes ouvertes.
        return view('auth.login', [
            'classes' => \App\Models\Classe::orderBy('code')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $donnees = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
        ], [
            'email.required'    => 'Veuillez saisir votre adresse e-mail.',
            'email.email'       => 'Cette adresse e-mail n\'est pas valide.',
            'password.required' => 'Veuillez saisir votre mot de passe.',
        ]);

        // Limitation des tentatives (5 par minute et par e-mail + IP)
        $cle = 'connexion:' . mb_strtolower($donnees['email']) . '|' . $request->ip();

        if (RateLimiter::tooManyAttempts($cle, 5)) {
            throw ValidationException::withMessages([
                'email' => 'Trop de tentatives. Réessayez dans ' . RateLimiter::availableIn($cle) . ' secondes.',
            ]);
        }

        $user = User::where('email', $donnees['email'])->first();

        if (! $user || ! Auth::attempt(['email' => $donnees['email'], 'password' => $donnees['password']])) {
            RateLimiter::hit($cle, 60);

            throw ValidationException::withMessages([
                'email' => 'Email ou mot de passe incorrect.',
            ]);
        }

        if ($user->statut === 'suspendu') {
            Auth::logout();

            throw ValidationException::withMessages([
                'email' => 'Compte suspendu. Contactez l\'administration.',
            ]);
        }

        if ($user->statut !== 'actif') {
            Auth::logout();

            throw ValidationException::withMessages([
                'email' => 'Compte en attente de validation par l\'administration.',
            ]);
        }

        RateLimiter::clear($cle);

        $request->session()->regenerate();
        $request->session()->put('derniere_activite', time());

        $user->forceFill(['dernier_login' => now()])->save();

        AuditLog::create([
            'user_id'    => $user->id,
            'action'     => 'LOGIN',
            'entite'     => 'users',
            'entite_id'  => $user->id,
            'ip_address' => $request->ip(),
        ]);

        return redirect()->intended(route('dashboard'));
    }

    public function destroy(Request $request)
    {
        $user = $request->user();

        if ($user) {
            AuditLog::create([
                'user_id'    => $user->id,
                'action'     => 'LOGOUT',
                'entite'     => 'users',
                'entite_id'  => $user->id,
                'ip_address' => $request->ip(),
            ]);
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('succes', 'Vous êtes déconnecté.');
    }
}
