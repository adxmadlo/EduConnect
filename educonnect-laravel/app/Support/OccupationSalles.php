<?php

namespace App\Support;

use App\Models\EmploiDuTemps;
use App\Models\Reservation;
use App\Models\Salle;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Occupation des salles : réservations acceptées ou en attente et créneaux de
 * cours. Alimente le plan des étages et le graphique de disponibilité (espaces
 * étudiant et administration), soit à l'instant présent, soit sur un créneau
 * choisi par l'utilisateur.
 */
class OccupationSalles
{
    /** Durée du créneau proposé dans le sélecteur, en minutes. */
    public const DUREE_CRENEAU = 60;

    /**
     * Convertit la valeur d'un champ datetime-local en créneau d'une heure.
     * Sans valeur (ou valeur illisible), on part de l'heure en cours.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    public static function creneau(?string $valeur = null): array
    {
        $debut = now()->startOfHour();

        if (is_string($valeur) && trim($valeur) !== '') {
            try {
                $debut = Carbon::parse($valeur)->startOfMinute();
            } catch (\Throwable) {
                // Valeur illisible : on garde l'heure en cours.
            }
        }

        return [$debut, $debut->copy()->addMinutes(self::DUREE_CRENEAU)];
    }

    /**
     * Occupation des salles. Sans arguments, on interroge l'instant présent ;
     * avec un début et une fin, on teste le chevauchement sur tout le créneau.
     *
     * @return array{
     *     salles: \Illuminate\Support\Collection<int, Salle>,
     *     planEtages: \Illuminate\Support\Collection<int, array>,
     *     total: int,
     *     libres: int
     * }
     */
    public static function plan(?CarbonInterface $debut = null, ?CarbonInterface $fin = null): array
    {
        $debut = $debut ?? now();
        $fin = $fin ?? $debut;

        $salles = Salle::where('actif', true)->orderBy('etage')->orderBy('nom')->get();

        $occupees = Reservation::whereIn('statut', ['en_attente', 'confirmee'])
            ->tap(fn (Builder $requete) => self::surLeCreneau($requete, $debut, $fin))
            ->pluck('salle_id')
            ->merge(
                EmploiDuTemps::query()
                    ->tap(fn (Builder $requete) => self::surLeCreneau($requete, $debut, $fin))
                    ->pluck('salle_id')
            )
            ->unique()
            ->all();

        $planEtages = $salles
            ->groupBy('etage')
            ->map(function ($groupe, $etage) use ($occupees) {
                $liste = $groupe->map(fn (Salle $salle) => [
                    'id'       => $salle->id,
                    'nom'      => $salle->nom,
                    'libelle'  => Str::after($salle->nom, 'UCAO-'),
                    'type'     => $salle->type,
                    'capacite' => $salle->capacite,
                    'occupee'  => in_array($salle->id, $occupees, true),
                ])->values();

                return [
                    'etage'  => (int) $etage,
                    'salles' => $liste,
                    'libres' => $liste->where('occupee', false)->count(),
                    'total'  => $liste->count(),
                ];
            })
            ->sortKeys()
            ->values();

        $occupeesActives = $salles->filter(fn (Salle $salle) => in_array($salle->id, $occupees, true))->count();

        return [
            'salles'     => $salles,
            'planEtages' => $planEtages,
            'total'      => $salles->count(),
            'libres'     => $salles->count() - $occupeesActives,
        ];
    }

    /**
     * Restreint une requête aux occupations qui chevauchent le créneau. Quand
     * début et fin sont identiques, on teste l'instant présent.
     */
    private static function surLeCreneau(Builder $requete, CarbonInterface $debut, CarbonInterface $fin): void
    {
        if ($debut->equalTo($fin)) {
            $requete->where('date_debut', '<=', $debut)->where('date_fin', '>', $debut);

            return;
        }

        $requete->where('date_debut', '<', $fin)->where('date_fin', '>', $debut);
    }
}
