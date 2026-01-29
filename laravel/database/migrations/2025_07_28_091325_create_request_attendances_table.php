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
        Schema::create('request_attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->enum('status', ['Approve','Rejected'])->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();

            $table->dateTime('requested_at')->nullable(); // Date and time when the request was made
            $table->dateTime('approved_at')->nullable(); // Date and time when the request
            $table->integer('approved_by')->nullable();
            $table->text('reason')->nullable();
             $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('request_attendances');
    }
};
