<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_score_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('report_score_id')
                ->constrained('report_scores')
                ->cascadeOnDelete();

            $table->string('type');

            $table->string('label');

            $table->smallInteger('score');

            $table->smallInteger('sort_order')->default(0);

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();

            $table->index(['report_score_id', 'type']);
        });

        DB::statement("
            ALTER TABLE report_score_items
            ADD CONSTRAINT report_score_items_type_check
            CHECK (type IN ('PENGETAHUAN', 'KETERAMPILAN'))
        ");

        DB::statement('
            ALTER TABLE report_score_items
            ADD CONSTRAINT report_score_items_score_check
            CHECK (score >= 0 AND score <= 100)
        ');
    }

    public function down(): void
    {
        Schema::dropIfExists('report_score_items');
    }
};
