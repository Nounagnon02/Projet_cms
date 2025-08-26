<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pages', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->longText('content');
            $table->text('excerpt')->nullable();

            // Hiérarchie des pages
            $table->foreignId('parent_id')->nullable()->constrained('pages')->onDelete('cascade');
            $table->integer('sort_order')->default(0);

            // Statut et visibilité
            $table->enum('status', ['draft', 'published', 'private'])->default('draft');
            $table->boolean('show_in_menu')->default(true);
            $table->string('menu_title')->nullable(); // Titre différent pour le menu

            // Relations
            $table->foreignId('author_id')->constrained('users')->onDelete('cascade');

            // Template et layout
            $table->string('template')->nullable();
            $table->json('content_blocks')->nullable();

            // SEO
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->text('meta_keywords')->nullable();

            // Statistiques
            $table->integer('views_count')->default(0);

            $table->timestamps();

            $table->index(['status', 'sort_order']);
            $table->index(['parent_id', 'sort_order']);
            $table->index('show_in_menu');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pages');
    }
};
