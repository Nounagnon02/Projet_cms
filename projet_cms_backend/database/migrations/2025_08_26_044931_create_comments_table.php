<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comments', function (Blueprint $table) {
            $table->id();
            $table->longText('content');

            // Relations polymorphiques (peut commenter posts, pages, etc.)
            $table->morphs('commentable'); // commentable_type, commentable_id

            // Auteur (utilisateur ou invité)
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('guest_name')->nullable();
            $table->string('guest_email')->nullable();
            $table->string('guest_website')->nullable();
            $table->ipAddress('ip_address')->nullable();
            $table->text('user_agent')->nullable();

            // Hiérarchie des commentaires (réponses)
            $table->foreignId('parent_id')->nullable()->constrained('comments')->onDelete('cascade');

            // Statut et modération
            $table->enum('status', ['pending', 'approved', 'rejected', 'spam'])->default('pending');
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->onDelete('set null');

            // Statistiques
            $table->integer('likes_count')->default(0);
            $table->integer('replies_count')->default(0);

            $table->timestamps();

            $table->index(['commentable_type', 'commentable_id']);
            $table->index(['status', 'created_at']);
            $table->index(['parent_id', 'created_at']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comments');
    }
};
