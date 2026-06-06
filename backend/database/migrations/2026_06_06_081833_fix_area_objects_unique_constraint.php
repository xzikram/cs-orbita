<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('area_objects', function (Blueprint $table) {
            $table->dropForeign(['area_id']);
            $table->dropUnique('area_objects_area_id_cleaning_object_id_unique');
            $table->unique(['area_id', 'cleaning_object_id', 'room_name'], 'area_objects_unique');
            $table->foreign('area_id')->references('id')->on('areas')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::table('area_objects', function (Blueprint $table) {
            $table->dropForeign(['area_id']);
            $table->dropUnique('area_objects_unique');
            $table->unique(['area_id', 'cleaning_object_id']);
            $table->foreign('area_id')->references('id')->on('areas')->onDelete('cascade');
        });
    }
};
