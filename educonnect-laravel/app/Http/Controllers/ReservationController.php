<?php

namespace App\Http\Controllers;

use App\Models\EmploiDuTemps;
use App\Models\Reservation;
use App\Models\Salle;
use App\Support\OccupationSalles;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Réservations de salles : les étudiants, enseignants et mentors déposent une
 * demande ; l'administration la confirme ou la refuse.
 */
class ReservationController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $statut = $request->query('statut');

        $reservations = Reservation::with(['salle', 'demandeur'])
            ->where('demandeur_id', $user->id)
            ->when($statut, fn ($q) => $q->where('statut', $statut))
            ->orderByDesc('date_debut')
            ->paginate(10)
            ->withQueryString();

        // Occupation des salles sur le créneau choisi (une heure par défaut).
        [$debut, $fin] = OccupationSalles::creneau($request->query('creneau'));
        $occupation = OccupationSalles::plan($debut, $fin);

        return view('reservations.index', [
            'reservations' => $reservations,
            'salles'       => $occupation['salles'],
            'statut'       => $statut,
            'planEtages'   => $occupation['planEtages'],
            'totalSalles'  => $occupation['total'],
            'totalLibres'  => $occupation['libres'],
            'debutCreneau' => $debut,
            'finCreneau'   => $fin,
            'interactif'   => true,
            'nbNotifs'     => $user->notificationsEduconnect()->where('lu', false)->count(),
        ]);
    }

    /**
     * Occupation d'un créneau au format JSON : le sélecteur date/heure du plan
     * recharge le plan des étages et le graphique sans recharger la page.
     */
    public function occupation(Request $request)
    {
        [$debut, $fin] = OccupationSalles::creneau($request->query('creneau'));
        $occupation = OccupationSalles::plan($debut, $fin);
        $interactif = $request->boolean('interactif');

        return response()->json([
            'debutLocal'   => $debut->format('Y-m-d\TH:i'),
            'finLocal'     => $fin->format('Y-m-d\TH:i'),
            'debutLisible' => $debut->format('d/m/Y H:i'),
            'finLisible'   => $fin->format('H:i'),
            'total'        => $occupation['total'],
            'libres'       => $occupation['libres'],
            'planEtages'   => $occupation['planEtages'],
            'plan'         => view('reservations._plan', [
                'planEtages' => $occupation['planEtages'],
                'interactif' => $interactif,
            ])->render(),
        ]);
    }

    public function store(Request $request)
    {
        $user = $request->user();

        $donnees = $request->validate([
            'salle_id'     => ['required', 'integer', 'exists:salles,id'],
            'objet'        => ['required', 'string', 'min:3', 'max:300'],
            'participants' => ['required', 'integer', 'min:1', 'max:500'],
            'date_debut'   => ['required', 'date', 'after:now'],
            'date_fin'     => ['required', 'date', 'after:date_debut'],
        ], [
            'salle_id.required'      => 'Veuillez choisir une salle.',
            'salle_id.exists'        => 'Cette salle n’existe pas.',
            'objet.required'         => 'Indiquez l’objet de la réservation.',
            'objet.min'              => 'L’objet doit contenir au moins 3 caractères.',
            'participants.required'  => 'Indiquez le nombre de participants.',
            'participants.min'       => 'Il faut au moins un participant.',
            'date_debut.required'    => 'Indiquez la date et l’heure de début.',
            'date_debut.after'       => 'La réservation doit commencer dans le futur.',
            'date_fin.required'      => 'Indiquez la date et l’heure de fin.',
            'date_fin.after'         => 'La fin doit être postérieure au début.',
        ]);

        $salle = Salle::findOrFail($donnees['salle_id']);

        if (! $salle->actif) {
            return back()->withInput()->withErrors(['salle_id' => 'Cette salle est indisponible.']);
        }

        if ($donnees['participants'] > $salle->capacite) {
            return back()->withInput()->withErrors([
                'participants' => 'La salle ' . $salle->nom . ' accueille au maximum ' . $salle->capacite . ' personnes.',
            ]);
        }

        $chevauchement = Reservation::where('salle_id', $salle->id)
            ->whereIn('statut', ['en_attente', 'confirmee'])
            ->where('date_debut', '<', $donnees['date_fin'])
            ->where('date_fin', '>', $donnees['date_debut'])
            ->exists();

        if ($chevauchement) {
            return back()->withInput()->withErrors([
                'date_debut' => 'La salle ' . $salle->nom . ' est déjà réservée sur ce créneau.',
            ]);
        }

        $cours = EmploiDuTemps::where('salle_id', $salle->id)
            ->where('date_debut', '<', $donnees['date_fin'])
            ->where('date_fin', '>', $donnees['date_debut'])
            ->exists();

        if ($cours) {
            return back()->withInput()->withErrors([
                'date_debut' => 'Un cours occupe déjà la salle ' . $salle->nom . ' sur ce créneau.',
            ]);
        }

        Reservation::create([
            'salle_id'     => $salle->id,
            'demandeur_id' => $user->id,
            'objet'        => $donnees['objet'],
            'participants' => $donnees['participants'],
            'date_debut'   => $donnees['date_debut'],
            'date_fin'     => $donnees['date_fin'],
            'statut'       => 'en_attente',
            'code_resa'    => 'RES-' . now()->format('ymd') . '-' . strtoupper(Str::random(4)),
        ]);

        return back()->with('succes', 'Demande envoyée : elle sera validée par l’administration.');
    }

    public function destroy(Request $request, Reservation $reservation)
    {
        $user = $request->user();

        abort_unless($reservation->demandeur_id === $user->id, 403, 'Cette réservation ne vous appartient pas.');

        if ($reservation->statut === 'confirmee') {
            return back()->withErrors(['reservation' => 'Une réservation confirmée ne peut plus être annulée en ligne. Contactez l’administration.']);
        }

        $reservation->update(['statut' => 'annulee']);

        return back()->with('succes', 'Réservation ' . $reservation->code_resa . ' annulée.');
    }
}
