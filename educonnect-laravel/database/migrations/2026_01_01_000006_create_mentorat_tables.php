<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Mentorat (validation en 4 étapes) et stages. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mentors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->text('bio')->nullable();
            $table->string('tarif', 20)->default('gratuit');
            $table->decimal('note_moyenne', 3, 2)->default(0);
            $table->unsignedInteger('nb_sessions')->default(0);
            $table->unsignedInteger('nb_etudiants')->default(0);
            $table->decimal('taux_satisfaction', 3, 2)->default(0);
            $table->boolean('etape_temoignage')->default(false);
            $table->boolean('etape_notes')->default(false);
            $table->boolean('etape_entretien')->default(false);
            $table->boolean('etape_quiz')->default(false);
            $table->decimal('score_quiz', 5, 2)->nullable();
            $table->timestamp('quiz_passe_le')->nullable();
            $table->timestamp('quiz_retentable_le')->nullable();
            $table->foreignId('valide_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('valide_le')->nullable();
            $table->boolean('actif')->default(false);
            $table->timestamps();
        });

        Schema::create('mentor_modules', function (Blueprint $table) {
            $table->foreignId('mentor_id')->constrained('mentors')->cascadeOnDelete();
            $table->foreignId('module_id')->constrained('modules')->cascadeOnDelete();
            $table->decimal('note_obtenue', 4, 2);
            $table->text('temoignage_txt')->nullable();

            $table->primary(['mentor_id', 'module_id']);
        });

        Schema::create('sessions_mentorat', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mentor_id')->constrained('mentors')->restrictOnDelete();
            $table->foreignId('etudiant_id')->constrained('etudiants')->restrictOnDelete();
            $table->foreignId('module_id')->constrained('modules')->restrictOnDelete();
            $table->string('type_session', 20)->default('en_ligne');
            $table->date('date_session');
            $table->time('heure_debut');
            $table->time('heure_fin');
            $table->foreignId('salle_id')->nullable()->constrained('salles')->nullOnDelete();
            $table->string('lien_visio', 500)->nullable();
            $table->string('statut', 20)->default('demandee');
            $table->unsignedTinyInteger('note_etudiant')->nullable();
            $table->text('commentaire')->nullable();
            $table->timestamps();

            $table->index('date_session');
            $table->index('statut');
            $table->index('mentor_id');
            $table->index('etudiant_id');
        });

        Schema::create('stages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('etudiant_id')->constrained('etudiants')->cascadeOnDelete();
            $table->string('entreprise', 200);
            $table->string('poste', 200)->nullable();
            $table->date('date_debut')->nullable();
            $table->date('date_fin')->nullable();
            $table->string('statut', 20)->default('candidat');
            $table->foreignId('recommande_par')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('score_profil', 5, 2)->nullable();
            $table->boolean('admin_valide')->default(false);
            $table->string('contact_entreprise', 200)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('statut');
            $table->index('etudiant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stages');
        Schema::dropIfExists('sessions_mentorat');
        Schema::dropIfExists('mentor_modules');
        Schema::dropIfExists('mentors');
    }
};
