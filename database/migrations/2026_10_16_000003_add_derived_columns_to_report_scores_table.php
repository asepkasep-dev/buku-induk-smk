<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('report_scores', function (Blueprint $table) {
            $table->decimal('pengetahuan_avg', 5, 2)->nullable()->after('subject_offering_id');
            $table->decimal('keterampilan_avg', 5, 2)->nullable()->after('pengetahuan_avg');
        });

        DB::statement('
            ALTER TABLE report_scores
            ADD CONSTRAINT report_scores_pengetahuan_avg_check
            CHECK (pengetahuan_avg IS NULL OR (pengetahuan_avg >= 0 AND pengetahuan_avg <= 100))
        ');

        DB::statement('
            ALTER TABLE report_scores
            ADD CONSTRAINT report_scores_keterampilan_avg_check
            CHECK (keterampilan_avg IS NULL OR (keterampilan_avg >= 0 AND keterampilan_avg <= 100))
        ');
    }

    public function down(): void
    {
        DB::statement('
            ALTER TABLE report_scores
            DROP CONSTRAINT IF EXISTS report_scores_pengetahuan_avg_check
        ');
        DB::statement('
            ALTER TABLE report_scores
            DROP CONSTRAINT IF EXISTS report_scores_keterampilan_avg_check
        ');

        Schema::table('report_scores', function (Blueprint $table) {
            $table->dropColumn(['pengetahuan_avg', 'keterampilan_avg']);
        });
    }
};
