<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            ALTER TABLE extracurricular_records
            ADD CONSTRAINT extracurricular_records_status_check
            CHECK (status IN (
                'DRAFT',
                'LOCKED',
                'FINALIZED'
            ))
        ");

        DB::statement("
            ALTER TABLE attendance_summaries
            ADD CONSTRAINT attendance_summaries_status_check
            CHECK (status IN (
                'DRAFT',
                'LOCKED',
                'FINALIZED'
            ))
        ");
    }

    public function down(): void
    {
        DB::statement("
            ALTER TABLE extracurricular_records
            DROP CONSTRAINT IF EXISTS extracurricular_records_status_check
        ");

        DB::statement("
            ALTER TABLE attendance_summaries
            DROP CONSTRAINT IF EXISTS attendance_summaries_status_check
        ");
    }
};