<?php

namespace App\Http\Controllers\Etudiant;

use App\Http\Controllers\Controller;
use App\Models\EmploiDuTemps;
use Illuminate\Http\Request;

class PlanningController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $etudiant = $user->etudiant;

        $debutSemaine = now()->startOfWeek();
        $finSemaine   = now()->endOfWeek();

        $creneaux = $etudiant
            ? EmploiDuTemps::with(['module', 'salle', 'enseignant'])
                ->where('classe_id', $etudiant->classe_id)
                ->where('date_debut', '>=', $debutSemaine)
                ->where('date_debut', '<=', $finSemaine)
                ->orderBy('date_debut')
                ->get()
            : collect();

        // Les examens de la semaine sont présentés à part, les cours restent
        // regroupés par jour.
        $examens = $creneaux->where('type_creneau', 'examen')->values();

        $cours = $creneaux
            ->where('type_creneau', '!=', 'examen')
            ->groupBy(fn ($c) => $c->date_debut->toDateString());

        return view('etudiant.planning', [
            'etudiant'     => $etudiant,
            'cours'        => $cours,
            'examens'      => $examens,
            'debutSemaine' => $debutSemaine,
            'finSemaine'   => $finSemaine,
            'annee'        => config('educonnect.annee_acad'),
            'nbNotifs'     => $user->notificationsEduconnect()->where('lu', false)->count(),
        ]);
    }
}
