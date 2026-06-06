<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cleaning_activity_id')->constrained()->cascadeOnDelete();
            $table->string('type', 10)->default('after'); // before, after
            $table->string('file_path');
            $table->integer('file_size')->default(0);
            $table->string('original_name')->nullable();
            $table->string('mime_type', 50)->nullable();
            $table->timestamp('uploaded_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_photos');
    }
};
