<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            ALTER TABLE rombels
            ADD CONSTRAINT rombels_grade_check
            CHECK (grade IN (10, 11, 12))
        ");

        DB::statement("
            ALTER TABLE curriculum_subjects
            ADD CONSTRAINT curriculum_subjects_grade_check
            CHECK (grade IN (10, 11, 12))
        ");

        DB::statement("
            ALTER TABLE semesters
            ADD CONSTRAINT semesters_number_check
            CHECK (number IN (1, 2))
        ");

        DB::statement("
            ALTER TABLE curriculum_subjects
            ADD CONSTRAINT curriculum_subjects_semester_number_check
            CHECK (semester_number IN (1, 2))
        ");
    }

    public function down(): void
    {
        DB::statement("
            ALTER TABLE rombels
            DROP CONSTRAINT IF EXISTS rombels_grade_check
        ");

        DB::statement("
            ALTER TABLE curriculum_subjects
            DROP CONSTRAINT IF EXISTS curriculum_subjects_grade_check
        ");

        DB::statement("
            ALTER TABLE semesters
            DROP CONSTRAINT IF EXISTS semesters_number_check
        ");

        DB::statement("
            ALTER TABLE curriculum_subjects
            DROP CONSTRAINT IF EXISTS curriculum_subjects_semester_number_check
        ");
    }
};