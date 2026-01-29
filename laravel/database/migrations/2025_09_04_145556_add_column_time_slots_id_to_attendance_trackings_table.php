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
        Schema::table('attendance_trackings', function (Blueprint $table) {
            $table->foreignId('time_slot_id')->nullable()->constrained('time_slots')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attendance_trackings', function (Blueprint $table) {
            $table->dropForeign('time_slots');
            $table->dropColumn('time_slots');
        });
    }
};
