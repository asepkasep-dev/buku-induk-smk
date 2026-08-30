<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operator_rombel_scopes', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained('users')
                ->restrictOnDelete();

            $table->foreignId('rombel_id')
                ->constrained('rombels')
                ->restrictOnDelete();

            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->unique(
                ['user_id', 'rombel_id'],
                'operator_rombel_scope_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operator_rombel_scopes');
    }
};