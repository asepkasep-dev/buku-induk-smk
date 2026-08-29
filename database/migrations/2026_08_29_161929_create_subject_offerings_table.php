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
        Schema::create('subject_offerings', function (Blueprint $table) {
            $table->id();

            $table->foreignId('curriculum_subject_id')
                ->constrained()
                ->restrictOnDelete();

            $table->foreignId('rombel_id')
                ->constrained()
                ->restrictOnDelete();

            $table->foreignId('semester_id')
                ->constrained()
                ->restrictOnDelete();

            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->unique([
                'curriculum_subject_id',
                'rombel_id',
                'semester_id',
            ], 'subject_offering_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subject_offerings');
    }
};
