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
            $table->date('join_at')->nullable();
            $table->text('graduated_from')->nullable();
            $table->integer('graduated_at')->nullable();
            $table->text('experience')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_details', function (Blueprint $table) {
            $table->dropColumn('join_at');
            $table->dropColumn('graduated_from');
            $table->dropColumn('graduated');
            $table->dropColumn('experience');
            //
        });
    }
};
