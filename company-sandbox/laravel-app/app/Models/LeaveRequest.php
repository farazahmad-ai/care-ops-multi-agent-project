<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaveRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'carer_id', 'start_date', 'end_date', 'type', 'status', 'requested_at', 'decided_at'
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'requested_at' => 'datetime',
        'decided_at' => 'datetime',
    ];

    public function carer(): BelongsTo
    {
        return $this->belongsTo(Carer::class);
    }
}

