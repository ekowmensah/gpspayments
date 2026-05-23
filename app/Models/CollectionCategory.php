<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CollectionCategory extends Model
{
    protected $fillable = [
        'association_id',
        'code',
        'name',
        'description',
        'payment_mode',
        'default_charge_type',
        'default_is_required',
        'default_allow_partial_payment',
        'status',
        'created_by',
    ];

    protected $casts = [
        'default_is_required' => 'boolean',
        'default_allow_partial_payment' => 'boolean',
    ];

    public function association(): BelongsTo
    {
        return $this->belongsTo(Association::class);
    }

    public function collectionItems(): HasMany
    {
        return $this->hasMany(CollectionItem::class, 'collection_category_id');
    }
}
