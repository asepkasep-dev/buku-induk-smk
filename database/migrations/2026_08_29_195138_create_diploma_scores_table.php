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
        Schema::create('diploma_scores', function (Blueprint $table) {
            $table->id();

            $table->foreignId('student_id')
                ->constrained()
                ->restrictOnDelete();

            $table->foreignId('subject_id')
                ->constrained()
                ->restrictOnDelete();

            $table->decimal('final_score', 5, 2)->nullable();
            $table->string('predicate')->nullable();

            $table->string('status')->default('DRAFT');
            $table->timestamp('finalized_at')->nullable();

            $table->timestamps();

            $table->unique([
                'student_id',
                'subject_id',
            ], 'diploma_score_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('diploma_scores');
    }
};
