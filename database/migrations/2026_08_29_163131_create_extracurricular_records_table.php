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
        Schema::create('extracurricular_records', function (Blueprint $table) {
            $table->id();

            $table->foreignId('student_id')
                ->constrained()
                ->restrictOnDelete();

            $table->foreignId('extracurricular_id')
                ->constrained()
                ->restrictOnDelete();

            $table->foreignId('semester_id')
                ->constrained()
                ->restrictOnDelete();

            $table->string('predicate')->nullable();
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
                'extracurricular_id',
                'semester_id',
            ], 'extracurricular_record_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('extracurricular_records');
    }
};
