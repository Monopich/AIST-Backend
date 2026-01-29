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
        Schema::table('subjects', function (Blueprint $table) {
             if (Schema::hasColumn('subjects', 'sub_department_id')) {
                $table->dropForeign(['sub_department_id']);
                $table->dropColumn('sub_department_id');
            }
            $table->foreignId('department_id')->nullable()->constrained('departments')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subjects', function (Blueprint $table) {
            $table->foreignId('sub_department_id')->nullable()->constrained('sub_departments')->onDelete('cascade');
            $table->dropForeign(['department_id']);
            $table->dropColumn('department_id');
        });
    }
};
