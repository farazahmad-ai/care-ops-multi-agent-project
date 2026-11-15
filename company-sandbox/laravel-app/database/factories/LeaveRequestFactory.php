<?php

namespace Database\Factories;

use App\Models\LeaveRequest;
use App\Models\Carer;
use Illuminate\Database\Eloquent\Factories\Factory;

class LeaveRequestFactory extends Factory
{
    protected $model = LeaveRequest::class;

    public function definition(): array
    {
        $startDate = $this->faker->dateTimeBetween('-4 weeks', '+4 weeks');
        $endDate = (clone $startDate)->modify('+3 days');

        return [
            'carer_id' => Carer::factory(),
            'start_date' => $startDate->format('Y-m-d'),
            'end_date' => $endDate->format('Y-m-d'),
            'type' => $this->faker->randomElement(['annual', 'sick', 'unpaid']),
            'status' => 'approved',
            'requested_at' => (clone $startDate)->modify('-2 weeks'),
            'decided_at' => (clone $startDate)->modify('-1 week'),
        ];
    }
}

