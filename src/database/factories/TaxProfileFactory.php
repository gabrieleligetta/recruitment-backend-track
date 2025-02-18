<?php

namespace Database\Factories;

use App\Models\TaxProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class TaxProfileFactory extends Factory
{
    protected $model = TaxProfile::class;

    public function definition()
    {
        return [
            'user_id'      => User::factory(), // creates a new user if not provided
            'tax_id'       => strtoupper($this->faker->bothify('??####')),
            'company_name' => $this->faker->company,
            'address'      => $this->faker->streetAddress,
            'country'      => $this->faker->country,
            'city'         => $this->faker->city,
            'zip_code'     => $this->faker->postcode,
        ];
    }
}
