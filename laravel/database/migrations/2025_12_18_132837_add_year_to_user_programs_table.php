<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('user_programs', function (Blueprint $table) {
            $table->integer('year')->nullable()->after('program_id');
            // OR if you want string
            // $table->string('year')->nullable()->after('program_id');
        });
    }

    public function down(): void
    {
        Schema::table('user_programs', function (Blueprint $table) {
            $table->dropColumn('year');
        });
    }
};
