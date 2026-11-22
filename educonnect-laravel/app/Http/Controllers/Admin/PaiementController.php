<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Paiement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PaiementController extends Controller
{
    public function index(Request $request)
    {
        $statut = $request->query('statut');
        $moyen = $request->query('moyen');

        $paiements = Paiement::with(['payeur', 'frais.etudiant.user', 'frais.etudiant.classe'])
            ->when($statut, fn ($q) => $q->where('statut', $statut))
            ->when($moyen, fn ($q) => $q->where('moyen', $moyen))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $parMoyen = Paiement::where('statut', 'confirme')
            ->select('moyen', DB::raw('SUM(montant) as total'), DB::raw('COUNT(*) as nb'))
            ->groupBy('moyen')
            ->orderByDesc('total')
            ->get();

        return view('admin.paiements', [
            'paiements'  => $paiements,
            'statut'     => $statut,
            'moyen'      => $moyen,
            'totalConfirme' => (float) Paiement::where('statut', 'confirme')->sum('montant'),
            'totalAttente'  => (float) Paiement::where('statut', 'en_attente')->sum('montant'),
            'nbConfirmes'   => Paiement::where('statut', 'confirme')->count(),
            'parMoyen'   => $parMoyen,
            'nbNotifs'   => $request->user()->notificationsEduconnect()->where('lu', false)->count(),
        ]);
    }
}
