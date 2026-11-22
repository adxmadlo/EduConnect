<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Scolarité et paiements (Wave, Orange Money, carte, virement, espèces). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('frais_scolarite', function (Blueprint $table) {
            $table->id();
            $table->foreignId('etudiant_id')->constrained('etudiants')->cascadeOnDelete();
            $table->string('annee_acad', 10);
            $table->decimal('montant_total', 12, 2);
            $table->string('plan', 20)->default('trimestriel');
            $table->string('statut', 20)->default('en_attente');
            $table->date('date_echeance')->nullable();
            $table->timestamps();

            $table->unique(['etudiant_id', 'annee_acad'], 'uq_frais');
            $table->index('statut');
        });

        Schema::create('paiements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('frais_id')->constrained('frais_scolarite')->restrictOnDelete();
            $table->foreignId('payeur_id')->constrained('users')->restrictOnDelete();
            $table->decimal('montant', 12, 2);
            $table->string('moyen', 20);                 // wave | orange_money | carte | virement | especes
            $table->string('wave_checkout_id', 200)->nullable();
            $table->decimal('wave_fee', 10, 2)->nullable();
            $table->string('om_order_id', 200)->nullable();
            $table->string('om_token', 500)->nullable();
            $table->string('reference_ext', 200)->nullable();
            $table->string('recu_numero', 50)->unique();
            $table->string('recu_url', 500)->nullable();
            $table->string('qr_code', 500)->nullable();
            $table->json('callback_payload')->nullable();
            $table->string('statut', 20)->default('en_attente');
            $table->text('note')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();

            $table->index('statut');
            $table->index('recu_numero');
            $table->index('reference_ext');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('paiements');
        Schema::dropIfExists('frais_scolarite');
    }
};
