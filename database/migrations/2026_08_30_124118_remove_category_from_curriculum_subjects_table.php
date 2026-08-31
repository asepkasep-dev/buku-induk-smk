<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            ALTER TABLE curriculum_subjects
            DROP CONSTRAINT IF EXISTS curriculum_subjects_category_check
        ");

        Schema::table('curriculum_subjects', function (Blueprint $table) {
            $table->dropColumn('category');
        });
    }

    public function down(): void
    {
        Schema::table('curriculum_subjects', function (Blueprint $table) {
            $table->string('category')->nullable();
        });

        DB::statement("
            ALTER TABLE curriculum_subjects
            ADD CONSTRAINT curriculum_subjects_category_check
            CHECK (category IN ('GENERAL', 'VOCATIONAL'))
        ");
    }
};