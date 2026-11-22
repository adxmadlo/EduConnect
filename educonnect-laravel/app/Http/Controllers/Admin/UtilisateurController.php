<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class UtilisateurController extends Controller
{
    public function index(Request $request)
    {
        $role = $request->query('role');
        $recherche = trim((string) $request->query('q'));

        $utilisateurs = User::query()
            ->when($role, fn ($q) => $q->where('role', $role))
            ->when($recherche !== '', function ($q) use ($recherche) {
                $q->where(fn ($sub) => $sub
                    ->where('nom', 'like', "%{$recherche}%")
                    ->orWhere('prenom', 'like', "%{$recherche}%")
                    ->orWhere('email', 'like', "%{$recherche}%"));
            })
            ->orderBy('role')
            ->orderBy('nom')
            ->paginate(20)
            ->withQueryString();

        return view('admin.utilisateurs', [
            'utilisateurs' => $utilisateurs,
            'role'         => $role,
            'recherche'    => $recherche,
            'repartition'  => User::selectRaw('role, COUNT(*) as total')->groupBy('role')->pluck('total', 'role'),
            'nbNotifs'     => $request->user()->notificationsEduconnect()->where('lu', false)->count(),
        ]);
    }

    public function statut(Request $request, User $user)
    {
        $donnees = $request->validate([
            'statut' => ['required', 'in:actif,suspendu,en_attente,archive'],
        ], [
            'statut.in' => 'Statut inconnu.',
        ]);

        if ($user->id === $request->user()->id) {
            return back()->withErrors(['statut' => 'Vous ne pouvez pas modifier votre propre statut.']);
        }

        $user->update(['statut' => $donnees['statut']]);

        return back()->with('succes', 'Statut de ' . $user->nomComplet() . ' mis à jour.');
    }
}
