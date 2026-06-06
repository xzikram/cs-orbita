<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cleaning_activity_id')->constrained()->cascadeOnDelete();
            $table->foreignId('area_object_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_checked')->default(false);
            $table->timestamp('checked_at')->nullable();
            $table->timestamps();

            $table->unique(['cleaning_activity_id', 'area_object_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_items');
    }
};
