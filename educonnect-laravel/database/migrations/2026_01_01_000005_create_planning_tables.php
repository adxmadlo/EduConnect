<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Emplois du temps et réservations de salles. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('emplois_du_temps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('classe_id')->nullable()->constrained('classes')->nullOnDelete();
            $table->foreignId('module_id')->nullable()->constrained('modules')->nullOnDelete();
            $table->foreignId('enseignant_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('salle_id')->constrained('salles')->restrictOnDelete();
            $table->string('titre', 200)->nullable();
            $table->dateTime('date_debut');
            $table->dateTime('date_fin');
            $table->string('type_creneau', 20)->default('cours1');
            $table->string('recurrence', 10)->default('aucune');
            $table->foreignId('cree_par')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['date_debut', 'date_fin']);
            $table->index('classe_id');
            $table->index('salle_id');
        });

        Schema::create('reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('salle_id')->constrained('salles')->restrictOnDelete();
            $table->foreignId('demandeur_id')->constrained('users')->restrictOnDelete();
            $table->string('objet', 300);
            $table->unsignedSmallInteger('participants')->default(1);
            $table->dateTime('date_debut');
            $table->dateTime('date_fin');
            $table->string('statut', 20)->default('en_attente');
            $table->string('code_resa', 20)->unique();
            $table->text('notes_admin')->nullable();
            $table->timestamps();

            $table->index(['date_debut', 'date_fin']);
            $table->index('statut');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservations');
        Schema::dropIfExists('emplois_du_temps');
    }
};
