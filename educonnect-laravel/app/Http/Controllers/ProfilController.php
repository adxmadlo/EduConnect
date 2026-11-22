<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class ProfilController extends Controller
{
    public function index(Request $request)
    {
        return view('profil', [
            'user' => $request->user()->load('preferences'),
        ]);
    }

    public function update(Request $request)
    {
        $user = $request->user();

        $donnees = $request->validate([
            'telephone'         => ['nullable', 'string', 'max:20'],
            'adresse'           => ['nullable', 'string', 'max:300'],
            'mot_de_passe_actuel' => ['nullable', 'string'],
            'nouveau_mot_de_passe' => ['nullable', 'confirmed', Password::min(8)],
        ], [
            'nouveau_mot_de_passe.confirmed' => 'La confirmation du mot de passe ne correspond pas.',
            'nouveau_mot_de_passe.min'       => 'Le mot de passe doit contenir au moins 8 caractères.',
        ]);

        $user->update([
            'telephone' => $donnees['telephone'] ?? $user->telephone,
        ]);

        if ($user->etudiant && array_key_exists('adresse', $donnees)) {
            $user->etudiant->update(['adresse' => $donnees['adresse']]);
        }

        if (! empty($donnees['nouveau_mot_de_passe'])) {
            if (! Hash::check($donnees['mot_de_passe_actuel'] ?? '', $user->password)) {
                return back()->withErrors(['mot_de_passe_actuel' => 'Mot de passe actuel incorrect.']);
            }

            $user->update(['password' => $donnees['nouveau_mot_de_passe']]);
        }

        return back()->with('succes', 'Profil mis à jour.');
    }
}
