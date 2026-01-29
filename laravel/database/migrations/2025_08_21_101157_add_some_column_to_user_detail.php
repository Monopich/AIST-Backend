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
            $table->string('guardian')->nullable();
            $table->string('high_school')->nullable();
            $table->string('mcs_no')->nullable();
            $table->string('can_id')->nullable();
            $table->string('bac_grade')->nullable();
            $table->string('bac_from')->nullable();
            $table->string('bac_program')->nullable();
            $table->string('degree')->nullable();
            $table->string('option')->nullable();
            $table->text('history')->nullable();
            $table->json('redoubles')->nullable(); // Array field
            $table->string('scholarships')->nullable();
            $table->string('branch')->nullable();
            $table->string('file')->nullable();
            $table->string('grade')->nullable();
            $table->boolean('is_radie')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_details', function (Blueprint $table) {
            $table->dropColumn([
                'guardian',
                'high_school',
                'mcs_no',
                'can_id',
                'bac_grade',
                'bac_from',
                'bac_program',
                'degree',
                'option',
                'history',
                'redoubles',
                'scholarships',
                'branch',
                'file',
                'grade',
                'is_radie'
            ]);
        });
    }
};
