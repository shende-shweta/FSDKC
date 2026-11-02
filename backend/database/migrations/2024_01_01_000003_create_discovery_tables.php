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
            $table->string('phone_number', 50);
            $table->string('country_code', 5);
            $table->string('status', 20)->default('pending');
            $table->integer('menu_depth')->default(0);
            $table->integer('nodes_discovered')->default(0);
            $table->json('languages')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('discovery_nodes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('discovery_job_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->text('prompt_text')->nullable();
            $table->string('dtmf_option', 10)->nullable();
            $table->string('node_type', 20)->default('menu');
            $table->integer('depth')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discovery_nodes');
        Schema::dropIfExists('discovery_jobs');
    }
};
