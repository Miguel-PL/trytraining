<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Block;
use App\Models\BlockExercise;
use App\Models\Workout;
use Illuminate\Http\Request;

// use Illuminate\Support\Facades\DB; // Sugerencia de transición para el update de workout

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

    public function index(Request $request)
    {
        $query = Workout::where('user_id', $request->user()->id)
            ->orderBy('date', 'desc');

        // ordenar por fecha (reciente primero)
        $query->orderBy('date', 'desc');

        // filtro por nombre
        if ($request->has('name')) {
            $query->where('name', 'like', '%'.$request->name.'%');
        }

        return $query->paginate(10);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string',
            'date' => 'required|date',
            'blocks' => 'array',
            'blocks.*.exercises.*.reps' => $this->nullableExerciseTextRules(),
            'blocks.*.exercises.*.time' => $this->nullableExerciseTextRules(),
            'blocks.*.exercises.*.intensity' => $this->nullableExerciseTextRules(),
        ]);

        $workout = Workout::create([
            'name' => $request->name,
            'date' => $request->date,
            'user_id' => $request->user()->id,
        ]);

        if ($request->has('blocks')) {
            foreach ($request->blocks as $blockData) {

                $block = Block::create([
                    'workout_id' => $workout->id,
                    'name' => $blockData['name'],
                    'order' => $blockData['order'],
                ]);

                if (isset($blockData['exercises'])) {
                    foreach ($blockData['exercises'] as $exerciseData) {

                        BlockExercise::create([
                            'block_id' => $block->id,
                            'exercise_id' => $exerciseData['exercise_id'],
                            'type' => $exerciseData['type'],
                            'reps' => $exerciseData['reps'] ?? null,
                            'time' => $exerciseData['time'] ?? null,
                            'intensity' => $exerciseData['intensity'] ?? null,
                            'order' => $exerciseData['order'],
                        ]);
                    }
                }
            }
        }

        return response()->json($workout->load('blocks.blockExercises.exercise'), 201);
    }

    public function show(Request $request, $id)
    {
        $workout = Workout::where('user_id', $request->user()->id)
            ->with('blocks.blockExercises.exercise')
            ->findOrFail($id);

        return $workout;
    }

    // DB::transaction(function () use ($request, $workout) {// TODA lógica aquí}); // por si queremos hacer transición al actualizar
    public function update(Request $request, $id)
    {
        $workout = Workout::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string',
            'date' => 'required|date',
            'blocks' => 'array',
            'blocks.*.exercises.*.reps' => $this->nullableExerciseTextRules(),
            'blocks.*.exercises.*.time' => $this->nullableExerciseTextRules(),
            'blocks.*.exercises.*.intensity' => $this->nullableExerciseTextRules(),
        ]);

        $workout->update([
            'name' => $validated['name'],
            'date' => $validated['date'],
        ]);
        $existingBlockIds = $workout->blocks()->pluck('id')->toArray();

        $incomingBlockIds = collect($request->blocks ?? [])
            ->pluck('id')
            ->filter()
            ->toArray();

        $blocksToDelete = array_diff($existingBlockIds, $incomingBlockIds);

        Block::destroy($blocksToDelete);

        foreach ($request->blocks as $blockData) {

            if (isset($blockData['id'])) {
                // UPDATE
                $block = $workout->blocks()->findOrFail($blockData['id']);
                $block->update([
                    'name' => $blockData['name'],
                    'order' => $blockData['order'],
                ]);
            } else {
                // CREATE
                $block = Block::create([
                    'workout_id' => $workout->id,
                    'name' => $blockData['name'],
                    'order' => $blockData['order'],
                ]);
            }
            $existingExerciseIds = $block->blockExercises()->pluck('id')->toArray();

            $incomingExerciseIds = collect($blockData['exercises'] ?? [])
                ->pluck('id')
                ->filter()
                ->toArray();

            $exercisesToDelete = array_diff($existingExerciseIds, $incomingExerciseIds);

            BlockExercise::destroy($exercisesToDelete);
            foreach ($blockData['exercises'] ?? [] as $exerciseData) {

                if (isset($exerciseData['id'])) {
                    // UPDATE
                    $be = $block->blockExercises()->findOrFail($exerciseData['id']);

                    $be->update([
                        'exercise_id' => $exerciseData['exercise_id'],
                        'type' => $exerciseData['type'],
                        'reps' => $exerciseData['reps'] ?? null,
                        'time' => $exerciseData['time'] ?? null,
                        'intensity' => $exerciseData['intensity'] ?? null,
                        'order' => $exerciseData['order'],
                    ]);
                } else {
                    // CREATE
                    BlockExercise::create([
                        'block_id' => $block->id,
                        'exercise_id' => $exerciseData['exercise_id'],
                        'type' => $exerciseData['type'],
                        'reps' => $exerciseData['reps'] ?? null,
                        'time' => $exerciseData['time'] ?? null,
                        'intensity' => $exerciseData['intensity'] ?? null,
                        'order' => $exerciseData['order'],
                    ]);
                }
            }
        }

        return response()->json(
            $workout->load('blocks.blockExercises.exercise')
        );
    }

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
