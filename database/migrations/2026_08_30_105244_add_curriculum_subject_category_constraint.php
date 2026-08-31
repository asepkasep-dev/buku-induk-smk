<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            ALTER TABLE curriculum_subjects
            ADD CONSTRAINT curriculum_subjects_category_check
            CHECK (category IN ('GENERAL', 'VOCATIONAL'))
        ");
    }

    public function down(): void
    {
        DB::statement("
            ALTER TABLE curriculum_subjects
            DROP CONSTRAINT IF EXISTS curriculum_subjects_category_check
        ");
    }
};