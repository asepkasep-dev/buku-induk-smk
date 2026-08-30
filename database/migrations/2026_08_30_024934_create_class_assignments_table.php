<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('class_assignments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained('users')
                ->restrictOnDelete();

            $table->foreignId('rombel_id')
                ->constrained('rombels')
                ->restrictOnDelete();

            $table->foreignId('academic_year_id')
                ->constrained('academic_years')
                ->restrictOnDelete();

            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();

            $table->string('status')->default('ACTIVE');
            $table->text('notes')->nullable();

            $table->timestamps();
        });

        DB::statement("
            CREATE UNIQUE INDEX class_assignments_one_active_per_rombel
            ON class_assignments (rombel_id, academic_year_id)
            WHERE status = 'ACTIVE'
        ");

        DB::statement("
            ALTER TABLE class_assignments
            ADD CONSTRAINT class_assignments_status_check
            CHECK (status IN (
                'ACTIVE',
                'INACTIVE',
                'CANCELLED'
            ))
        ");

        DB::statement("
            ALTER TABLE class_assignments
            ADD CONSTRAINT class_assignments_date_range_check
            CHECK (
                end_date IS NULL
                OR start_date IS NULL
                OR end_date >= start_date
            )
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('class_assignments');
    }
};