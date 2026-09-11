<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discovery_jobs', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 255);
            $table->string('phone_number', 50);
            $table->string('country_code', 5);
            $table->enum('status', ['pending', 'running', 'completed', 'failed'])->default('pending');
            $table->integer('menu_depth')->nullable();
            $table->integer('nodes_discovered')->nullable();
            $table->json('languages')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discovery_jobs');
    }
};