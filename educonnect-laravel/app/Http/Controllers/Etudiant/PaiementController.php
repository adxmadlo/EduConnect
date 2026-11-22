<?php

namespace App\Http\Controllers\Etudiant;

use App\Http\Controllers\Controller;
use App\Models\Paiement;
use Illuminate\Http\Request;

class PaiementController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $etudiant = $user->etudiant;
        $annee = config('educonnect.annee_acad');

        $frais = $etudiant?->fraisCourants($annee);

        $paiements = $frais
            ? Paiement::where('frais_id', $frais->id)->latest()->get()
            : collect();

        return view('etudiant.paiements', [
            'etudiant'  => $etudiant,
            'frais'     => $frais,
            'paiements' => $paiements,
            'annee'     => $annee,
            'montantPaye' => $frais?->montantPaye() ?? 0,
            'solde'       => $frais?->soldeRestant() ?? 0,
            'pourcentage' => $frais?->pourcentagePaye() ?? 0,
            'nbNotifs'  => $user->notificationsEduconnect()->where('lu', false)->count(),
        ]);
    }
}
