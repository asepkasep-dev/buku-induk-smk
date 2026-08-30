<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('curriculum_subjects', function (Blueprint $table) {
            $table->dropUnique('curriculum_subject_unique');
        });

        DB::statement("
            CREATE UNIQUE INDEX curriculum_subject_unique
            ON curriculum_subjects (
                curriculum_id,
                subject_id,
                competency_id,
                grade,
                semester_number
            )
            NULLS NOT DISTINCT
        ");
    }

    public function down(): void
    {
        DB::statement("
            DROP INDEX IF EXISTS curriculum_subject_unique
        ");

        Schema::table('curriculum_subjects', function (Blueprint $table) {
            $table->unique(
                [
                    'curriculum_id',
                    'subject_id',
                    'competency_id',
                    'grade',
                    'semester_number',
                ],
                'curriculum_subject_unique'
            );
        });
    }
};