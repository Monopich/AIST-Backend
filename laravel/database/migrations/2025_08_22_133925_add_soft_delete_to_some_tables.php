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
         Schema::table('user_details', function (Blueprint $table) {
            $table->softDeletes();
        });
        Schema::table('groups', function (Blueprint $table) {
            $table->softDeletes();
        });
         Schema::table('generations', function (Blueprint $table) {
            $table->softDeletes();
        });
         Schema::table('group_histories', function (Blueprint $table) {
            $table->softDeletes();
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_details', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
        Schema::table('groups', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
        Schema::table('generations', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
          Schema::table('group_histories', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

    }
};
