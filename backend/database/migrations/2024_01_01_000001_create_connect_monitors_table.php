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
            $table->string('name', 255);
            $table->string('toll_free_number', 50);
            $table->string('country_code', 5);
            $table->string('carrier', 100)->nullable();
            $table->enum('status', ['active', 'paused', 'alert'])->default('active');
            $table->float('reachability_pct')->default(100.0);
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('connect_monitors');
    }
};