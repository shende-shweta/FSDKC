<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property float|null $reachability_pct Denormalized source of truth for this monitor's
 *   reachability percentage. Refreshed exclusively by RealTimeTestService::runConnectTest().
 *   Any other write path that inserts rows into connect_check_results MUST also call
 *   ReachabilityService::calculate() and update this column to avoid divergence with
 *   ConnectController::checks() and LegacyReportController::carrierSummary().
 */
class ConnectMonitor extends Model
{
    protected $fillable = [
        'name',
        'toll_free_number',
        'country_code',
        'carrier',
        'status',
        'reachability_pct',
        'last_checked_at',
    ];

    protected $casts = [
        'reachability_pct' => 'float',
        'last_checked_at' => 'datetime',
    ];

    public function checkResults(): HasMany
    {
        return $this->hasMany(ConnectCheckResult::class);
    }
}
