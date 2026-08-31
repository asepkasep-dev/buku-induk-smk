<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            ALTER TABLE attendance_summaries
            ADD CONSTRAINT attendance_summaries_sick_check
            CHECK (sick >= 0)
        ");

        DB::statement("
            ALTER TABLE attendance_summaries
            ADD CONSTRAINT attendance_summaries_excused_check
            CHECK (excused >= 0)
        ");

        DB::statement("
            ALTER TABLE attendance_summaries
            ADD CONSTRAINT attendance_summaries_absent_check
            CHECK (absent >= 0)
        ");
    }

    public function down(): void
    {
        DB::statement("
            ALTER TABLE attendance_summaries
            DROP CONSTRAINT IF EXISTS attendance_summaries_sick_check
        ");

        DB::statement("
            ALTER TABLE attendance_summaries
            DROP CONSTRAINT IF EXISTS attendance_summaries_excused_check
        ");

        DB::statement("
            ALTER TABLE attendance_summaries
            DROP CONSTRAINT IF EXISTS attendance_summaries_absent_check
        ");
    }
};