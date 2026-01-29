<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('temp_students', function (Blueprint $table) {
            $table->id(); // primary key

            $table->string('khmer_name');
            $table->string('latin_name');
            $table->string('profile_picture')->nullable();

            $table->string('gender', 10);
            $table->date('date_of_birth');

            $table->string('phone_number')->unique();
            $table->string('origin')->nullable();

            $table->unsignedBigInteger('department_id');
            $table->unsignedBigInteger('program_id');

            $table->timestamps(); // created_at & updated_at
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('temp_students');
    }
};
