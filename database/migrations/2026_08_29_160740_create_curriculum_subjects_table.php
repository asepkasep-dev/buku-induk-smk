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
        Schema::create('curriculum_subjects', function (Blueprint $table) {
            $table->id();

            $table->foreignId('curriculum_id')
                ->constrained()
                ->restrictOnDelete();

            $table->foreignId('subject_id')
                ->constrained()
                ->restrictOnDelete();

            $table->foreignId('competency_id')
                ->nullable()
                ->constrained()
                ->restrictOnDelete();

            $table->string('category');

            $table->unsignedTinyInteger('grade');
            $table->unsignedTinyInteger('semester_number');

            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->unique([
                'curriculum_id',
                'subject_id',
                'competency_id',
                'grade',
                'semester_number',
            ], 'curriculum_subject_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('curriculum_subjects');
    }
};
