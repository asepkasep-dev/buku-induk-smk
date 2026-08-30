<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            ALTER TABLE academic_years
            ADD CONSTRAINT academic_years_date_range_check
            CHECK (end_date >= start_date)
        ");

        DB::statement("
            ALTER TABLE enrollments
            ADD CONSTRAINT enrollments_date_range_check
            CHECK (end_date IS NULL OR start_date IS NULL OR end_date >= start_date)
        ");

        DB::statement("
            ALTER TABLE pkl_records
            ADD CONSTRAINT pkl_records_date_range_check
            CHECK (end_date >= start_date)
        ");
    }

    public function down(): void
    {
        DB::statement("
            ALTER TABLE academic_years
            DROP CONSTRAINT IF EXISTS academic_years_date_range_check
        ");

        DB::statement("
            ALTER TABLE enrollments
            DROP CONSTRAINT IF EXISTS enrollments_date_range_check
        ");

        DB::statement("
            ALTER TABLE pkl_records
            DROP CONSTRAINT IF EXISTS pkl_records_date_range_check
        ");
    }
};