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
        Schema::create('attendance_summaries', function (Blueprint $table) {
            $table->id();

            $table->foreignId('student_id')
                ->constrained()
                ->restrictOnDelete();

            $table->foreignId('semester_id')
                ->constrained()
                ->restrictOnDelete();

            $table->unsignedSmallInteger('sick')->default(0);
            $table->unsignedSmallInteger('excused')->default(0);
            $table->unsignedSmallInteger('absent')->default(0);

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
                'semester_id',
            ], 'attendance_summary_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendance_summaries');
    }
};
