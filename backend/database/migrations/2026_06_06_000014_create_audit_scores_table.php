<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cleaning_activity_id')->constrained()->cascadeOnDelete();
            $table->foreignId('auditor_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedTinyInteger('kebersihan_score')->default(0);
            $table->unsignedTinyInteger('kerapihan_score')->default(0);
            $table->unsignedTinyInteger('kepatuhan_sop_score')->default(0);
            $table->unsignedTinyInteger('total_score')->default(0);
            $table->text('notes')->nullable();
            $table->string('status', 20)->default('pending'); // pending, passed, failed
            $table->timestamp('audited_at')->nullable();
            $table->timestamps();

            $table->index('total_score');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_scores');
    }
};
