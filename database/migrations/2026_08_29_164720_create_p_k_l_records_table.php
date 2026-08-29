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
        Schema::create('pkl_records', function (Blueprint $table) {
            $table->id();

            $table->foreignId('student_id')
                ->constrained()
                ->restrictOnDelete();

            $table->string('partner_name');
            $table->text('location')->nullable();

            $table->date('start_date');
            $table->date('end_date');

            $table->unsignedTinyInteger('competency_score')->nullable();
            $table->string('competency_predicate')->nullable();

            $table->unsignedTinyInteger('attitude_score')->nullable();
            $table->string('attitude_predicate')->nullable();

            $table->text('description')->nullable();

            $table->string('status')->default('DRAFT');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('p_k_l_records');
    }
};
