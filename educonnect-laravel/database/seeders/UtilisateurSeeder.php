<?php

namespace Database\Seeders;

use App\Models\Classe;
use App\Models\Enseignant;
use App\Models\Etudiant;
use App\Models\Mentor;
use App\Models\Module;
use App\Models\User;
use App\Models\UserPreference;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Comptes de démonstration.
 * Mot de passe universel : EduConnect2026! (comme README_DEMO.txt).
 * Les noms reprennent ceux de educonnect_final.sql ; les étudiants sont générés
 * à partir d'un vivier de prénoms/noms sénégalais pour éviter 120 lignes recopiées.
 */
class UtilisateurSeeder extends Seeder
{
    public const MOT_DE_PASSE = 'EduConnect2026!';

    public function run(): void
    {
        $motDePasse = Hash::make(self::MOT_DE_PASSE);

        // ── Direction ────────────────────────────────────────────────
        $direction = [
            ['UCAO-ADM-001', 'Bilong', 'Marie', 'bilong@ucao.edu.sn', '+221771000001', 'chef_admin'],
            ['UCAO-ADM-002', 'Traoré', 'Moussa', 'traore@ucao.edu.sn', '+221771000002', 'admin'],
        ];

        foreach ($direction as [$numero, $nom, $prenom, $email, $tel, $role]) {
            User::updateOrCreate(['email' => $email], [
                'numero_ucao' => $numero, 'nom' => $nom, 'prenom' => $prenom,
                'telephone' => $tel, 'password' => $motDePasse, 'role' => $role, 'statut' => 'actif',
            ]);
        }

        // ── Enseignants (13, comme le schéma d'origine) ──────────────
        $enseignants = [
            ['UCAO-PROF-01', 'Ndiaye', 'Ibrahima', 'i.ndiaye@ucao.edu.sn', 'Maître de Conférences', 'Informatique'],
            ['UCAO-PROF-02', 'Sall', 'Aminata', 'a.sall@ucao.edu.sn', 'Assistant', 'Langues'],
            ['UCAO-PROF-03', 'Diop', 'Mamadou', 'm.diop@ucao.edu.sn', 'Maître de Conférences', 'Informatique'],
            ['UCAO-PROF-04', 'Fall', 'Rokhaya', 'r.fall@ucao.edu.sn', 'Assistant', 'Informatique'],
            ['UCAO-PROF-05', 'Touré', 'Seydou', 's.toure@ucao.edu.sn', 'Maître de Conférences', 'Informatique'],
            ['UCAO-PROF-06', 'Kane', 'Fatou', 'f.kane@ucao.edu.sn', 'Assistant', 'Mathématiques'],
            ['UCAO-PROF-07', 'Sarr', 'Cheikh', 'c.sarr@ucao.edu.sn', 'Maître de Conférences', 'Mathématiques'],
            ['UCAO-PROF-08', 'Mbaye', 'Aissatou', 'a.mbaye@ucao.edu.sn', 'Assistant', 'Comptabilité'],
            ['UCAO-PROF-09', 'Gaye', 'Lamine', 'l.gaye@ucao.edu.sn', 'Maître de Conférences', 'Économie'],
            ['UCAO-PROF-10', 'Diouf', 'Ndéye', 'n.diouf@ucao.edu.sn', 'Assistant', 'Langues'],
            ['UCAO-PROF-11', 'Cissé', 'Babacar', 'b.cisse@ucao.edu.sn', 'Assistant', 'Informatique'],
            ['UCAO-PROF-12', 'Ba', 'Mariama', 'm.ba@ucao.edu.sn', 'Maître de Conférences', 'Gestion'],
            ['UCAO-PROF-13', 'Faye', 'Omar', 'o.faye@ucao.edu.sn', 'Assistant', 'Informatique'],
        ];

        $utilisateursEnseignants = [];

        foreach ($enseignants as $i => [$numero, $nom, $prenom, $email, $grade, $departement]) {
            $user = User::updateOrCreate(['email' => $email], [
                'numero_ucao' => $numero, 'nom' => $nom, 'prenom' => $prenom,
                'telephone'   => '+2217710000' . str_pad((string) (11 + $i), 2, '0', STR_PAD_LEFT),
                'password'    => $motDePasse, 'role' => 'enseignant', 'statut' => 'actif',
            ]);

            Enseignant::updateOrCreate(['user_id' => $user->id], [
                'grade'       => $grade,
                'departement' => $departement,
                'bureau'      => 'Bureau ' . (100 + $i),
            ]);

            $utilisateursEnseignants[] = $user;
        }

        // Attribution des modules : un principal par module + un secondaire sur un module sur trois
        $modules = Module::orderBy('id')->get();

        foreach ($modules as $i => $module) {
            $principal = $utilisateursEnseignants[$i % count($utilisateursEnseignants)];
            $module->enseignants()->syncWithoutDetaching([
                $principal->id => ['est_principal' => true],
            ]);

            if ($i % 3 === 0) {
                $secondaire = $utilisateursEnseignants[($i + 4) % count($utilisateursEnseignants)];
                if ($secondaire->id !== $principal->id) {
                    $module->enseignants()->syncWithoutDetaching([
                        $secondaire->id => ['est_principal' => false],
                    ]);
                }
            }
        }

        // ── Étudiants : 20 par classe ───────────────────────────────
        $prenoms = ['Adama', 'Marie', 'Ibou', 'Aïssa', 'Mamadou', 'Fatou', 'Cheikh', 'Ndéye', 'Seydou',
            'Aminata', 'Lamine', 'Rokhaya', 'Omar', 'Bineta', 'Pape', 'Mariama', 'Ibrahima', 'Khadija',
            'Moussa', 'Kadiatou', 'Aliou', 'Aïchatou', 'Souleymane', 'Fatoumata', 'Awa', 'Bakary',
            'Hawa', 'Tenin', 'Drissa', 'Safiatou', 'Oumou', 'Bintou', 'Youssouf', 'Maïmouna', 'Rougui'];

        $noms = ['Diallo', 'Sow', 'Ndiaye', 'Fall', 'Diouf', 'Sarr', 'Ba', 'Gaye', 'Touré', 'Cissé',
            'Kane', 'Mbaye', 'Diop', 'Faye', 'Ly', 'Niang', 'Dieng', 'Seck', 'Konaté', 'Traoré',
            'Balde', 'Camara', 'Barry', 'Dramé', 'Sylla', 'Bah', 'Coulibaly', 'Keïta', 'Doumbia', 'Sanogo'];

        $classes = Classe::orderBy('id')->get();
        $compteur = 1;

        foreach ($classes as $classe) {
            for ($i = 0; $i < 20; $i++) {
                $prenom = $prenoms[($classe->id * 7 + $i * 3) % count($prenoms)];
                $nom    = $noms[($classe->id * 5 + $i * 11) % count($noms)];

                $slug = Str::slug(Str::ascii($prenom . '.' . $nom)) . $compteur;
                $email = $slug . '@etud.ucao.sn';

                $user = User::updateOrCreate(['email' => $email], [
                    'numero_ucao' => sprintf('UCAO-2026-%03d', $compteur),
                    'nom'         => $nom,
                    'prenom'      => $prenom,
                    'telephone'   => sprintf('+221770%06d', 100000 + $compteur),
                    'password'    => $motDePasse,
                    'role'        => 'etudiant',
                    'statut'      => 'actif',
                ]);

                Etudiant::updateOrCreate(['user_id' => $user->id], [
                    'classe_id'         => $classe->id,
                    'date_naissance'    => now()->subYears(19)->subDays($compteur * 11)->toDateString(),
                    'adresse'           => 'Dakar, Sénégal',
                    'credits_requis'    => 30,
                    'statut_academique' => 'regulier',
                ]);

                $compteur++;
            }
        }

        // ── Parents (3) reliés aux 3 premiers étudiants ─────────────
        $parents = [
            ['UCAO-PAR-001', 'Diallo', 'Oumar', 'oumar.diallo@gmail.com', '+221771200001'],
            ['UCAO-PAR-002', 'Sow', 'Mamadou', 'mamadou.sow@gmail.com', '+221771200002'],
            ['UCAO-PAR-003', 'Ndiaye', 'Rokhaya', 'rokhaya.ndiaye@gmail.com', '+221771200003'],
        ];

        $premiersEtudiants = Etudiant::orderBy('id')->limit(3)->get();

        foreach ($parents as $i => [$numero, $nom, $prenom, $email, $tel]) {
            $parent = User::updateOrCreate(['email' => $email], [
                'numero_ucao' => $numero, 'nom' => $nom, 'prenom' => $prenom,
                'telephone' => $tel, 'password' => $motDePasse, 'role' => 'parent', 'statut' => 'actif',
            ]);

            if (isset($premiersEtudiants[$i])) {
                $premiersEtudiants[$i]->update(['parent_id' => $parent->id]);
            }
        }

        // ── Mentors (3) + profils validés ──────────────────────────
        $mentors = [
            ['UCAO-MENT-01', 'Ndiaye', 'Souleymane', 'souleymane.ndiaye.mentor@ucao.sn',
                'Expert en algorithmes et programmation C#. Toujours disponible.', 4.80, 24, 8, 0.96, 87.50],
            ['UCAO-MENT-02', 'Sarr', 'Mariama', 'mariama.sarr.mentor@ucao.sn',
                'Passionnée par les maths et la comptabilité. Pédagogie claire.', 4.60, 18, 6, 0.93, 82.00],
            ['UCAO-MENT-03', 'Diallo', 'Ibrahima', 'ibrahima.diallo.mentor@ucao.sn',
                'Spécialiste web et SGBDR. Cours pratiques orientés projets.', 4.90, 31, 10, 0.97, 91.00],
        ];

        $modulesMentorat = [
            ['IG-L1S1-03', 'IG-L1S1-05'],
            ['IG-L1S1-06', 'IG-L1S1-08'],
            ['IG-L1S2-07', 'IG-L1S2-09'],
        ];

        $admin = User::where('role', 'chef_admin')->first();

        foreach ($mentors as $i => [$numero, $nom, $prenom, $email, $bio, $note, $nbSessions, $nbEtudiants, $satisfaction, $scoreQuiz]) {
            $user = User::updateOrCreate(['email' => $email], [
                'numero_ucao' => $numero, 'nom' => $nom, 'prenom' => $prenom,
                'telephone'   => '+22177130000' . ($i + 1),
                'password'    => $motDePasse, 'role' => 'mentor', 'statut' => 'actif',
            ]);

            $mentor = Mentor::updateOrCreate(['user_id' => $user->id], [
                'bio'               => $bio,
                'tarif'             => 'gratuit',
                'note_moyenne'      => $note,
                'nb_sessions'       => $nbSessions,
                'nb_etudiants'      => $nbEtudiants,
                'taux_satisfaction' => $satisfaction,
                'etape_temoignage'  => true,
                'etape_notes'       => true,
                'etape_entretien'   => true,
                'etape_quiz'        => true,
                'score_quiz'        => $scoreQuiz,
                'quiz_passe_le'     => now()->subMonth(),
                'valide_par'        => $admin?->id,
                'valide_le'         => now()->subWeeks(3),
                'actif'             => true,
            ]);

            foreach ($modulesMentorat[$i] as $code) {
                $module = Module::where('code', $code)->first();
                if ($module) {
                    $mentor->modules()->syncWithoutDetaching([
                        $module->id => [
                            'note_obtenue'   => 16.00 + $i,
                            'temoignage_txt' => 'Maîtrise confirmée par l\'enseignant du module.',
                        ],
                    ]);
                }
            }
        }

        // ── Préférences : tous les comptes actifs ──────────────────
        foreach (User::where('statut', 'actif')->get() as $user) {
            UserPreference::updateOrCreate(['user_id' => $user->id], [
                'notif_email' => true,
                'notif_sms'   => $user->id % 2 === 0,
                'notif_inapp' => true,
                'langue'      => 'fr',
                'theme'       => 'auto',
                'timezone'    => 'Africa/Dakar',
                'updated_at'  => now(),
            ]);
        }
    }
}
