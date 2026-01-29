<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('temp_student_list', function (Blueprint $table) {
            $table->id();

            $table->foreignId('temp_student_id')
                ->constrained('temp_students')
                ->cascadeOnDelete();

            $table->foreignId('import_score_id')
                ->constrained('import_scores')
                ->cascadeOnDelete();

            $table->integer('academic_year');
            $table->integer('rank');
            $table->decimal('score', 5, 2);

            $table->enum('enrollment_decision', ['selected', 'not_selected']);

            $table->timestamps();

            // prevent same student applying twice in same year
            $table->unique(['temp_student_id', 'academic_year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('temp_student_list');
    }
};