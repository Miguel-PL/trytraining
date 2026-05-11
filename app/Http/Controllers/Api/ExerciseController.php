<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ExerciseResource;
use App\Jobs\ProcessExerciseVideo;
use App\Models\Exercise;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ExerciseController extends Controller
{
    /**
     * Ajusta datos típicos de multipart (FormData) antes de validar.
     */
    private function normalizeExerciseRequest(Request $request): void
    {
        if ($request->exists('video_url')) {
            $v = $request->input('video_url');
            if ($v === '' || $v === null) {
                $request->merge(['video_url' => null]);
            }
        }

        if ($request->has('categories') && is_string($request->input('categories'))) {
            $decoded = json_decode($request->input('categories'), true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $request->merge(['categories' => $decoded]);
            }
        }
    }

    /**
     * Si el cliente intentó subir video_file y PHP rechazó la subida (tamaño, etc.), falla con un mensaje claro.
     */
    private function rejectInvalidPhpVideoUpload(): void
    {
        $meta = $_FILES['video_file'] ?? null;
        if (! is_array($meta)) {
            return;
        }

        $name = $meta['name'] ?? '';
        $error = (int) ($meta['error'] ?? UPLOAD_ERR_NO_FILE);

        if ($name === '' && $error === UPLOAD_ERR_NO_FILE) {
            return;
        }

        if ($error === UPLOAD_ERR_OK) {
            return;
        }

        throw ValidationException::withMessages([
            'video_file' => [$this->phpUploadErrorMessage($error)],
        ]);
    }

    private function phpUploadErrorMessage(int $code): string
    {
        return match ($code) {
            UPLOAD_ERR_INI_SIZE => 'El vídeo supera los límites de PHP: upload_max_filesize='.ini_get('upload_max_filesize').' y post_max_size='.ini_get('post_max_size').'. Sube ambos valores en el php.ini que usa el sitio (Herd → PHP del dominio, o php-fpm de nginx), reinicia PHP y vuelve a intentarlo.',
            UPLOAD_ERR_FORM_SIZE => 'El vídeo supera el límite MAX_FILE_SIZE del formulario HTML.',
            UPLOAD_ERR_PARTIAL => 'La subida del vídeo quedó incompleta. Vuelve a intentarlo.',
            UPLOAD_ERR_NO_TMP_DIR => 'El servidor no tiene carpeta temporal para subidas (upload_tmp_dir).',
            UPLOAD_ERR_CANT_WRITE => 'El servidor no pudo escribir el vídeo en disco.',
            UPLOAD_ERR_EXTENSION => 'Una extensión de PHP bloqueó la subida del vídeo.',
            default => 'La subida del vídeo falló (código PHP '.$code.').',
        };
    }

    public function index(Request $request)
    {
        $query = Exercise::query();

        if ($request->has('all')) {
            $driver = DB::getDriverName();
            $allQuery = Exercise::with('categories');

            if ($driver === 'mysql') {
                $allQuery->orderByRaw('name COLLATE utf8mb4_spanish_ci asc');
            } elseif ($driver === 'pgsql') {
                $allQuery->orderByRaw('name COLLATE "es_ES" asc');
            } else {
                $allQuery->orderBy('name');
            }

            return $allQuery->get();
        }

        // Buscar por nombre
        if ($request->has('name')) {
            $query->where('name', 'like', '%'.$request->name.'%');
        }

        // Filtrar por categoría
        if ($request->has('category_id')) {
            $query->whereHas('categories', function ($q) use ($request) {
                $q->where('categories.id', $request->category_id);
            });
        }

        $driver = DB::getDriverName();
        if ($driver === 'mysql') {
            $query->orderByRaw('name COLLATE utf8mb4_spanish_ci asc');
        } elseif ($driver === 'pgsql') {
            $query->orderByRaw('name COLLATE "es_ES" asc');
        } else {
            $query->orderBy('name');
        }

        return ExerciseResource::collection(
            $query->with('categories')->paginate(10)
        );
    }

    public function show($id)
    {
        $exercise = Exercise::with('categories')->findOrFail($id);

        return new ExerciseResource($exercise);
    }

    public function store(Request $request)
    {
        $this->normalizeExerciseRequest($request);
        $this->rejectInvalidPhpVideoUpload();

        $validated = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'video_url' => 'nullable|string|max:255',
            'video_file' => ['nullable', 'file', 'max:102400'],
            'categories' => 'nullable|array',
            'categories.*' => 'integer',
        ])->after(function ($validator) use ($request): void {
            if ($request->hasFile('video_file')) {
                return;
            }
            if ($request->filled('video_url')) {
                return;
            }
            $meta = $_FILES['video_file'] ?? null;
            if (is_array($meta) && (($meta['name'] ?? '') !== '') && (int) ($meta['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                return;
            }
            $validator->errors()->add('video_file', 'Debes enviar video_url (enlace externo) o video_file (subida).');
        })->validate();

        $processingToken = $request->hasFile('video_file') ? (string) Str::uuid() : null;

        $exercise = Exercise::create([
            'name' => $validated['name'],
            'video_url' => $validated['video_url'] ?? null,
            'video_status' => $processingToken !== null ? 'uploading' : 'ready',
            'video_storage_path' => null,
            'video_processing_token' => $processingToken,
        ]);

        if (! empty($validated['categories'])) {
            $exercise->categories()->attach($validated['categories']);
        }

        if ($request->hasFile('video_file')) {
            $file = $request->file('video_file');
            $ext = $file->getClientOriginalExtension() ?: 'mp4';
            $uuid = (string) Str::uuid();

            // Guardamos el original temporalmente (no público) y lo borramos al terminar.
            $tmpPath = $file->storeAs(
                "exercise-video-tmp/{$exercise->id}",
                "{$uuid}.{$ext}",
                'local'
            );

            $outputRelativePath = "videos/{$exercise->id}/{$uuid}.mp4";

            $exercise->update([
                'video_url' => null,
                'video_status' => 'uploading',
                'video_storage_path' => null,
                'video_processing_token' => $processingToken,
            ]);

            ProcessExerciseVideo::dispatch(
                $exercise->id,
                $tmpPath,
                $outputRelativePath,
                $processingToken,
            );
        }

        return (new ExerciseResource($exercise->load('categories')))
            ->response()
            ->setStatusCode(201);
    }

    public function update(Request $request, $id)
    {
        $exercise = Exercise::findOrFail($id);

        $this->normalizeExerciseRequest($request);
        $this->rejectInvalidPhpVideoUpload();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'video_url' => 'nullable|string|max:255',
            'video_file' => ['nullable', 'file', 'max:102400'],
            'categories' => 'nullable|array',
            'categories.*' => 'integer',
        ]);

        $exercise->update([
            'name' => $validated['name'],
        ]);

        // 🔥 sincronizar categorías
        if (array_key_exists('categories', $validated)) {
            $exercise->categories()->sync($validated['categories'] ?? []);
        }

        // Si envían un nuevo link externo, lo usamos y borramos el MP4 local anterior.
        if (array_key_exists('video_url', $validated) && $validated['video_url'] !== null) {
            if (! empty($exercise->video_storage_path)) {
                Storage::disk('public')->delete($exercise->video_storage_path);
            }

            $exercise->update([
                'video_url' => $validated['video_url'],
                'video_status' => 'ready',
                'video_storage_path' => null,
                'video_processing_token' => null,
            ]);
        }

        // Si envían archivo, procesamos en background y reemplazamos el MP4 anterior.
        if ($request->hasFile('video_file')) {
            if (! empty($exercise->video_storage_path)) {
                Storage::disk('public')->delete($exercise->video_storage_path);
            }

            $file = $request->file('video_file');
            $ext = $file->getClientOriginalExtension() ?: 'mp4';
            $uuid = (string) Str::uuid();
            $processingToken = (string) Str::uuid();

            $tmpPath = $file->storeAs(
                "exercise-video-tmp/{$exercise->id}",
                "{$uuid}.{$ext}",
                'local'
            );

            $outputRelativePath = "videos/{$exercise->id}/{$uuid}.mp4";

            $exercise->update([
                'video_url' => null,
                'video_status' => 'uploading',
                'video_storage_path' => null,
                'video_processing_token' => $processingToken,
            ]);

            ProcessExerciseVideo::dispatch(
                $exercise->id,
                $tmpPath,
                $outputRelativePath,
                $processingToken,
            );
        }

        return new ExerciseResource($exercise->load('categories'));
    }

    public function destroy($id)
    {
        $exercise = Exercise::findOrFail($id);

        if (! empty($exercise->video_storage_path)) {
            Storage::disk('public')->delete($exercise->video_storage_path);
        }
        $exercise->delete();

        return response()->json(['message' => 'Ejercicio eliminado']);
    }
}
