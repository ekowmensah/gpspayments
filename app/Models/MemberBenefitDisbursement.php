<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MemberBenefitDisbursement extends Model
{
    protected $fillable = [
        'association_id',
        'collection_item_id',
        'member_id',
        'disbursed_amount',
        'disbursed_date',
        'reference',
        'status',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'disbursed_date' => 'date',
    ];

    public function collectionItem(): BelongsTo
    {
        return $this->belongsTo(CollectionItem::class);
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }
}
