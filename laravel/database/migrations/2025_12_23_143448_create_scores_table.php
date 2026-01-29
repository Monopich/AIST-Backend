<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_program_id')->constrained('user_programs')->onDelete('cascade');
            $table->foreignId('subject_id')->constrained('subjects')->onDelete('cascade');
            $table->decimal('score', 5, 2); // e.g., 100.00 max
            $table->timestamps();

            $table->unique(['user_program_id', 'subject_id']); // one score per student per subject
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scores');
    }
};

