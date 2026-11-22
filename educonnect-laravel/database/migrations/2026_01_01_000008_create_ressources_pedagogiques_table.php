<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Ressources pédagogiques : supports déposés par les enseignants, mentors et l'administration. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ressources_pedagogiques', function (Blueprint $table) {
            $table->id();
            $table->foreignId('module_id')->nullable()->constrained('modules')->nullOnDelete();
            $table->foreignId('classe_id')->nullable()->constrained('classes')->nullOnDelete();
            $table->foreignId('deposant_id')->constrained('users')->restrictOnDelete();
            $table->string('titre', 200);
            $table->text('description')->nullable();
            $table->string('type', 20)->default('cours');
            $table->string('fichier_path', 255)->nullable();
            $table->string('fichier_nom', 255)->nullable();
            $table->string('lien_url', 255)->nullable();
            $table->string('annee_acad', 10);
            $table->boolean('visible')->default(true);
            $table->unsignedInteger('telechargements')->default(0);
            $table->timestamps();

            $table->index('module_id');
            $table->index('visible');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ressources_pedagogiques');
    }
};
