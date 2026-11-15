<?php

namespace Database\Factories;

use App\Models\Candidate;
use Illuminate\Database\Eloquent\Factories\Factory;

class CandidateFactory extends Factory
{
    protected $model = Candidate::class;

    public function definition(): array
    {
        // UK Drama telephone number range: 07700 900000 to 07700 900999
        $dramaNumber = '07700 900' . str_pad((string)$this->faker->numberBetween(0, 999), 3, '0', STR_PAD_LEFT);

        return [
            'first_name' => $this->faker->firstName(),
            'last_name' => $this->faker->lastName(),
            'email' => $this->faker->unique()->userName() . '@example.test',
            'phone' => $dramaNumber,
            'postcode' => $this->faker->postcode(),
            'cv_text' => "Professional Care Assistant with experience in adult social care, medication assistance, and personal care. Certifications in First Aid and Moving & Handling.",
            'status' => $this->faker->randomElement(['applied', 'screening', 'interview', 'offered', 'hired', 'rejected']),
            'source' => $this->faker->randomElement(['website', 'agency', 'referral', 'job_board']),
            'applied_at' => $this->faker->dateTimeBetween('-6 months', 'now'),
        ];
    }
}

