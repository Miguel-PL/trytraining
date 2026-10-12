<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Block;
use App\Models\BlockExercise;
use App\Models\Workout;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WorkoutController extends Controller
{
    /** @return array<int, \Closure> */
    private function nullableExerciseTextRules(): array
    {
        return [function (string $attribute, mixed $value, \Closure $fail): void {
            if ($value === null) {
                return;
            }
            if (! is_scalar($value)) {
                $fail(__('validation.string'));

                return;
            }
            if (strlen((string) $value) > 255) {
                $fail(__('validation.max.string', ['attribute' => $attribute, 'max' => 255]));
            }
        }];
    }

    /**
     * Reglas compartidas por store() y update().
     *
     * @return array<string, mixed>
     */
    private function workoutRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'date' => ['required', 'date'],
            'blocks' => ['sometimes', 'array'],
            'blocks.*.id' => ['sometimes', 'nullable', 'integer'],
            'blocks.*.name' => ['required', 'string', 'max:255'],
            'blocks.*.order' => ['required', 'integer', 'min:0'],
            'blocks.*.exercises' => ['sometimes', 'array'],
            'blocks.*.exercises.*.id' => ['sometimes', 'nullable', 'integer'],
            'blocks.*.exercises.*.exercise_id' => ['required', 'integer', 'exists:exercises,id'],
            'blocks.*.exercises.*.type' => ['required', 'string', 'in:reps,time'],
            'blocks.*.exercises.*.reps' => $this->nullableExerciseTextRules(),
            'blocks.*.exercises.*.time' => $this->nullableExerciseTextRules(),
            'blocks.*.exercises.*.intensity' => $this->nullableExerciseTextRules(),
            'blocks.*.exercises.*.order' => ['required', 'integer', 'min:0'],
        ];
    }

    public function index(Request $request)
    {
        $query = Workout::where('user_id', $request->user()->id)
            ->orderBy('date', 'desc');

        // filtro por nombre
        if ($request->filled('name')) {
            $query->where('name', 'like', '%'.$request->name.'%');
        }

        return $query->paginate(10);
    }

    public function store(Request $request)
    {
        $validated = $request->validate($this->workoutRules());

        $workout = DB::transaction(function () use ($request, $validated) {
            $workout = Workout::create([
                'name' => $validated['name'],
                'date' => $validated['date'],
                'user_id' => $request->user()->id,
            ]);

            $this->syncBlocks($workout, $validated['blocks'] ?? []);

            return $workout;
        });

        return response()->json($workout->load('blocks.blockExercises.exercise'), 201);
    }

    public function show(Request $request, $id)
    {
        $workout = Workout::where('user_id', $request->user()->id)
            ->with('blocks.blockExercises.exercise')
            ->findOrFail($id);

        return $workout;
    }

    public function update(Request $request, $id)
    {
        // Solo el propietario puede modificar el entrenamiento.
        $workout = Workout::where('user_id', $request->user()->id)
            ->findOrFail($id);

        $validated = $request->validate($this->workoutRules());

        DB::transaction(function () use ($workout, $validated) {
            $workout->update([
                'name' => $validated['name'],
                'date' => $validated['date'],
            ]);

            $this->syncBlocks($workout, $validated['blocks'] ?? []);
        });

        return response()->json(
            $workout->load('blocks.blockExercises.exercise')
        );
    }

    /**
     * Sincroniza (crea, actualiza y elimina) los bloques del entrenamiento.
     *
     * Los identificadores recibidos se resuelven siempre a través de la
     * relación del entrenamiento (`$workout->blocks()->findOrFail()`), de modo
     * que un id de otro entrenamiento provoca un 404 en lugar de modificar
     * datos ajenos.
     *
     * @param  array<int, array<string, mixed>>  $blocks
     */
    private function syncBlocks(Workout $workout, array $blocks): void
    {
        $existingBlockIds = $workout->blocks()->pluck('id')->all();
        $incomingBlockIds = collect($blocks)->pluck('id')->filter()->all();

        Block::destroy(array_diff($existingBlockIds, $incomingBlockIds));

        foreach ($blocks as $blockData) {
            if (isset($blockData['id'])) {
                $block = $workout->blocks()->findOrFail($blockData['id']);

                $block->update([
                    'name' => $blockData['name'],
                    'order' => $blockData['order'],
                ]);
            } else {
                $block = Block::create([
                    'workout_id' => $workout->id,
                    'name' => $blockData['name'],
                    'order' => $blockData['order'],
                ]);
            }

            $this->syncBlockExercises($block, $blockData['exercises'] ?? []);
        }
    }

    /**
     * Sincroniza (crea, actualiza y elimina) los ejercicios de un bloque.
     *
     * Igual que en syncBlocks(), los ids se resuelven a través de la relación
     * del bloque para impedir que se modifiquen ejercicios de otro bloque.
     *
     * @param  array<int, array<string, mixed>>  $exercises
     */
    private function syncBlockExercises(Block $block, array $exercises): void
    {
        $existingExerciseIds = $block->blockExercises()->pluck('id')->all();
        $incomingExerciseIds = collect($exercises)->pluck('id')->filter()->all();

        BlockExercise::destroy(array_diff($existingExerciseIds, $incomingExerciseIds));

        foreach ($exercises as $exerciseData) {
            $attributes = [
                'exercise_id' => $exerciseData['exercise_id'],
                'type' => $exerciseData['type'],
                'reps' => $exerciseData['reps'] ?? null,
                'time' => $exerciseData['time'] ?? null,
                'intensity' => $exerciseData['intensity'] ?? null,
                'order' => $exerciseData['order'],
            ];

            if (isset($exerciseData['id'])) {
                $blockExercise = $block->blockExercises()->findOrFail($exerciseData['id']);

                $blockExercise->update($attributes);
            } else {
                BlockExercise::create($attributes + ['block_id' => $block->id]);
            }
        }
    }

    /**
     * Endpoint público pensado para la pantalla del gimnasio.
     *
     * Expone únicamente el contenido de la rutina (nombre, bloques y
     * ejercicios con su vídeo y parámetros). No incluye datos del usuario
     * propietario, por lo que permanece fuera de `auth:sanctum`.
     */
    public function display($id)
    {
        $workout = Workout::with('blocks.blockExercises.exercise')->findOrFail($id);

        $result = [
            'name' => $workout->name,
            'blocks' => [],
        ];

        foreach ($workout->blocks as $block) {
            $blockData = [
                'name' => $block->name,
                'exercises' => [],
            ];

            foreach ($block->blockExercises as $be) {
                $value = $be->type === 'reps' ? $be->reps : $be->time;

                $blockData['exercises'][] = [
                    'name' => $be->exercise->name,
                    'video_url' => $be->exercise->video_url,
                    'video_status' => $be->exercise->video_status,
                    'type' => $be->type,
                    'value' => $value,
                    'intensity' => $be->intensity,
                ];
            }

            $result['blocks'][] = $blockData;
        }

        return $result;
    }

    public function destroy(Request $request, $id)
    {
        $workout = Workout::where('user_id', $request->user()->id)
            ->findOrFail($id);

        $workout->delete();

        return response()->json(['message' => 'Workout eliminado']);
    }
}
