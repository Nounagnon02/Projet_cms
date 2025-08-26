<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->longText('value')->nullable();
            $table->string('type')->default('string'); // string, integer, boolean, json, array
            $table->string('group')->default('general'); // general, seo, social, etc.
            $table->string('label');
            $table->text('description')->nullable();
            $table->json('options')->nullable(); // Pour les sélects, etc.
            $table->boolean('is_public')->default(false); // Accessible côté frontend
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index(['group', 'sort_order']);
            $table->index('is_public');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
