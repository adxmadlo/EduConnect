<?php

namespace App\Http\Controllers\Parent;

use App\Http\Controllers\Controller;
use App\Models\Etudiant;
use App\Models\Note;
use App\Models\Paiement;
use Illuminate\Http\Request;

class EnfantController extends Controller
{
    public function index(Request $request, ?Etudiant $etudiant = null)
    {
        $user = $request->user();
        $annee = config('educonnect.annee_acad');

        // Un parent ne peut consulter que ses propres enfants
        $enfants = Etudiant::with(['user', 'classe'])->where('parent_id', $user->id)->get();

        if ($etudiant && $etudiant->parent_id !== $user->id) {
            abort(403, 'Cet étudiant n\'est pas rattaché à votre compte.');
        }

        $selection = $etudiant ?? $enfants->first();

        $notes = $selection
            ? Note::with('module')
                ->where('etudiant_id', $selection->id)
                ->where('publie', true)
                ->where('annee_acad', $annee)
                ->orderBy('semestre')
                ->orderByDesc('note_finale')
                ->get()
            : collect();

        $frais = $selection?->fraisCourants($annee);

        return view('parent.enfants', [
            'enfants'   => $enfants,
            'selection' => $selection,
            'notes'     => $notes,
            'frais'     => $frais,
            'moyenne'   => $selection?->moyennePubliee($annee),
            'paiements' => $frais ? Paiement::where('frais_id', $frais->id)->latest()->get() : collect(),
            'annee'     => $annee,
            'nbNotifs'  => $user->notificationsEduconnect()->where('lu', false)->count(),
        ]);
    }
}
