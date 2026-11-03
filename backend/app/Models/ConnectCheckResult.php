<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConnectCheckResult extends Model
{
    use HasFactory;
    protected $fillable = [
        'connect_monitor_id',
        'reachable',
        'latency_ms',
        'carrier_route',
        'failure_reason',
        'checked_at',
    ];

    protected $casts = [
        'reachable' => 'boolean',
        'checked_at' => 'datetime',
    ];

    public function monitor(): BelongsTo
    {
        return $this->belongsTo(ConnectMonitor::class, 'connect_monitor_id');
    }
}
