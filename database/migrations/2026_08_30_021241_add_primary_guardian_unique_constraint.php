<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            CREATE UNIQUE INDEX student_guardians_one_primary_per_student
            ON student_guardians (student_id)
            WHERE is_primary = true
        ");
    }

    public function down(): void
    {
        DB::statement("
            DROP INDEX IF EXISTS student_guardians_one_primary_per_student
        ");
    }
};