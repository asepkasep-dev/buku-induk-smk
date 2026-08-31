<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            ALTER TABLE academic_years
            ADD CONSTRAINT academic_years_status_check
            CHECK (status IN ('INACTIVE', 'ACTIVE', 'COMPLETED'))
        ");

        DB::statement("
            ALTER TABLE semesters
            ADD CONSTRAINT semesters_status_check
            CHECK (status IN ('INACTIVE', 'ACTIVE', 'COMPLETED'))
        ");
    }

    public function down(): void
    {
        DB::statement("
            ALTER TABLE academic_years
            DROP CONSTRAINT IF EXISTS academic_years_status_check
        ");

        DB::statement("
            ALTER TABLE semesters
            DROP CONSTRAINT IF EXISTS semesters_status_check
        ");
    }
};