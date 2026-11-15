<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Candidate extends Model
{
    use HasFactory;

    protected $fillable = [
        'first_name', 'last_name', 'email', 'phone', 'postcode',
        'cv_text', 'status', 'source', 'applied_at'
    ];

    protected $casts = [
        'applied_at' => 'datetime',
    ];

    public function interviews(): HasMany
    {
        return $this->hasMany(Interview::class);
    }

    public function complianceChecks(): HasMany
    {
        return $this->hasMany(ComplianceCheck::class);
    }

    public function carer(): HasOne
    {
        return $this->hasOne(Carer::class);
    }
}

