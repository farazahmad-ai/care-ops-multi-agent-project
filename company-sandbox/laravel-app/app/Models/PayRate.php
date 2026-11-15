<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PayRate extends Model
{
    use HasFactory;

    protected $fillable = [
        'role', 'hourly_pay', 'hourly_charge', 'effective_from', 'effective_to'
    ];

    protected $casts = [
        'hourly_pay' => 'decimal:2',
        'hourly_charge' => 'decimal:2',
        'effective_from' => 'date',
        'effective_to' => 'date',
    ];
}

