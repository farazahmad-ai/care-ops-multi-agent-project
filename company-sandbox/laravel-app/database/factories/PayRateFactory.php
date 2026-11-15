<?php

namespace Database\Factories;

use App\Models\PayRate;
use Illuminate\Database\Eloquent\Factories\Factory;

class PayRateFactory extends Factory
{
    protected $model = PayRate::class;

    public function definition(): array
    {
        return [
            'role' => 'carer',
            'hourly_pay' => 12.50,
            'hourly_charge' => 20.00,
            'effective_from' => '2025-01-01',
            'effective_to' => null,
        ];
    }
}

