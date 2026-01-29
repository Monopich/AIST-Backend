<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('import_scores', function (Blueprint $table) {
            $table->unique('academic_year');
        });
    }

    public function down(): void
    {
        Schema::table('import_scores', function (Blueprint $table) {
            $table->dropUnique(['academic_year']);
        });
    }

};
