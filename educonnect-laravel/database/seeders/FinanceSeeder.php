<?php

namespace Database\Seeders;

use App\Models\Etudiant;
use App\Models\FraisScolarite;
use App\Models\Paiement;
use App\Models\User;
use Illuminate\Database\Seeder;

/** Frais de scolarité (500 000 FCFA, plan trimestriel) et quelques paiements. */
class FinanceSeeder extends Seeder
{
    public function run(): void
    {
        $annee = config('educonnect.annee_acad');
        $montant = (float) config('educonnect.frais_annuels');

        $etudiants = Etudiant::orderBy('id')->get();
        $parent = User::where('role', 'parent')->orderBy('id')->first();
        $recu = 1;

        foreach ($etudiants as $etudiant) {
            $frais = FraisScolarite::updateOrCreate(
                ['etudiant_id' => $etudiant->id, 'annee_acad' => $annee],
                [
                    'montant_total' => $montant,
                    'plan'          => 'trimestriel',
                    'statut'        => $etudiant->id % 5 === 0 ? 'retard' : 'en_cours',
                    'date_echeance' => now()->addMonths(3)->toDateString(),
                ]
            );

            // Les 6 premiers étudiants ont un historique de paiement
            if ($etudiant->id > 6) {
                continue;
            }

            $payeur = $parent?->id ?? $etudiant->user_id;

            $paiements = [
                ['montant' => 125000, 'moyen' => 'wave', 'statut' => 'confirme', 'jours' => 45],
                ['montant' => 125000, 'moyen' => $etudiant->id % 2 === 0 ? 'orange_money' : 'carte', 'statut' => 'confirme', 'jours' => 15],
            ];

            if ($etudiant->id === 3) {
                $paiements[] = ['montant' => 125000, 'moyen' => 'wave', 'statut' => 'en_attente', 'jours' => 1];
            }

            foreach ($paiements as $ligne) {
                $numero = sprintf('REC-2026-%05d', $recu++);

                Paiement::updateOrCreate(['recu_numero' => $numero], [
                    'frais_id'     => $frais->id,
                    'payeur_id'    => $payeur,
                    'montant'      => $ligne['montant'],
                    'moyen'        => $ligne['moyen'],
                    'statut'       => $ligne['statut'],
                    'recu_url'     => null,
                    'confirmed_at' => $ligne['statut'] === 'confirme' ? now()->subDays($ligne['jours']) : null,
                ]);
            }
        }

        // Statut des frais recalculé d'après les paiements confirmés
        foreach ($etudiants as $etudiant) {
            $frais = $etudiant->fraisCourants($annee);
            if (! $frais) {
                continue;
            }

            $frais->update([
                'statut' => match (true) {
                    $frais->soldeRestant() <= 0 => 'solde',
                    $frais->montantPaye() > 0   => 'en_cours',
                    default                     => $frais->statut,
                },
            ]);
        }
    }
}
