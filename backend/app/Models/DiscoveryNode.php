<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DiscoveryNode extends Model
{
    use HasFactory;
    protected $fillable = [
        'discovery_job_id',
        'parent_id',
        'prompt_text',
        'dtmf_option',
        'node_type',
        'depth',
    ];

    public function job(): BelongsTo
    {
        return $this->belongsTo(DiscoveryJob::class, 'discovery_job_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }
}
