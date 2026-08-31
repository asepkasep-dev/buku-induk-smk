<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            ALTER TABLE diploma_scores
            ADD CONSTRAINT diploma_scores_final_score_check
            CHECK (final_score IS NULL OR (final_score >= 0 AND final_score <= 100))
        ");
    }

    public function down(): void
    {
        DB::statement("
            ALTER TABLE diploma_scores
            DROP CONSTRAINT IF EXISTS diploma_scores_final_score_check
        ");
    }
};