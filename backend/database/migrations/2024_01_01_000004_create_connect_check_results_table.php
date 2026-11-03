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
            $table->foreignId('connect_monitor_id')->constrained()->cascadeOnDelete();
            $table->boolean('reachable')->default(false);
            $table->unsignedInteger('latency_ms')->nullable();
            $table->string('carrier_route')->nullable();
            $table->string('failure_reason')->nullable();
            $table->timestamp('checked_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('connect_check_results');
    }
};
