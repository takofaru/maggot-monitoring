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
        Schema::table('activity_logs', function (Blueprint $table) {
            $table->string('type')->default('system')->after('id');
            $table->string('title')->after('type');
            $table->text('description')->after('title');
            $table->foreignId('user_id')->nullable()->after('description')->constrained()->nullOnDelete();
            $table->string('user_name')->nullable()->after('user_id');
            $table->json('metadata')->nullable()->after('user_name');
            $table->boolean('is_read')->default(false)->after('metadata');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('activity_logs', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropColumn(['type', 'title', 'description', 'user_id', 'user_name', 'metadata', 'is_read']);
        });
    }
};
