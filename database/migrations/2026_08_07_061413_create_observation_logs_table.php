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
        Schema::create('observation_logs', function (Blueprint $table) {
            $table->id()->autoIncrement()->primary();
            $table->foreignId('cycle_id')->constrained()->cascadeOnDelete();
            $table->string('phase_name');
            $table->foreignId('environment_log_id')->constrained()->cascadeOnDelete();
            $table->date('timestamp');
            $table->decimal('feed_weight', 8, 2)->default(0);
            $table->decimal('maggot_weight', 8, 2)->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('observation_logs');
    }
};
