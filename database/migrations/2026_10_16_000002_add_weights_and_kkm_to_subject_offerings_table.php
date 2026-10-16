<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subject_offerings', function (Blueprint $table) {
            $table->smallInteger('pengetahuan_weight')->default(50);
            $table->smallInteger('keterampilan_weight')->default(50);
            $table->smallInteger('kkm')->nullable();
        });

        DB::statement('
            ALTER TABLE subject_offerings
            ADD CONSTRAINT subject_offerings_pengetahuan_weight_check
            CHECK (pengetahuan_weight >= 0 AND pengetahuan_weight <= 100)
        ');

        DB::statement('
            ALTER TABLE subject_offerings
            ADD CONSTRAINT subject_offerings_keterampilan_weight_check
            CHECK (keterampilan_weight >= 0 AND keterampilan_weight <= 100)
        ');

        DB::statement('
            ALTER TABLE subject_offerings
            ADD CONSTRAINT subject_offerings_kkm_check
            CHECK (kkm IS NULL OR (kkm >= 0 AND kkm <= 100))
        ');
    }

    public function down(): void
    {
        DB::statement('
            ALTER TABLE subject_offerings
            DROP CONSTRAINT IF EXISTS subject_offerings_pengetahuan_weight_check
        ');
        DB::statement('
            ALTER TABLE subject_offerings
            DROP CONSTRAINT IF EXISTS subject_offerings_keterampilan_weight_check
        ');
        DB::statement('
            ALTER TABLE subject_offerings
            DROP CONSTRAINT IF EXISTS subject_offerings_kkm_check
        ');

        Schema::table('subject_offerings', function (Blueprint $table) {
            $table->dropColumn(['pengetahuan_weight', 'keterampilan_weight', 'kkm']);
        });
    }
};
