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
        Schema::create('locations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('building_id')->constrained('buildings')->onDelete('cascade');
            $table->integer('floor')->nullable();
             // GPS coordinates (latitude and longitude)
            $table->decimal('latitude', 10, 7)->nullable();  // ±90.0000000
            $table->decimal('longitude', 10, 7)->nullable(); // ±180.0000000
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('locations');
    }
};
