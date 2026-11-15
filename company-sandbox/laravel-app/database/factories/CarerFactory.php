<?php

namespace Database\Factories;

use App\Models\Carer;
use Illuminate\Database\Eloquent\Factories\Factory;

class CarerFactory extends Factory
{
    protected $model = Carer::class;

    public function definition(): array
    {
        $dramaNumber = '07700 900' . str_pad((string)$this->faker->numberBetween(0, 999), 3, '0', STR_PAD_LEFT);

        return [
            'first_name' => $this->faker->firstName(),
            'last_name' => $this->faker->lastName(),
            'email' => $this->faker->unique()->userName() . '@example.test',
            'phone' => $dramaNumber,
            'postcode' => $this->faker->postcode(),
            'role' => $this->faker->randomElement(['carer', 'carer', 'carer', 'senior_carer']),
            'status' => $this->faker->randomElement(['active', 'active', 'active', 'suspended', 'left']),
            'hired_at' => $this->faker->dateTimeBetween('-2 years', '-1 month'),
            'candidate_id' => null,
        ];
    }
}

