<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Hapus seed dummy sebelumnya, buat satu akun Superadmin
        $this->call(RolePermissionSeeder::class);

        User::factory()->create([
            'name' => 'Superadmin Sintux',
            'email' => 'superadmin@sintux.com',
            'role' => 'superadmin',
            'password' => bcrypt('password'),
        ]);
    }
}
