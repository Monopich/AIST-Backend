<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {

            if (!Schema::hasColumn('users', 'temp_student_id')) {
                $table->foreignId('temp_student_id')
                    ->nullable()
                    ->unique()
                    ->after('id')
                    ->constrained('temp_students')
                    ->nullOnDelete();
            }

            if (!Schema::hasColumn('users', 'role_id')) {
                $table->unsignedBigInteger('role_id')
                    ->after('password');
            }
        });
    }


    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {

            if (Schema::hasColumn('users', 'temp_student_id')) {
                $table->dropForeign(['temp_student_id']);
                $table->dropColumn('temp_student_id');
            }

            if (Schema::hasColumn('users', 'role_id')) {
                $table->dropColumn('role_id');
            }
        });
    }

};
