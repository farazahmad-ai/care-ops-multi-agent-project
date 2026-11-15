<?php

namespace Database\Factories;

use App\Models\InvoiceLine;
use App\Models\Invoice;
use App\Models\Timesheet;
use Illuminate\Database\Eloquent\Factories\Factory;

class InvoiceLineFactory extends Factory
{
    protected $model = InvoiceLine::class;

    public function definition(): array
    {
        return [
            'invoice_id' => Invoice::factory(),
            'timesheet_id' => Timesheet::factory(),
            'hours' => 8.00,
            'rate' => 20.00,
            'amount' => 160.00,
        ];
    }
}

