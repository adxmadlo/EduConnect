<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Table users — reprise exacte du schéma EduConnect v3.
 * Le mot de passe reste dans la colonne `password` (convention Laravel) ;
 * l'ancien `password_hash` devient donc `password` (hash bcrypt identique).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('numero_ucao', 20)->unique()->nullable();
            $table->string('nom', 100);
            $table->string('prenom', 100);
            $table->string('email', 180)->unique();
            $table->string('telephone', 20)->nullable();
            $table->string('password');
            $table->string('role', 20)->default('etudiant')->index();
            $table->string('statut', 20)->default('en_attente')->index();
            $table->string('photo_url', 500)->nullable();
            $table->timestamp('dernier_login')->nullable();
            $table->rememberToken();
            $table->timestamps();

            $table->index('email');
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
    }
};
