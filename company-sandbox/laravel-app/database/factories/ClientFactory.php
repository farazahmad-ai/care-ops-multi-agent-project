<?php

namespace Database\Factories;

use App\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;

class ClientFactory extends Factory
{
    protected $model = Client::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->company() . ' Care Home',
            'type' => $this->faker->randomElement(['care_home', 'care_home', 'domiciliary']),
            'address_line' => $this->faker->streetAddress(),
            'postcode' => $this->faker->postcode(),
            'contact_name' => $this->faker->name(),
            'contact_email' => $this->faker->unique()->userName() . '@example.test',
            'status' => 'active',
        ];
    }
}

