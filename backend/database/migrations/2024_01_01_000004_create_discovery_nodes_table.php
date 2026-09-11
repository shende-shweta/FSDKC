<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discovery_nodes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('discovery_job_id')->constrained('discovery_jobs')->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('discovery_nodes')->nullOnDelete();
            $table->text('prompt_text')->nullable();
            $table->string('dtmf_option', 10)->nullable();
            $table->string('node_type', 50)->nullable();
            $table->integer('depth')->default(0);
            $table->timestamps();

            $table->index('discovery_job_id');
            $table->index(['discovery_job_id', 'parent_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discovery_nodes');
    }
};