<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            ALTER TABLE pkl_records
            ADD CONSTRAINT pkl_records_competency_score_check
            CHECK (competency_score IS NULL OR (competency_score >= 0 AND competency_score <= 100))
        ");

        DB::statement("
            ALTER TABLE pkl_records
            ADD CONSTRAINT pkl_records_attitude_score_check
            CHECK (attitude_score IS NULL OR (attitude_score >= 0 AND attitude_score <= 100))
        ");
    }

    public function down(): void
    {
        DB::statement("
            ALTER TABLE pkl_records
            DROP CONSTRAINT IF EXISTS pkl_records_competency_score_check
        ");

        DB::statement("
            ALTER TABLE pkl_records
            DROP CONSTRAINT IF EXISTS pkl_records_attitude_score_check
        ");
    }
};