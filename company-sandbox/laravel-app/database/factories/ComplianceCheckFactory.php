<?php

namespace Database\Factories;

use App\Models\ComplianceCheck;
use Illuminate\Database\Eloquent\Factories\Factory;

class ComplianceCheckFactory extends Factory
{
    protected $model = ComplianceCheck::class;

    public function definition(): array
    {
        $issuedOn = $this->faker->dateTimeBetween('-1 year', 'now');
        $expiresOn = (clone $issuedOn)->modify('+1 year');

        return [
            'carer_id' => null,
            'candidate_id' => null,
            'type' => $this->faker->randomElement(['dbs', 'right_to_work', 'moving_handling', 'safeguarding']),
            'issued_on' => $issuedOn->format('Y-m-d'),
            'expires_on' => $expiresOn->format('Y-m-d'),
            'status' => 'valid',
        ];
    }
}

