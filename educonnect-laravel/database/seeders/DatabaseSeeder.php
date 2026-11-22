<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

/**
 * `php artisan migrate --seed` reconstruit une base de démonstration complète.
 * Ordre imposé par les clés étrangères : référentiels → comptes → pédagogie → finances → vie scolaire.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Repartir d'une base vide pour que le seed soit reproductible
        Schema::disableForeignKeyConstraints();

        foreach ([
            'notifications', 'audit_log', 'user_preferences',
            'ressources_pedagogiques',
            'sessions_mentorat', 'mentor_modules', 'stages', 'mentors',
            'reservations', 'emplois_du_temps',
            'paiements', 'frais_scolarite',
            'bulletins', 'notes',
            'module_classes', 'module_enseignants',
            'etudiants', 'enseignants',
            'modules', 'salles', 'classes',
            'sessions', 'password_reset_tokens', 'users',
        ] as $table) {
            if (Schema::hasTable($table)) {
                \Illuminate\Support\Facades\DB::table($table)->delete();
            }
        }

        Schema::enableForeignKeyConstraints();

        $this->call([
            ReferentielSeeder::class,
            UtilisateurSeeder::class,
            PedagogieSeeder::class,
            RessourceSeeder::class,
            FinanceSeeder::class,
            VieScolaireSeeder::class,
        ]);
    }
}
