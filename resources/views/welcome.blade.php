<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ config('app.name', 'Try Training') }}</title>
        <style>
            :root {
                color-scheme: light dark;
                --bg: #0f172a;
                --card: #1e293b;
                --text: #e2e8f0;
                --muted: #94a3b8;
                --accent: #38bdf8;
                --border: #334155;
            }

            * { box-sizing: border-box; }

            body {
                margin: 0;
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 2rem 1rem;
                font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
                background: var(--bg);
                color: var(--text);
                line-height: 1.5;
            }

            main {
                width: 100%;
                max-width: 42rem;
                background: var(--card);
                border: 1px solid var(--border);
                border-radius: 0.75rem;
                padding: 2rem;
            }

            h1 { margin: 0 0 0.25rem; font-size: 1.75rem; }
            p.lead { margin: 0 0 1.5rem; color: var(--muted); }
            h2 { margin: 1.5rem 0 0.5rem; font-size: 1rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--accent); }

            ul { margin: 0; padding-left: 1.25rem; }
            li { margin-bottom: 0.35rem; }
            code {
                font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
                background: rgba(56, 189, 248, 0.12);
                color: var(--accent);
                padding: 0.1rem 0.35rem;
                border-radius: 0.25rem;
                font-size: 0.875em;
            }

            footer { margin-top: 2rem; font-size: 0.875rem; color: var(--muted); }
            footer a { color: var(--accent); }
        </style>
    </head>
    <body>
        <main>
            <h1>{{ config('app.name', 'Try Training') }}</h1>
            <p class="lead">
                API REST para gestionar rutinas de entrenamiento: ejercicios,
                categorías y entrenamientos compuestos por bloques.
            </p>

            <h2>Autenticación</h2>
            <ul>
                <li><code>POST /api/login</code> — obtiene un token de Sanctum.</li>
                <li><code>POST /api/logout</code> — revoca el token actual.</li>
            </ul>

            <h2>Recursos protegidos (Bearer token)</h2>
            <ul>
                <li><code>/api/exercises</code> — CRUD de ejercicios.</li>
                <li><code>/api/categories</code> — CRUD de categorías.</li>
                <li><code>/api/workouts</code> — CRUD de entrenamientos del usuario autenticado.</li>
            </ul>

            <h2>Pantalla pública</h2>
            <ul>
                <li><code>GET /api/workouts/{id}/display</code> — rutina lista para mostrar en la pantalla del gimnasio.</li>
            </ul>

            <footer>
                Estado del servicio: <a href="/up">/up</a>
            </footer>
        </main>
    </body>
</html>
