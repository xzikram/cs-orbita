<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_findings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('audit_score_id')->constrained()->cascadeOnDelete();
            $table->foreignId('area_id')->constrained();
            $table->string('category', 30); // kebersihan, kerapihan, kepatuhan_sop
            $table->text('description');
            $table->string('photo_path')->nullable();
            $table->string('status', 20)->default('open'); // open, resolved
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_findings');
    }
};
