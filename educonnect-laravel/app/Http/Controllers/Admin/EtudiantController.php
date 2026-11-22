<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Classe;
use App\Models\Etudiant;
use Illuminate\Http\Request;

class EtudiantController extends Controller
{
    public function index(Request $request)
    {
        $recherche = trim((string) $request->query('q'));
        $classeId = $request->integer('classe');

        $etudiants = Etudiant::with(['user', 'classe'])
            ->when($recherche !== '', function ($query) use ($recherche) {
                $query->whereHas('user', function ($q) use ($recherche) {
                    $q->where('nom', 'like', "%{$recherche}%")
                        ->orWhere('prenom', 'like', "%{$recherche}%")
                        ->orWhere('email', 'like', "%{$recherche}%")
                        ->orWhere('numero_ucao', 'like', "%{$recherche}%");
                });
            })
            ->when($classeId > 0, fn ($query) => $query->where('classe_id', $classeId))
            ->orderBy('classe_id')
            ->orderBy('id')
            ->paginate(20)
            ->withQueryString();

        return view('admin.etudiants', [
            'etudiants' => $etudiants,
            'classes'   => Classe::orderBy('code')->get(),
            'recherche' => $recherche,
            'classeId'  => $classeId,
            'nbNotifs'  => $request->user()->notificationsEduconnect()->where('lu', false)->count(),
        ]);
    }
}
