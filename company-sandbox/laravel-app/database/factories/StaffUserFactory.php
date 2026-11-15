<?php

namespace Database\Factories;

use App\Models\StaffUser;
use Illuminate\Database\Eloquent\Factories\Factory;

class StaffUserFactory extends Factory
{
    protected $model = StaffUser::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->name(),
            'email' => $this->faker->unique()->userName() . '@example.test',
            'department' => $this->faker->randomElement(['recruitment', 'hr', 'operations', 'finance']),
        ];
    }
}

