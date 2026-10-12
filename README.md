# Try Training API

Backend REST API para gestionar rutinas de entrenamiento. Permite administrar
ejercicios y categorías, y componer entrenamientos formados por bloques de
ejercicios. Incluye autenticación por token (Laravel Sanctum), gestión de vídeos
de ejercicios con conversión asíncrona a MP4 y un endpoint público pensado para
mostrar una rutina en la pantalla de un gimnasio.

Proyecto de portfolio desarrollado con Laravel 12. **No está auditado ni
verificado para producción.**

## Tecnologías

- **PHP >= 8.2** y **Laravel 12**
- **Laravel Sanctum** — autenticación por tokens de API (`auth:sanctum`)
- **MySQL / MariaDB** como base de datos principal (SQLite disponible para
  desarrollo local y para las pruebas automatizadas)
- **Colas de Laravel** (driver `database`) — procesado en segundo plano
- **FFmpeg** — conversión de los vídeos subidos a MP4 (H.264)
- **Vite + Tailwind CSS** — andamiaje de assets incluido en el repositorio,
  pero no necesario para consumir la API (la página raíz es HTML autocontenido)

## Requisitos

- PHP 8.2 o superior con las extensiones `pdo`, `mbstring`, `openssl` y
  `fileinfo`.
- [Composer](https://getcomposer.org/) 2.
- MySQL o MariaDB (recomendado). También se admite SQLite.
- Opcional: **Node.js 18+** si quieres compilar los assets del frontend.
- Opcional: **FFmpeg** en el `PATH` si vas a subir vídeos de ejercicios.

## Instalación

```bash
git clone https://github.com/Miguel-PL/trytraining.git
cd trytraining

composer install

# Crea el archivo de entorno
cp .env.example .env        # Linux / macOS
copy .env.example .env      # Windows

php artisan key:generate
```

Después de configurar la base de datos (siguiente apartado), migra y siembra la
base de datos y crea el enlace simbólico usado para servir los vídeos:

```bash
php artisan migrate --seed
php artisan storage:link
```

## Configuración de la base de datos

### MySQL / MariaDB (recomendado)

Crea una base de datos y ajusta las variables en `.env`:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=trytraining
DB_USERNAME=root
DB_PASSWORD=
```

```sql
CREATE DATABASE trytraining CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

### SQLite (desarrollo local y tests)

El proyecto también funciona con SQLite, que es lo que usan las pruebas. Para
usarlo, comenta las variables de MySQL en `.env` y deja solo:

```env
DB_CONNECTION=sqlite
DB_DATABASE=database/database.sqlite
```

Las migraciones son compatibles con ambos motores. MySQL/MariaDB es el motor con
el que se ha desarrollado el proyecto (las migraciones incluyen ajustes
específicos para él).

## Uso

```bash
# Servidor de desarrollo en http://localhost:8000
php artisan serve

# Worker de colas: necesario para procesar los vídeos subidos
php artisan queue:work

# Opcional: assets del frontend
npm install
npm run dev
```

El seeder `DatabaseSeeder` crea un usuario administrador:

- `ADMIN_EMAIL`: correo del administrador (por defecto `info@trytraining.es`).
- `ADMIN_PASSWORD`: contraseña que se usará al crear un administrador **nuevo**.
  Es obligatoria: si no está definida o está vacía, el seeder se detiene con un
  error y no crea ninguna cuenta.
- Si ya existe un usuario con ese correo, el seeder no cambia su contraseña
  (solo crea el administrador cuando no existe).

## Endpoints de la API

El prefijo de la API es `/api`. Los endpoints marcados como protegidos requieren
la cabecera `Authorization: Bearer <token>`.

| Método | Ruta | Acceso | Descripción |
| --- | --- | --- | --- |
| `POST` | `/api/login` | Público | Obtiene un token de acceso |
| `POST` | `/api/logout` | Protegido | Revoca el token actual |
| `GET` | `/api/exercises` | Protegido | Lista de ejercicios (paginada, filtros `name`, `category_id`, `all`) |
| `POST` | `/api/exercises` | Protegido | Crea un ejercicio |
| `GET` | `/api/exercises/{id}` | Protegido | Detalle de un ejercicio |
| `PUT` | `/api/exercises/{id}` | Protegido | Actualiza un ejercicio |
| `DELETE` | `/api/exercises/{id}` | Protegido | Elimina un ejercicio |
| `GET` | `/api/categories` | Protegido | Lista de categorías |
| `GET` | `/api/categories/{id}` | Protegido | Detalle de una categoría |
| `POST` | `/api/categories` | Protegido | Crea una categoría |
| `PUT` | `/api/categories/{id}` | Protegido | Actualiza una categoría |
| `DELETE` | `/api/categories/{id}` | Protegido | Elimina una categoría |
| `GET` | `/api/categories/{id}/exercises` | Protegido | Ejercicios de una categoría |
| `POST` | `/api/workouts` | Protegido | Crea un entrenamiento (con bloques y ejercicios) |
| `GET` | `/api/workouts` | Protegido | Lista los entrenamientos del usuario (paginada, filtro `name`) |
| `GET` | `/api/workouts/{id}` | Protegido | Detalle de un entrenamiento propio |
| `PUT` | `/api/workouts/{id}` | Protegido | Actualiza un entrenamiento propio |
| `DELETE` | `/api/workouts/{id}` | Protegido | Elimina un entrenamiento propio |
| `GET` | `/api/workouts/{id}/display` | **Público** | Rutina lista para la pantalla del gimnasio |

### Sobre `GET /api/workouts/{id}/display`

Es público de forma intencionada: alimenta una pantalla que muestra la rutina sin
que nadie inicie sesión. La respuesta está limitada a los datos de la rutina
(nombre del entrenamiento, bloques y, por cada ejercicio, nombre, vídeo, tipo,
valor e intensidad). **No** expone datos del usuario propietario.

## Pruebas

```bash
php artisan test
```

Las pruebas se ejecutan sobre SQLite en memoria (configurado en `phpunit.xml`) e
incluyen cobertura de la autenticación, la propiedad de los entrenamientos, la
validación de datos de entrada y el endpoint público `display`.

## Estructura del dominio

- `User` — propietario de los entrenamientos.
- `Workout` — entrenamiento de un usuario; contiene bloques ordenados.
- `Block` — bloque dentro de un entrenamiento; contiene ejercicios ordenados.
- `BlockExercise` — ejercicio dentro de un bloque (`type` `reps` o `time`).
- `Exercise` — ejercicio de la biblioteca (con vídeo).
- `Category` — categoría; relación muchos-a-muchos con `Exercise`.

## Licencia

Distribuido bajo la licencia [MIT](https://opensource.org/licenses/MIT).
