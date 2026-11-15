<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StaffUser extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'email', 'department'];

    public function assignedShifts(): HasMany
    {
        return $this->hasMany(ShiftAssignment::class, 'assigned_by');
    }

    public function approvedTimesheets(): HasMany
    {
        return $this->hasMany(Timesheet::class, 'approved_by');
    }
}

