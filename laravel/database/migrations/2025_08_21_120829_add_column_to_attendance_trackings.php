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
            $table->foreignId('leave_request_id')->nullable()->constrained('leave_requests')->onDelete('cascade');

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attendance_trackings', function (Blueprint $table) {
            $table->dropForeign('leave_request_id');
            $table->dropColumn('leave_request_id');
        });
    }
};
