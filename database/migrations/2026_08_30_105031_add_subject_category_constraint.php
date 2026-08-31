<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            ALTER TABLE subjects
            ADD CONSTRAINT subjects_category_check
            CHECK (category IN ('GENERAL', 'VOCATIONAL'))
        ");
    }

    public function down(): void
    {
        DB::statement("
            ALTER TABLE subjects
            DROP CONSTRAINT IF EXISTS subjects_category_check
        ");
    }
};