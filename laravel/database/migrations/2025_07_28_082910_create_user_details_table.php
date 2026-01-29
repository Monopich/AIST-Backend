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
        Schema::create('user_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('id_card')->unique();
            $table->boolean('is_active')->default(true);
            $table->foreignId('department_id')->constrained('departments')->onDelete('cascade');
            $table->foreignId('sub_department_id')->nullable()->constrained('sub_departments')->onDelete('cascade');
            // $table->foreignId('semester_id')->constrained('semesters')->onDelete('cascade');
            $table->text('khmer_first_name')->nullable();
            $table->text('khmer_last_name')->nullable();
            $table->text('latin_name')->nullable();
            $table->text('khmer_name')->nullable();
            $table->text('address')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->text('origin')->nullable();
            $table->text('profile_picture')->nullable();
            $table->enum('gender', ['Male','Female']);
            $table->string('phone_number')->nullable();
            $table->boolean('special')->default(false);
            $table->text('bio')->nullable();


            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_details');
    }
};
