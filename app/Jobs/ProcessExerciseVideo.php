<?php

namespace App\Jobs;

use App\Models\Exercise;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;
use Throwable;

class ProcessExerciseVideo implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public int $exerciseId,
        public string $tmpInputPathOnLocalDisk,
        public string $outputRelativePathOnPublicDisk,
        public string $processingToken,
    ) {
        //
    }

    public int $timeout = 1200;

    public int $tries = 3;

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $exercise = Exercise::find($this->exerciseId);

        // Si el ejercicio fue eliminado mientras el job estaba en cola, limpiamos y salimos.
        if ($exercise === null) {
            Storage::disk('local')->delete($this->tmpInputPathOnLocalDisk);
            Storage::disk('public')->delete($this->outputRelativePathOnPublicDisk);

            return;
        }

        // Si el ejercicio ya tiene otro token, significa que se subió/reemplazó el vídeo.
        // No sobrescribimos el resultado actual: limpiamos el temporal y salimos.
        if ($exercise->video_processing_token !== $this->processingToken) {
            Storage::disk('local')->delete($this->tmpInputPathOnLocalDisk);
            Storage::disk('public')->delete($this->outputRelativePathOnPublicDisk);

            return;
        }

        $exercise->update([
            'video_status' => 'processing',
        ]);

        $localDisk = Storage::disk('local');
        $publicDisk = Storage::disk('public');

        if (! $localDisk->exists($this->tmpInputPathOnLocalDisk)) {
            $exercise->update(['video_status' => 'failed']);

            return;
        }

        $inputFullPath = $localDisk->path($this->tmpInputPathOnLocalDisk);
        $outputFullPath = $publicDisk->path($this->outputRelativePathOnPublicDisk);

        // Asegura el directorio destino.
        $outputDir = dirname($outputFullPath);
        if (! is_dir($outputDir)) {
            @mkdir($outputDir, 0755, true);
        }

        // Convierte a MP4 (H.264) y etiqueta "faststart" para streaming progresivo.
        // -map 0:a? hace que falle si no hay audio? No: hace "opcional" el audio.
        $command = [
            'ffmpeg',
            '-y',
            '-i',
            $inputFullPath,
            '-map',
            '0:v:0',
            '-map',
            '0:a?',
            '-c:v',
            'libx264',
            '-profile:v',
            'high',
            '-preset',
            'medium',
            '-crf',
            '23',
            '-pix_fmt',
            'yuv420p',
            '-c:a',
            'aac',
            '-b:a',
            '128k',
            '-movflags',
            '+faststart',
            $outputFullPath,
        ];

        try {
            $process = new Process($command);
            $process->setTimeout($this->timeout);
            $process->run();

            if (! $process->isSuccessful()) {
                throw new ProcessFailedException($process);
            }
        } catch (Throwable $e) {
            $localDisk->delete($this->tmpInputPathOnLocalDisk);
            $publicDisk->delete($this->outputRelativePathOnPublicDisk);

            $currentExercise = Exercise::find($this->exerciseId);
            if ($currentExercise !== null && $currentExercise->video_processing_token === $this->processingToken) {
                $currentExercise->update(['video_status' => 'failed']);
            }

            // En producción, también conviene loguear la excepción completa.
            return;
        }

        // Borra el video original temporal tras convertirlo.
        $localDisk->delete($this->tmpInputPathOnLocalDisk);

        // Si el ejercicio se eliminó o cambió de token mientras corría FFmpeg,
        // limpiamos el MP4 y salimos sin sobrescribir.
        $currentExercise = Exercise::find($this->exerciseId);
        if ($currentExercise === null || $currentExercise->video_processing_token !== $this->processingToken) {
            $publicDisk->delete($this->outputRelativePathOnPublicDisk);

            return;
        }

        $currentExercise->update([
            'video_status' => 'ready',
            'video_storage_path' => $this->outputRelativePathOnPublicDisk,
            // `Storage::disk('public')->url()` está disponible en Laravel, pero Intelephense no lo infiere bien.
            // Calculamos la URL a mano usando la configuración del disk `public` (ya incluye `/storage`).
            'video_url' => rtrim((string) config('filesystems.disks.public.url', '/storage'), '/')
                .'/'.ltrim($this->outputRelativePathOnPublicDisk, '/'),
            'video_processing_token' => null,
        ]);
    }
}
