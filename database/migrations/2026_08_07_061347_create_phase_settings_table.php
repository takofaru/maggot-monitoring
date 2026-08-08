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
        Schema::create('phase_settings', function (Blueprint $table) {
            $table->id();
            $table->integer('order');
            $table->string('phase_name');
            $table->decimal('temp_bottom', 8, 2);
            $table->decimal('temp_top', 8, 2);
            $table->decimal('humid_bottom', 3, 2);
            $table->decimal('humid_top', 3, 2);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('phase_settings');
    }
};
