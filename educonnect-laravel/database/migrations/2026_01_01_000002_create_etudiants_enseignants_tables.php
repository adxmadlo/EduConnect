<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Profils étudiants / enseignants et affectations des modules. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('etudiants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->foreignId('classe_id')->constrained('classes')->restrictOnDelete();
            $table->date('date_naissance')->nullable();
            $table->string('adresse', 300)->nullable();
            $table->foreignId('parent_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('credits_obtenus')->default(0);
            $table->unsignedInteger('credits_requis')->default(30);
            $table->string('statut_academique', 20)->default('regulier');
            $table->timestamps();

            $table->index('classe_id');
        });

        Schema::create('enseignants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->string('grade', 100)->nullable();
            $table->string('departement', 100)->nullable();
            $table->string('bureau', 50)->nullable();
            $table->timestamps();
        });

        Schema::create('module_enseignants', function (Blueprint $table) {
            $table->foreignId('module_id')->constrained('modules')->cascadeOnDelete();
            $table->foreignId('enseignant_id')->constrained('users')->cascadeOnDelete();
            $table->boolean('est_principal')->default(true);

            $table->primary(['module_id', 'enseignant_id']);
        });

        Schema::create('module_classes', function (Blueprint $table) {
            $table->foreignId('module_id')->constrained('modules')->cascadeOnDelete();
            $table->foreignId('classe_id')->constrained('classes')->cascadeOnDelete();
            $table->string('annee_acad', 10);

            $table->primary(['module_id', 'classe_id', 'annee_acad']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('module_classes');
        Schema::dropIfExists('module_enseignants');
        Schema::dropIfExists('enseignants');
        Schema::dropIfExists('etudiants');
    }
};
