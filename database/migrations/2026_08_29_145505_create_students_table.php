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
        Schema::create('students', function (Blueprint $table) {
            $table->id();

            $table->string('nis')->unique();
            $table->string('nisn')->nullable()->unique();
            $table->string('nik', 16)->nullable()->unique();

            $table->string('full_name');
            $table->string('nickname')->nullable();

            $table->string('gender', 1);
            $table->string('birth_place')->nullable();
            $table->date('birth_date')->nullable();
            $table->string('religion')->nullable();

            $table->unsignedSmallInteger('entry_year');

            $table->string('previous_school')->nullable();
            $table->string('previous_diploma_number')->nullable();
            $table->date('previous_diploma_date')->nullable();

            $table->string('phone')->nullable();
            $table->string('email')->nullable();

            $table->text('address')->nullable();
            $table->string('village')->nullable();
            $table->string('district')->nullable();
            $table->string('city')->nullable();
            $table->string('province')->nullable();
            $table->string('postal_code', 10)->nullable();

            $table->string('status')->default('CALON');

            $table->date('entry_date')->nullable();
            $table->date('exit_date')->nullable();
            $table->text('exit_notes')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('students');
    }
};
