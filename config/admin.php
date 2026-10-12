<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Usuario administrador creado por el seeder
    |--------------------------------------------------------------------------
    |
    | Credenciales que usa DatabaseSeeder. Se leen aquí (y no con env()
    | directamente en el seeder) para que sigan funcionando cuando la
    | configuración esté cacheada con `php artisan config:cache`.
    |
    */

    'email' => env('ADMIN_EMAIL', 'info@trytraining.es'),

    /*
    | Sin valor por defecto a propósito: si ADMIN_PASSWORD no está definida,
    | el seeder debe detenerse en lugar de crear una cuenta con una
    | contraseña conocida o vacía.
    */

    'password' => env('ADMIN_PASSWORD'),

];
