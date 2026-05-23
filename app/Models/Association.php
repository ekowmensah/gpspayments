<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Association extends Model
{
    protected $fillable = [
        'name',
        'legal_name',
        'registration_number',
        'email',
        'phone',
        'address',
        'currency_code',
        'timezone',
        'logo_path',
        'status',
    ];

    public function members(): HasMany
    {
        return $this->hasMany(Member::class);
    }
}

