<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Member extends Model
{
    protected $fillable = [
        'association_id',
        'unit_id',
        'member_code',
        'first_name',
        'middle_name',
        'last_name',
        'phone',
        'alt_phone',
        'email',
        'gender',
        'date_of_birth',
        'address',
        'occupation',
        'date_joined',
        'status',
        'status_reason',
        'next_of_kin_name',
        'next_of_kin_phone',
        'next_of_kin_relationship',
        'photo_path',
        'metadata',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'date_joined' => 'date',
        'metadata' => 'array',
    ];

    public function association(): BelongsTo
    {
        return $this->belongsTo(Association::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function user(): HasOne
    {
        return $this->hasOne(User::class);
    }

    public function benefitDisbursements(): HasMany
    {
        return $this->hasMany(MemberBenefitDisbursement::class);
    }

    public function beneficiaryCollections(): HasMany
    {
        return $this->hasMany(CollectionItem::class, 'beneficiary_member_id');
    }

    public function voluntaryActions(): HasMany
    {
        return $this->hasMany(MemberVoluntaryAction::class);
    }

    public function rating(): HasOne
    {
        return $this->hasOne(MemberRating::class);
    }
}
