<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('connect_monitors', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('toll_free_number', 50);
            $table->string('country_code', 5);
            $table->string('carrier', 100)->nullable();
            $table->string('status', 20)->default('active');
            $table->decimal('reachability_pct', 5, 2)->default(100.00);
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamps();
        });

        Schema::create('connect_check_results', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('connect_monitor_id')->constrained()->cascadeOnDelete();
            $table->boolean('reachable');
            $table->integer('latency_ms')->nullable();
            $table->string('carrier_route', 255)->nullable();
            $table->string('failure_reason', 255)->nullable();
            $table->timestamp('checked_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('connect_check_results');
        Schema::dropIfExists('connect_monitors');
    }
};
