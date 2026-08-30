<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            ALTER TABLE ukk_records
            ADD CONSTRAINT ukk_records_final_score_check
            CHECK (final_score IS NULL OR (final_score >= 0 AND final_score <= 100))
        ");
    }

    public function down(): void
    {
        DB::statement("
            ALTER TABLE ukk_records
            DROP CONSTRAINT IF EXISTS ukk_records_final_score_check
        ");
    }
};