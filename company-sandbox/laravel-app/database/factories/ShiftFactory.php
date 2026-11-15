<?php

namespace Database\Factories;

use App\Models\Shift;
use App\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;

class ShiftFactory extends Factory
{
    protected $model = Shift::class;

    public function definition(): array
    {
        $startsAt = $this->faker->dateTimeBetween('-8 weeks', '+4 weeks');
        $durationHours = $this->faker->randomElement([8, 10, 12]);
        $endsAt = (clone $startsAt)->modify("+{$durationHours} hours");

        return [
            'client_id' => Client::factory(),
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'role_required' => $this->faker->randomElement(['carer', 'carer', 'senior_carer']),
            'status' => 'open',
            'notes' => $this->faker->optional()->sentence(),
        ];
    }
}

