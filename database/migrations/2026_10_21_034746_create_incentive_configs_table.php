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
        Schema::create('incentive_configs', function (Blueprint $table): void {
            $table->id();
            $table->string('resource')->unique();
            $table->unsignedInteger('revision')->default(1);
            $table->unsignedInteger('published_revision')->nullable();
            $table->json('draft');
            $table->json('published')->nullable();
            $table->bigInteger('published_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('incentive_configs');
    }
};
