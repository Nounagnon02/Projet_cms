<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Table des menus
        Schema::create('menus', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('location')->nullable(); // header, footer, sidebar, etc.
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['location', 'is_active']);
        });

        // Table des éléments de menu
        Schema::create('menu_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('menu_id')->constrained()->onDelete('cascade');
            $table->string('title');
            $table->string('url')->nullable();
            $table->string('route')->nullable(); // Route Laravel
            $table->json('route_params')->nullable(); // Paramètres de route

            // Relations polymorphiques (vers pages, posts, etc.)
            $table->morphs('linkable', 'menu_items_linkable_index'); // linkable_type, linkable_id

            // Hiérarchie
            $table->foreignId('parent_id')->nullable()->constrained('menu_items')->onDelete('cascade');
            $table->integer('sort_order')->default(0);

            // Options
            $table->string('target')->default('_self'); // _self, _blank, etc.
            $table->string('css_class')->nullable();
            $table->string('icon')->nullable();
            $table->boolean('is_active')->default(true);

            // Conditions d'affichage
            $table->json('visibility_rules')->nullable(); // Règles de visibilité

            $table->timestamps();

            $table->index(['menu_id', 'parent_id', 'sort_order']);
            $table->index(['linkable_type', 'linkable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_items');
        Schema::dropIfExists('menus');
    }
};
