<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Reservation;
use App\Support\OccupationSalles;
use Illuminate\Http\Request;

/** Validation des demandes de réservation de salles. */
class ReservationController extends Controller
{
    public function index(Request $request)
    {
        $statut = $request->query('statut');

        $reservations = Reservation::with(['salle', 'demandeur'])
            ->when($statut, fn ($q) => $q->where('statut', $statut))
            ->orderByRaw("CASE WHEN statut = 'en_attente' THEN 0 ELSE 1 END")
            ->orderByDesc('date_debut')
            ->paginate(15)
            ->withQueryString();

        // Plan des étages et graphique de disponibilité, identiques à l'espace étudiant.
        [$debut, $fin] = OccupationSalles::creneau($request->query('creneau'));
        $occupation = OccupationSalles::plan($debut, $fin);

        return view('admin.reservations', [
            'reservations' => $reservations,
            'statut'       => $statut,
            'enAttente'    => Reservation::where('statut', 'en_attente')->count(),
            'planEtages'   => $occupation['planEtages'],
            'totalSalles'  => $occupation['total'],
            'totalLibres'  => $occupation['libres'],
            'debutCreneau' => $debut,
            'finCreneau'   => $fin,
            'interactif'   => false,
            'nbNotifs'     => $request->user()->notificationsEduconnect()->where('lu', false)->count(),
        ]);
    }

    public function statut(Request $request, Reservation $reservation)
    {
        $donnees = $request->validate([
            'statut'      => ['required', 'in:en_attente,confirmee,refusee,annulee'],
            'notes_admin' => ['nullable', 'string', 'max:500'],
        ], [
            'statut.in' => 'Statut de réservation inconnu.',
        ]);

        $reservation->update([
            'statut'      => $donnees['statut'],
            'notes_admin' => $donnees['notes_admin'] ?? $reservation->notes_admin,
        ]);

        return back()->with('succes', 'Réservation ' . $reservation->code_resa . ' : ' . $donnees['statut'] . '.');
    }
}
