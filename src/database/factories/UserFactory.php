<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class UserFactory extends Factory
{
    // The name of the corresponding model.
    protected $model = User::class;

    public function definition()
    : array
    {
        return [
            'name'              => $this->faker->name,
            'email'             => $this->faker->unique()->safeEmail,
            'email_verified_at' => now(),
            'password'          => bcrypt('password'), // default password
            'role'              => 'user', // default role, can be changed later with a state
            'remember_token'    => Str::random(10),
        ];
    }

    /**
     * Indicate that the user is an administrator.
     */
    public function admin()
    : UserFactory
    {
        return $this->state([
            'role' => 'admin',
        ]);
    }
}
