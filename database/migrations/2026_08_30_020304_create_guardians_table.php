<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guardians', function (Blueprint $table) {
            $table->id();

            $table->string('full_name');
            $table->string('institution_name')->nullable();

            $table->string('nik', 16)->nullable()->unique();

            $table->string('phone')->nullable();
            $table->string('email')->nullable();

            $table->string('occupation')->nullable();
            $table->string('education')->nullable();

            $table->text('address')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guardians');
    }
};