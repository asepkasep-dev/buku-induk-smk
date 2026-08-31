<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            ALTER TABLE pkl_records
            ADD CONSTRAINT pkl_records_status_check
            CHECK (status IN ('DRAFT', 'FINALIZED'))
        ");

        DB::statement("
            ALTER TABLE ukk_records
            ADD CONSTRAINT ukk_records_status_check
            CHECK (status IN ('DRAFT', 'FINALIZED'))
        ");

        DB::statement("
            ALTER TABLE diploma_scores
            ADD CONSTRAINT diploma_scores_status_check
            CHECK (status IN ('DRAFT', 'FINALIZED'))
        ");
    }

    public function down(): void
    {
        DB::statement("
            ALTER TABLE pkl_records
            DROP CONSTRAINT IF EXISTS pkl_records_status_check
        ");

        DB::statement("
            ALTER TABLE ukk_records
            DROP CONSTRAINT IF EXISTS ukk_records_status_check
        ");

        DB::statement("
            ALTER TABLE diploma_scores
            DROP CONSTRAINT IF EXISTS diploma_scores_status_check
        ");
    }
};