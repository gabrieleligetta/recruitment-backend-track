<?php

namespace Database\Factories;

use App\Models\Invoice;
use App\Models\TaxProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class InvoiceFactory extends Factory
{
    protected $model = Invoice::class;

    public function definition()
    {
        return [
            // Either create a new user and tax profile or assume they exist.
            'user_id'         => User::factory(),
            'tax_profile_id'  => TaxProfile::factory(),
            'invoice_number'  => $this->faker->unique()->bothify('INV-####'),
            'description'     => $this->faker->sentence,
            'invoice_date'    => $this->faker->date(),
            'total_amount'    => $this->faker->randomFloat(2, 100, 10000),
            'status'          => $this->faker->randomElement(['pending', 'paid', 'canceled']),
        ];
    }
}
