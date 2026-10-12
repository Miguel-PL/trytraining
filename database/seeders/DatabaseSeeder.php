<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        $email = config('admin.email');

        // No se modifica la contraseña de un administrador que ya existe.
        if (User::where('email', $email)->exists()) {
            return;
        }

        $password = config('admin.password');

        if (blank($password)) {
            throw new RuntimeException(
                'No se puede crear el usuario administrador: define ADMIN_PASSWORD '
                .'(con un valor no vacío) en el fichero .env antes de ejecutar los seeders.'
            );
        }

        User::create([
            'name' => 'TryTraining',
            'email' => $email,
            'email_verified_at' => now(),
            'password' => Hash::make($password),
        ]);
    }
}
