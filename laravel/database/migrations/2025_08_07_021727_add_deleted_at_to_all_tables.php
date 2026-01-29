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
        Schema::table('users', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('programs', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('subjects', function (Blueprint $table) {
            $table->softDeletes();
        });
        Schema::table('departments', function (Blueprint $table) {
            $table->softDeletes();
        });
         Schema::table('sub_departments', function (Blueprint $table) {
            $table->softDeletes();
        });
         Schema::table('roles', function (Blueprint $table) {
            $table->softDeletes();
        });
         Schema::table('time_tables', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('programs', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('subjects', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
         Schema::table('departments', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
         Schema::table('sub_departments', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
         Schema::table('roles', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
         Schema::table('time_tables', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

    }
};
