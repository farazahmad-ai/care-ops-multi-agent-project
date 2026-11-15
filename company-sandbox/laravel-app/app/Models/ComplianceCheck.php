<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ComplianceCheck extends Model
{
    use HasFactory;

    protected $fillable = [
        'carer_id', 'candidate_id', 'type', 'issued_on', 'expires_on', 'status'
    ];

    protected $casts = [
        'issued_on' => 'date',
        'expires_on' => 'date',
    ];

    public function carer(): BelongsTo
    {
        return $this->belongsTo(Carer::class);
    }

    public function candidate(): BelongsTo
    {
        return $this->belongsTo(Candidate::class);
    }
}

