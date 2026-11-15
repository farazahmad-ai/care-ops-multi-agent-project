<?php

namespace Database\Factories;

use App\Models\Invoice;
use App\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;

class InvoiceFactory extends Factory
{
    protected $model = Invoice::class;

    public function definition(): array
    {
        $start = $this->faker->dateTimeBetween('-2 months', '-1 month');
        $end = (clone $start)->modify('+1 month -1 day');

        return [
            'client_id' => Client::factory(),
            'period_start' => $start->format('Y-m-d'),
            'period_end' => $end->format('Y-m-d'),
            'total' => 0.00,
            'status' => 'sent',
        ];
    }
}

