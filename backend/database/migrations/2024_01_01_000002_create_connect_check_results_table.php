<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('connect_check_results', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('connect_monitor_id')->constrained('connect_monitors')->cascadeOnDelete();
            $table->boolean('reachable');
            $table->integer('latency_ms')->nullable();
            $table->string('carrier_route', 255)->nullable();
            $table->string('failure_reason', 500)->nullable();
            $table->timestamp('checked_at');
            $table->timestamps();

            $table->index(['connect_monitor_id', 'checked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('connect_check_results');
    }
};