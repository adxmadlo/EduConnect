<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Classe;
use App\Models\Etudiant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

/**
 * Inscription publique : reprend register.php de l'ancienne version.
 * Le compte est créé avec le rôle « etudiant » et le statut « en_attente » :
 * il ne peut se connecter qu'après validation par l'administration.
 */
class RegisterController extends Controller
{
    public function create()
    {
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }

        return view('auth.register', [
            'classes' => Classe::orderBy('code')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $donnees = $request->validate([
            'prenom'   => ['required', 'string', 'min:2', 'max:80'],
            'nom'      => ['required', 'string', 'min:2', 'max:80'],
            'email'    => ['required', 'email', 'max:190', 'unique:users,email'],
            'classe_id' => ['required', 'integer', 'exists:classes,id'],
            'password' => ['required', 'string', 'min:8', 'max:72'],
        ], [
            'prenom.required'  => 'Veuillez indiquer votre prénom.',
            'nom.required'     => 'Veuillez indiquer votre nom.',
            'email.required'   => 'Veuillez indiquer votre adresse e-mail.',
            'email.email'      => 'Cette adresse e-mail n’est pas valide.',
            'email.unique'     => 'Un compte utilise déjà cette adresse e-mail.',
            'classe_id.required' => 'Veuillez choisir votre classe.',
            'classe_id.exists'   => 'Cette classe n’existe pas.',
            'password.required'  => 'Veuillez choisir un mot de passe.',
            'password.min'       => 'Le mot de passe doit contenir au moins 8 caractères.',
        ]);

        DB::transaction(function () use ($donnees) {
            $user = User::create([
                'nom'      => $donnees['nom'],
                'prenom'   => $donnees['prenom'],
                'email'    => $donnees['email'],
                'password' => $donnees['password'],
                'role'     => 'etudiant',
                'statut'   => 'en_attente',
            ]);

            Etudiant::create([
                'user_id'   => $user->id,
                'classe_id' => $donnees['classe_id'],
            ]);
        });

        return redirect()
            ->route('login')
            ->with('succes', 'Votre demande de compte a été enregistrée. Elle sera activée par l’administration.');
    }
}
