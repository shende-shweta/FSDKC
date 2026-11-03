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
            $table->string('name');
            $table->string('phone_number', 20);
            $table->string('country_code', 2);
            $table->string('status', 20)->default('pending');
            $table->unsignedInteger('menu_depth')->default(0);
            $table->unsignedInteger('nodes_discovered')->default(0);
            $table->json('languages')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discovery_jobs');
    }
};
