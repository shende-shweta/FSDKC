<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ConnectMonitor extends Model
{
    use HasFactory;
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
