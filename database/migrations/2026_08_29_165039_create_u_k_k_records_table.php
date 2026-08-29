<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('ukk_records', function (Blueprint $table) {
            $table->id();

            $table->foreignId('student_id')
                ->constrained()
                ->restrictOnDelete();

            $table->foreignId('competency_id')
                ->constrained()
                ->restrictOnDelete();

            $table->unsignedTinyInteger('final_score')->nullable();
            $table->string('predicate')->nullable();

            $table->string('status')->default('DRAFT');

            $table->timestamps();

            $table->unique([
                'student_id',
                'competency_id',
            ], 'ukk_record_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('u_k_k_records');
    }
};
