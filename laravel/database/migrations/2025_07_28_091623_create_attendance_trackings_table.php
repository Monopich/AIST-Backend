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
        Schema::create('attendance_trackings', function (Blueprint $table) {
            $table->id();
            $table->time('check_in_time')->nullable();
            $table->time('check_out_time')->nullable();
            $table->string('device')->nullable(); // Device used for attendance
            $table->dateTime('scanned_at')->nullable();
            $table->enum('status', ['Present','Late', 'On leave'])->default('Present');

            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('qr_code_id')->nullable()->constrained('qr_codes')->onDelete('cascade');

            $table->foreignId('request_attendance_id')->nullable()->constrained('request_attendances')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendance_trackings');
    }
};
