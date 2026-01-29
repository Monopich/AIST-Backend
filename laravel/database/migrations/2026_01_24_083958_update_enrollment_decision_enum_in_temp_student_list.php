<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE temp_student_list MODIFY enrollment_decision ENUM('selected', 'not_selected', 'enrolled')");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE temp_student_list MODIFY enrollment_decision ENUM('selected', 'not_selected')");
    }
};
