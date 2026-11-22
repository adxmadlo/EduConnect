<?php

namespace Database\Seeders;

use App\Models\Classe;
use App\Models\EmploiDuTemps;
use App\Models\Etudiant;
use App\Models\Mentor;
use App\Models\Module;
use App\Models\Notification;
use App\Models\Reservation;
use App\Models\Salle;
use App\Models\SessionMentorat;
use App\Models\User;
use Illuminate\Database\Seeder;

/** Emplois du temps de la semaine, réservations, mentorat et notifications. */
class VieScolaireSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('role', 'chef_admin')->firstOrFail();
        $creneaux = [
            ['cours1', '08:00', '11:00'],
            ['cours2', '11:15', '13:00'],
            ['cours3', '14:00', '16:45'],
        ];

        $classes = Classe::orderBy('id')->get();
        $salles = Salle::where('type', 'cours')->orderBy('id')->get();
        $debutSemaine = now()->startOfWeek()->addWeek();

        foreach ($classes as $indexClasse => $classe) {
            $modules = $classe->modules()->orderBy('modules.id')->get();

            if ($modules->isEmpty()) {
                continue;
            }

            $jour = 0;

            foreach ($modules as $i => $module) {
                [$type, $heureDebut, $heureFin] = $creneaux[$i % 3];
                $date = $debutSemaine->copy()->addDays(intdiv($i, 3) % 5)->setTimeFromTimeString($heureDebut);
                $fin = $date->copy()->setTimeFromTimeString($heureFin);

                $enseignant = $module->enseignants()->wherePivot('est_principal', true)->first();
                $salle = $salles[($i + $indexClasse) % max(1, $salles->count())] ?? null;

                if (! $salle) {
                    continue;
                }

                EmploiDuTemps::updateOrCreate(
                    [
                        'classe_id'  => $classe->id,
                        'module_id'  => $module->id,
                        'date_debut' => $date,
                    ],
                    [
                        'enseignant_id' => $enseignant?->id,
                        'salle_id'      => $salle->id,
                        'titre'         => $module->nom,
                        'date_fin'      => $fin,
                        'type_creneau'  => $type,
                        'recurrence'    => 'aucune',
                        'cree_par'      => $admin->id,
                    ]
                );

                $jour++;
                if ($jour >= 12) {
                    break; // une semaine de cours suffit pour la démo
                }
            }
        }

        // ── Réservations de salle ─────────────────────────────────
        $salleReunion = Salle::where('type', 'reunion')->first() ?? Salle::first();

        if ($salleReunion) {
            $demandeur = User::where('role', 'enseignant')->first();

            if ($demandeur) {
                Reservation::updateOrCreate(['code_resa' => 'RESA-2026-001'], [
                    'salle_id'     => $salleReunion->id,
                    'demandeur_id' => $demandeur->id,
                    'objet'        => 'Réunion de coordination pédagogique',
                    'participants' => 10,
                    'date_debut'   => now()->addDays(3)->setTime(15, 0),
                    'date_fin'     => now()->addDays(3)->setTime(17, 0),
                    'statut'       => 'confirmee',
                ]);
            }
        }

        // ── Sessions de mentorat ──────────────────────────────────
        $mentors = Mentor::orderBy('id')->get();
        $etudiants = Etudiant::orderBy('id')->limit(6)->get();

        foreach ($mentors as $i => $mentor) {
            $module = $mentor->modules()->first();

            if (! $module) {
                continue;
            }

            foreach ($etudiants->slice($i * 2, 2) as $j => $etudiant) {
                SessionMentorat::updateOrCreate(
                    [
                        'mentor_id'    => $mentor->id,
                        'etudiant_id'  => $etudiant->id,
                        'module_id'    => $module->id,
                        'date_session' => now()->addDays($i + 2)->toDateString(),
                    ],
                    [
                        'type_session' => $j % 2 === 0 ? 'en_ligne' : 'presentiel',
                        'heure_debut'  => '16:00',
                        'heure_fin'    => '17:30',
                        'salle_id'     => $j % 2 === 0 ? null : $salleReunion?->id,
                        'lien_visio'   => $j % 2 === 0 ? 'https://meet.google.com/educonnect-demo' : null,
                        'statut'       => $j === 0 ? 'confirmee' : 'demandee',
                    ]
                );
            }
        }

        // ── Notifications (2 par compte de démonstration) ─────────
        foreach (User::whereIn('role', ['etudiant', 'parent', 'enseignant'])->orderBy('id')->limit(12)->get() as $user) {
            Notification::updateOrCreate(
                ['user_id' => $user->id, 'titre' => 'Bienvenue sur EduConnect'],
                [
                    'type'     => 'systeme',
                    'corps'    => 'Votre compte est actif. Le tableau de bord affiche désormais vos données réelles.',
                    'canal'    => 'in_app',
                    'priorite' => 'normale',
                    'lu'       => false,
                    'envoye_le' => now()->subDay(),
                ]
            );

            Notification::updateOrCreate(
                ['user_id' => $user->id, 'titre' => 'Notes du semestre 1 publiées'],
                [
                    'type'     => 'note',
                    'corps'    => 'Les notes du semestre 1 sont disponibles dans votre espace.',
                    'canal'    => 'in_app',
                    'priorite' => 'haute',
                    'lu'       => false,
                    'envoye_le' => now()->subHours(6),
                ]
            );
        }
    }
}
