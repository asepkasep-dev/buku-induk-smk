<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            ALTER TABLE student_documents
            ADD CONSTRAINT student_documents_status_check
            CHECK (status IN ('ACTIVE', 'ARCHIVED'))
        ");
    }

    public function down(): void
    {
        DB::statement("
            ALTER TABLE student_documents
            DROP CONSTRAINT IF EXISTS student_documents_status_check
        ");
    }
};