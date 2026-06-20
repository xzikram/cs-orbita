<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_access_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('audit_session_id')->constrained('audit_sessions')->onDelete('cascade');
            $table->string('report_type'); // e.g. daily-checklist, matrix-excel
            $table->json('details')->nullable();
            $table->dateTime('accessed_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_access_logs');
    }
};
