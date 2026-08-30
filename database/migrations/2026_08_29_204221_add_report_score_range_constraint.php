<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            ALTER TABLE report_scores
            ADD CONSTRAINT report_scores_final_score_check
            CHECK (final_score IS NULL OR (final_score >= 0 AND final_score <= 100))
        ");
    }

    public function down(): void
    {
        DB::statement("
            ALTER TABLE report_scores
            DROP CONSTRAINT IF EXISTS report_scores_final_score_check
        ");
    }
};