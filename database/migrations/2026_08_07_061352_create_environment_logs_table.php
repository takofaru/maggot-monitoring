<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\Cycle;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('environment_logs', function (Blueprint $table) {
            $table->id()->autoIncrement()->primary();
            $table->foreignId('cycle_id')->constrained()->cascadeOnDelete();
            $table->dateTime('timestamp');
            $table->decimal('temperature', 8, 2);
            $table->decimal('humidity', 5, 2);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('environment_logs');
    }
};
