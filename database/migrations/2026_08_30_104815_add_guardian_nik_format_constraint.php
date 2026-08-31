<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            ALTER TABLE guardians
            ADD CONSTRAINT guardians_nik_format_check
            CHECK (nik IS NULL OR nik ~ '^[0-9]{16}$')
        ");
    }

    public function down(): void
    {
        DB::statement("
            ALTER TABLE guardians
            DROP CONSTRAINT IF EXISTS guardians_nik_format_check
        ");
    }
};