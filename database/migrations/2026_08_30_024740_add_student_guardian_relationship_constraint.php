<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            ALTER TABLE student_guardians
            ADD CONSTRAINT student_guardians_relationship_check
            CHECK (relationship IN (
                'FATHER',
                'MOTHER',
                'GUARDIAN',
                'FOSTER_GUARDIAN',
                'INSTITUTION_GUARDIAN',
                'OTHER'
            ))
        ");
    }

    public function down(): void
    {
        DB::statement("
            ALTER TABLE student_guardians
            DROP CONSTRAINT IF EXISTS student_guardians_relationship_check
        ");
    }
};