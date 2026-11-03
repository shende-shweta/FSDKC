<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DiscoveryJob extends Model
{
    use HasFactory;
    protected $fillable = [
        'name',
        'phone_number',
        'country_code',
        'status',
        'menu_depth',
        'nodes_discovered',
        'languages',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'languages' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function nodes(): HasMany
    {
        return $this->hasMany(DiscoveryNode::class);
    }
}
