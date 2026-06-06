<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cleaning_activities', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('area_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shift_id')->constrained();
            $table->foreignId('schedule_id')->nullable()->constrained('area_schedules')->nullOnDelete();
            $table->date('date');
            $table->time('start_time');
            $table->time('end_time')->nullable();
            $table->text('notes')->nullable();
            $table->string('status', 20)->default('in_progress');
            $table->string('sync_status', 20)->default('synced');
            $table->boolean('is_late')->default(false);
            $table->integer('late_minutes')->default(0);
            $table->timestamp('submitted_at')->nullable();
            $table->string('device_id')->nullable();
            $table->timestamps();

            $table->index(['area_id', 'date', 'shift_id']);
            $table->index(['user_id', 'date']);
            $table->index(['date', 'status']);
            $table->index('sync_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cleaning_activities');
    }
};
