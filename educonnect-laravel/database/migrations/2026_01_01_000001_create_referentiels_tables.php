<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Référentiels : classes, salles, modules (LMD). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('classes', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('nom', 100);
            $table->string('filiere', 100);
            $table->unsignedTinyInteger('annee_etude');
            $table->unsignedTinyInteger('capacite')->default(40);
            $table->string('annee_acad', 10);
            $table->timestamps();
        });

        Schema::create('salles', function (Blueprint $table) {
            $table->id();
            $table->string('nom', 50)->unique();
            $table->string('batiment', 30);              // UCAO | Claire_Afrique
            $table->unsignedTinyInteger('etage');
            $table->string('type', 20)->default('cours');
            $table->unsignedSmallInteger('capacite')->default(30);
            $table->json('equipements')->nullable();
            $table->boolean('actif')->default(true);
            $table->timestamps();

            $table->index('batiment');
            $table->index('etage');
        });

        Schema::create('modules', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('nom', 200);
            $table->string('filiere', 100);
            $table->unsignedTinyInteger('annee_etude');
            $table->unsignedTinyInteger('semestre');
            $table->string('type_eval', 10)->default('30_70'); // 100 | 30_70
            $table->unsignedTinyInteger('credits')->default(3);
            $table->unsignedTinyInteger('coefficient')->default(1);
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index('filiere');
            $table->index(['semestre', 'annee_etude']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('modules');
        Schema::dropIfExists('salles');
        Schema::dropIfExists('classes');
    }
};
