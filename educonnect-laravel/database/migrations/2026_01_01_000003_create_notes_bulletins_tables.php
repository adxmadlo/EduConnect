<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Notes et bulletins.
 * note_finale est calculée par NoteService (le trigger MySQL d'origine ne peut pas
 * être porté tel quel sur SQLite : la règle est appliquée côté application).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('etudiant_id')->constrained('etudiants')->cascadeOnDelete();
            $table->foreignId('module_id')->constrained('modules')->restrictOnDelete();
            $table->unsignedTinyInteger('semestre');
            $table->string('annee_acad', 10);
            $table->decimal('note_devoir', 4, 2)->nullable();
            $table->decimal('note_examen', 4, 2)->nullable();
            $table->decimal('note_finale', 4, 2)->nullable();
            $table->boolean('publie')->default(false);
            $table->timestamp('publie_le')->nullable();
            $table->foreignId('saisie_par')->constrained('users')->restrictOnDelete();
            $table->timestamp('saisie_le')->useCurrent();
            $table->timestamp('modifiee_le')->nullable();
            $table->timestamps();

            $table->unique(['etudiant_id', 'module_id', 'semestre', 'annee_acad'], 'uq_note');
            $table->index(['semestre', 'annee_acad']);
            $table->index('publie');
            $table->index('etudiant_id');
        });

        Schema::create('bulletins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('etudiant_id')->constrained('etudiants')->cascadeOnDelete();
            $table->unsignedTinyInteger('semestre');
            $table->string('annee_acad', 10);
            $table->decimal('moyenne_sem', 4, 2)->nullable();
            $table->unsignedSmallInteger('rang')->nullable();
            $table->string('mention', 2)->nullable();   // TB | B | AB | P | R
            $table->unsignedTinyInteger('credits_obtenus')->nullable();
            $table->string('pdf_url', 500)->nullable();
            $table->string('cachet_hash', 128)->nullable();
            $table->timestamp('genere_le')->useCurrent();
            $table->foreignId('genere_par')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['etudiant_id', 'semestre', 'annee_acad'], 'uq_bulletin');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bulletins');
        Schema::dropIfExists('notes');
    }
};
