<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('curricula', function (Blueprint $table) {
            $table->dropUnique(['name', 'version']);
        });

        DB::statement("
            CREATE UNIQUE INDEX curricula_name_version_unique
            ON curricula (name, version)
            NULLS NOT DISTINCT
        ");
    }

    public function down(): void
    {
        DB::statement("
            DROP INDEX IF EXISTS curricula_name_version_unique
        ");

        Schema::table('curricula', function (Blueprint $table) {
            $table->unique(['name', 'version']);
        });
    }
};