<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Create a single admin user and their personal company
        $adminUser = User::factory()
            ->withPersonalCompany()  // Ensures the user has a personal company created alongside
            ->create([
                'name' => 'Admin',
                'email' => 'admin@gmail.com',
                'password' => bcrypt('password'),
                'current_company_id' => 1,  // Assuming this will be the ID of the created company
                'created_at' => now(),
            ]);

        // Optionally, set additional properties or create related entities specific to this company
        $adminUser->ownedCompanies->first()->update([
            'name' => 'TSM',
            'created_at' => now(),
        ]);
    }
}
