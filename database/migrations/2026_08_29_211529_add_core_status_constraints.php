<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            ALTER TABLE students
            ADD CONSTRAINT students_status_check
            CHECK (status IN (
                'CALON',
                'AKTIF',
                'LULUS',
                'PINDAH',
                'MENGUNDURKAN_DIRI',
                'TIDAK_LULUS'
            ))
        ");

        DB::statement("
            ALTER TABLE report_scores
            ADD CONSTRAINT report_scores_status_check
            CHECK (status IN (
                'DRAFT',
                'LOCKED',
                'FINALIZED'
            ))
        ");

        DB::statement("
            ALTER TABLE graduations
            ADD CONSTRAINT graduations_status_check
            CHECK (status IN (
                'DRAFT',
                'VERIFIED',
                'FINALIZED'
            ))
        ");
    }

    public function down(): void
    {
        DB::statement("
            ALTER TABLE students
            DROP CONSTRAINT IF EXISTS students_status_check
        ");

        DB::statement("
            ALTER TABLE report_scores
            DROP CONSTRAINT IF EXISTS report_scores_status_check
        ");

        DB::statement("
            ALTER TABLE graduations
            DROP CONSTRAINT IF EXISTS graduations_status_check
        ");
    }
};