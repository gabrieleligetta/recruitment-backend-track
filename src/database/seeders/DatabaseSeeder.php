<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\TaxProfile;
use App\Models\Invoice;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database with 100-200 records per model.
     */
    public function run(): void
    {
        //Create an admin user
        User::factory()->create([
            'name'  => 'Admin User',
            'email' => 'admin@example.com',
            'role'  => 'admin',
            'password' => Hash::make('defaultPassword'),
        ]);
        $this->command->info("Default Admin User Created");

        //Create 50 users (to ensure at least 100-200 records in related tables)
        $users = User::factory(50)->create();

        //Assign tax profiles and invoices to each user
        $users->each(function ($user) {
            // Each user gets 2-4 tax profiles
            $taxProfiles = TaxProfile::factory(rand(2, 4))->create(['user_id' => $user->id]);

            // Each tax profile gets 5-10 invoices
            $taxProfiles->each(function ($taxProfile) use ($user) {
                Invoice::factory(rand(5, 10))->create([
                    'user_id'        => $user->id,
                    'tax_profile_id' => $taxProfile->id,
                ]);
            });
        });

        //Output seeding summary
        $this->command->info("Database seeding completed successfully with:");
        $this->command->info(User::count() . " users created.");
        $this->command->info(TaxProfile::count() . " tax profiles created.");
        $this->command->info(Invoice::count() . " invoices created.");
    }
}
