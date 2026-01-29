<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // orphaned rows
        DB::table('user_programs')
            ->whereNotIn('user_id', DB::table('users')->pluck('id'))
            ->delete();

        // Add foreign key only if it does not exist
        $sm = DB::select("SELECT CONSTRAINT_NAME
                           FROM information_schema.TABLE_CONSTRAINTS
                           WHERE TABLE_SCHEMA = DATABASE()
                             AND TABLE_NAME = 'user_programs'
                             AND CONSTRAINT_TYPE = 'FOREIGN KEY'
                             AND CONSTRAINT_NAME = 'user_programs_user_id_foreign'");
        if (empty($sm)) {
            Schema::table('user_programs', function (Blueprint $table) {
                $table->foreign('user_id')
                      ->references('id')
                      ->on('users')
                      ->onDelete('cascade');
            });
        }
    }

    public function down(): void
    {
        Schema::table('user_programs', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
        });
    }
};
