<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Client extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'type', 'address_line', 'postcode',
        'contact_name', 'contact_email', 'status'
    ];

    public function shifts(): HasMany
    {
        return $this->hasMany(Shift::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }
}

