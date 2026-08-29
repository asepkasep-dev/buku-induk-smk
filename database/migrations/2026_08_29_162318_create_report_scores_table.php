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
        Schema::create('report_scores', function (Blueprint $table) {
            $table->id();

            $table->foreignId('student_id')
                ->constrained()
                ->restrictOnDelete();

            $table->foreignId('subject_offering_id')
                ->constrained()
                ->restrictOnDelete();

            $table->unsignedTinyInteger('final_score')->nullable();
            $table->string('letter_grade')->nullable();
            $table->text('description')->nullable();

            $table->string('status')->default('DRAFT');

            $table->timestamp('locked_at')->nullable();
            $table->foreignId('locked_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('finalized_at')->nullable();
            $table->foreignId('finalized_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();

            $table->unique([
                'student_id',
                'subject_offering_id',
            ], 'report_score_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('report_scores');
    }
};
