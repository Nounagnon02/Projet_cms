<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('color', 7)->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('usage_count')->default(0); // Compteur d'utilisation
            $table->timestamps();

            $table->index(['slug', 'is_active']);
            $table->index('usage_count');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tags');
    }
};
