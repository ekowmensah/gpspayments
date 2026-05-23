<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CollectionItem extends Model
{
    protected $fillable = [
        'association_id',
        'collection_category_id',
        'code',
        'name',
        'description',
        'category',
        'charge_type',
        'frequency',
        'amount',
        'currency_code',
        'is_required',
        'allow_partial_payment',
        'is_benefit_collection',
        'beneficiary_member_id',
        'start_date',
        'end_date',
        'due_day_of_month',
        'grace_days',
        'penalty_type',
        'penalty_value',
        'applies_scope',
        'status',
        'created_by',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'approved_at' => 'datetime',
        'is_required' => 'boolean',
        'allow_partial_payment' => 'boolean',
        'is_benefit_collection' => 'boolean',
    ];

    public function association(): BelongsTo
    {
        return $this->belongsTo(Association::class);
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(Member::class, 'collection_item_members', 'collection_item_id', 'member_id')
            ->withTimestamps();
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function beneficiary(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'beneficiary_member_id');
    }

    public function benefitDisbursements(): HasMany
    {
        return $this->hasMany(MemberBenefitDisbursement::class);
    }

    public function categoryConfig(): BelongsTo
    {
        return $this->belongsTo(CollectionCategory::class, 'collection_category_id');
    }

    public function isVoluntaryCategory(): bool
    {
        if ($this->relationLoaded('categoryConfig') && $this->categoryConfig) {
            return strtolower((string)$this->categoryConfig->payment_mode) === 'voluntary';
        }

        return strtolower((string)$this->charge_type) === 'voluntary'
            || strtolower((string)$this->category) === 'donation';
    }
}
