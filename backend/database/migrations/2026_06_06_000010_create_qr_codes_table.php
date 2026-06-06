<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('qr_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('area_id')->constrained()->cascadeOnDelete();
            $table->uuid('uuid')->unique();
            $table->string('code', 50)->unique();
            $table->text('qr_data')->nullable();
            $table->integer('version')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamp('generated_at')->nullable();
            $table->timestamps();

            $table->index('uuid');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qr_codes');
    }
};
