<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();

            // Action effectuée
            $table->string('action'); // create, update, delete, login, etc.
            $table->text('description');

            // Utilisateur qui a effectué l'action
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');
            $table->string('user_name')->nullable(); // Backup si user supprimé

            // Modèle concerné (polymorphique)
            $table->morphs('loggable'); // loggable_type, loggable_id

            // Données avant/après (pour les updates)
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();

            // Métadonnées
            $table->ipAddress('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->string('url')->nullable();
            $table->string('method')->nullable(); // GET, POST, etc.

            // Catégorisation
            $table->string('level')->default('info'); // info, warning, error
            $table->string('category')->nullable(); // auth, content, admin, etc.

            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['loggable_type', 'loggable_id']);
            $table->index(['action', 'created_at']);
            $table->index(['level', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
