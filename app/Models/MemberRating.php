<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MemberRating extends Model
{
    protected $fillable = [
        'association_id',
        'member_id',
        'score',
        'minimum_required_score',
        'eligible_for_benefit',
        'band',
        'as_of_date',
        'metrics',
    ];

    protected $casts = [
        'score' => 'float',
        'minimum_required_score' => 'float',
        'eligible_for_benefit' => 'boolean',
        'as_of_date' => 'date',
        'metrics' => 'array',
    ];

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }
}

