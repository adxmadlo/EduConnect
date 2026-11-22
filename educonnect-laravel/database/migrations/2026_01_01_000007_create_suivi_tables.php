<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Notifications, journal d'audit et préférences utilisateur. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('type', 20);
            $table->string('titre', 200);
            $table->text('corps')->nullable();
            $table->string('lien', 500)->nullable();
            $table->boolean('lu')->default(false);
            $table->string('canal', 30)->default('in_app');
            $table->string('priorite', 20)->default('normale');
            $table->timestamp('envoye_le')->useCurrent();
            $table->timestamp('lu_le')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'lu']);
            $table->index('type');
            $table->index('priorite');
        });

        Schema::create('audit_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 100);
            $table->string('entite', 50)->nullable();
            $table->unsignedBigInteger('entite_id')->nullable();
            $table->json('detail')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();

            $table->index('action');
            $table->index('user_id');
            $table->index('created_at');
        });

        Schema::create('user_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->boolean('notif_email')->default(true);
            $table->boolean('notif_sms')->default(false);
            $table->boolean('notif_inapp')->default(true);
            $table->string('langue', 5)->default('fr');
            $table->string('theme', 10)->default('auto');
            $table->string('timezone', 50)->default('Africa/Dakar');
            $table->timestamp('updated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_preferences');
        Schema::dropIfExists('audit_log');
        Schema::dropIfExists('notifications');
    }
};
