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
         Schema::create('missions', function (Blueprint $table) {
            $table->id();
            $table->string('mission_title',25);
            $table->string('mission_type',25);
            $table->text('description')->nullable();       
            $table->date('assigned_date');
            $table->date('due_date');     
            $table->float('budget', 10, 2)->default(0);
            $table->string('location', 120)->nullable();
            $table->string('status', 20)->default('pending');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_missions');
    }
};
