<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ShiftAssignment extends Model
{
    use HasFactory;

    protected $fillable = [
        'shift_id', 'carer_id', 'status', 'assigned_at', 'assigned_by'
    ];

    protected $casts = [
        'assigned_at' => 'datetime',
    ];

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function carer(): BelongsTo
    {
        return $this->belongsTo(Carer::class);
    }

    public function assignedByUser(): BelongsTo
    {
        return $this->belongsTo(StaffUser::class, 'assigned_by');
    }

    public function timesheets(): HasMany
    {
        return $this->hasMany(Timesheet::class);
    }
}

