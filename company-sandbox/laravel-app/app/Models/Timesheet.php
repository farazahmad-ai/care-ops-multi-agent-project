<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Timesheet extends Model
{
    use HasFactory;

    protected $fillable = [
        'shift_assignment_id', 'carer_id', 'hours_worked', 'status',
        'submitted_at', 'approved_at', 'approved_by'
    ];

    protected $casts = [
        'hours_worked' => 'decimal:2',
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
    ];

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(ShiftAssignment::class, 'shift_assignment_id');
    }

    public function carer(): BelongsTo
    {
        return $this->belongsTo(Carer::class);
    }

    public function approvedByUser(): BelongsTo
    {
        return $this->belongsTo(StaffUser::class, 'approved_by');
    }

    public function invoiceLines(): HasMany
    {
        return $this->hasMany(InvoiceLine::class);
    }
}

