<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('file_name');
            $table->string('mime_type');
            $table->string('path');
            $table->string('disk')->default('public');
            $table->unsignedBigInteger('size'); // Taille en bytes

            // Métadonnées pour les images
            $table->integer('width')->nullable();
            $table->integer('height')->nullable();

            // Organisation
            $table->string('alt_text')->nullable();
            $table->text('description')->nullable();
            $table->string('folder')->nullable();

            // Relations
            $table->foreignId('uploaded_by')->constrained('users')->onDelete('cascade');

            // Statistiques
            $table->integer('downloads_count')->default(0);

            $table->timestamps();

            $table->index(['mime_type', 'created_at']);
            $table->index(['uploaded_by', 'created_at']);
            $table->index('folder');
        });

        // Table pour les relations polymorphiques média
        Schema::create('mediables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('media_id')->constrained()->onDelete('cascade');
            $table->morphs('mediable'); // mediable_type, mediable_id
            $table->string('collection')->default('default');
            $table->integer('order')->default(0);
            $table->timestamps();

            $table->index(['mediable_type', 'mediable_id']);
            $table->index(['collection', 'order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mediables');
        Schema::dropIfExists('media');
    }
};
