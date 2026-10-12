<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_the_admin_when_a_password_is_configured(): void
    {
        config([
            'admin.email' => 'admin@example.test',
            'admin.password' => 'secreto-seguro',
        ]);

        $this->seed(DatabaseSeeder::class);

        $admin = User::where('email', 'admin@example.test')->first();

        $this->assertNotNull($admin);
        $this->assertTrue(Hash::check('secreto-seguro', $admin->password));
    }

    /**
     * El seeder se invoca directamente para poder capturar la excepción; al
     * ejecutar `db:seed` el kernel de consola la absorbería.
     */
    #[DataProvider('blankPasswordProvider')]
    public function test_it_stops_without_creating_the_admin_when_password_is_blank(?string $password): void
    {
        config([
            'admin.email' => 'admin@example.test',
            'admin.password' => $password,
        ]);

        $thrown = null;

        try {
            (new DatabaseSeeder)->run();
        } catch (RuntimeException $e) {
            $thrown = $e;
        }

        $this->assertNotNull($thrown, 'El seeder debía detenerse si ADMIN_PASSWORD está vacía o no definida.');
        $this->assertStringContainsString('ADMIN_PASSWORD', $thrown->getMessage());
        $this->assertDatabaseMissing('users', ['email' => 'admin@example.test']);
    }

    public static function blankPasswordProvider(): array
    {
        return [
            'no definida' => [null],
            'vacía' => [''],
            'solo espacios' => ['   '],
        ];
    }

    public function test_it_does_not_change_the_password_of_an_existing_admin(): void
    {
        $admin = User::factory()->create([
            'email' => 'admin@example.test',
            'password' => Hash::make('contrasena-original'),
        ]);

        config([
            'admin.email' => 'admin@example.test',
            'admin.password' => 'otra-contrasena',
        ]);

        (new DatabaseSeeder)->run();

        $this->assertTrue(Hash::check('contrasena-original', $admin->fresh()->password));
        $this->assertDatabaseCount('users', 1);
    }

    public function test_it_does_not_require_a_password_when_the_admin_already_exists(): void
    {
        User::factory()->create(['email' => 'admin@example.test']);

        config([
            'admin.email' => 'admin@example.test',
            'admin.password' => null,
        ]);

        (new DatabaseSeeder)->run();

        $this->assertDatabaseCount('users', 1);
    }
}
