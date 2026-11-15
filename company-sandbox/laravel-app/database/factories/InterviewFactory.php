<?php

namespace Database\Factories;

use App\Models\Interview;
use App\Models\Candidate;
use Illuminate\Database\Eloquent\Factories\Factory;

class InterviewFactory extends Factory
{
    protected $model = Interview::class;

    public function definition(): array
    {
        return [
            'candidate_id' => Candidate::factory(),
            'scheduled_at' => $this->faker->dateTimeBetween('-3 months', '+1 month'),
            'interviewer' => $this->faker->name(),
            'outcome' => $this->faker->randomElement(['pending', 'pass', 'fail', 'no_show']),
            'notes' => $this->faker->sentence(),
        ];
    }
}

