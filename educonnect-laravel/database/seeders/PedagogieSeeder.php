<?php

namespace Database\Seeders;

use App\Models\Etudiant;
use App\Models\Module;
use App\Models\Note;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Notes et bulletins de démonstration.
 * Les valeurs sont calculées de façon déterministe (pas de Faker aléatoire) pour que
 * chaque `db:seed` reproduise les mêmes moyennes, rangs et mentions.
 */
class PedagogieSeeder extends Seeder
{
    public function run(): void
    {
        $annee = config('educonnect.annee_acad');

        $admin = User::where('role', 'chef_admin')->firstOrFail();
        $modules = Module::where('annee_etude', 1)->orderBy('id')->get();
        $etudiants = Etudiant::with('classe')->orderBy('id')->get();

        foreach ($etudiants as $etudiant) {
            // Les modules suivis dépendent de la classe de l'étudiant
            foreach ($modules as $module) {
                if (! $module->classes->contains('id', $etudiant->classe_id)) {
                    continue;
                }

                // S2 : notes seulement pour une partie des étudiants (saisie en cours)
                if ($module->semestre === 2 && $etudiant->id % 2 === 0) {
                    continue;
                }

                $base = 8.5 + (($etudiant->id * 13 + $module->id * 7) % 100) / 10;
                $base = min(19.5, $base);

                $estExamenSeul = $module->type_eval === '100';

                $note = Note::firstOrNew([
                    'etudiant_id' => $etudiant->id,
                    'module_id'   => $module->id,
                    'semestre'    => $module->semestre,
                    'annee_acad'  => $annee,
                ]);

                $note->note_devoir = $estExamenSeul ? null : round(min(20, $base + 0.6), 2);
                $note->note_examen = round($base, 2);
                $note->module()->associate($module);
                $note->calculerNoteFinale();

                $note->saisie_par = $module->enseignants()->wherePivot('est_principal', true)->first()?->id
                    ?? $admin->id;
                $note->saisie_le = now()->subDays(30 - ($etudiant->id % 20));
                $note->publie = $module->semestre === 1 || $etudiant->id % 3 !== 0;
                $note->publie_le = $note->publie ? $note->saisie_le->copy()->addDays(2) : null;
                $note->save();
            }
        }

        // ── Bulletins du semestre 1 : moyenne, rang, mention, crédits ──
        $this->genererBulletinsS1($annee, $admin);
    }

    private function genererBulletinsS1(string $annee, User $admin): void
    {
        $etudiants = Etudiant::with('classe')->orderBy('classe_id')->orderBy('id')->get();

        $resultats = [];

        foreach ($etudiants as $etudiant) {
            $notes = Note::with('module')
                ->where('etudiant_id', $etudiant->id)
                ->where('semestre', 1)
                ->where('annee_acad', $annee)
                ->where('publie', true)
                ->whereNotNull('note_finale')
                ->get();

            if ($notes->isEmpty()) {
                continue;
            }

            $moyenne = round($notes->avg('note_finale'), 2);
            $credits = $notes->filter(fn (Note $n) => (float) $n->note_finale >= config('educonnect.seuil_validation'))
                ->sum(fn (Note $n) => $n->module->credits);

            $resultats[$etudiant->classe_id][] = [
                'etudiant_id' => $etudiant->id,
                'moyenne'     => $moyenne,
                'credits'     => $credits,
            ];

            $etudiant->update(['credits_obtenus' => $credits]);
        }

        foreach ($resultats as $lignes) {
            usort($lignes, fn ($a, $b) => $b['moyenne'] <=> $a['moyenne']);

            foreach ($lignes as $rang => $ligne) {
                $moyenne = $ligne['moyenne'];

                $mention = match (true) {
                    $moyenne >= 16 => 'TB',
                    $moyenne >= 14 => 'B',
                    $moyenne >= 12 => 'AB',
                    $moyenne >= 10 => 'P',
                    default        => 'R',
                };

                \App\Models\Bulletin::updateOrCreate(
                    ['etudiant_id' => $ligne['etudiant_id'], 'semestre' => 1, 'annee_acad' => $annee],
                    [
                        'moyenne_sem'     => $moyenne,
                        'rang'            => $rang + 1,
                        'mention'         => $mention,
                        'credits_obtenus' => $ligne['credits'],
                        'genere_le'       => now(),
                        'genere_par'      => $admin->id,
                    ]
                );
            }
        }
    }
}
