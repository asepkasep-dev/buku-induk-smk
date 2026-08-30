<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('enrollments', function (Blueprint $table) {
            $table->dropUnique([
                'student_id',
                'academic_year_id',
            ]);
        });

        DB::statement("
            CREATE UNIQUE INDEX enrollments_one_active_per_year
            ON enrollments (student_id, academic_year_id)
            WHERE status = 'ACTIVE'
        ");
    }

    public function down(): void
    {
        DB::statement("
            DROP INDEX IF EXISTS enrollments_one_active_per_year
        ");

        Schema::table('enrollments', function (Blueprint $table) {
            $table->unique([
                'student_id',
                'academic_year_id',
            ]);
        });
    }
};