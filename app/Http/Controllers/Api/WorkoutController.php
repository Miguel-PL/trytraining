<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Workout;
use App\Models\Block;
use App\Models\BlockExercise;
use Illuminate\Http\Request;

class WorkoutController extends Controller
{
    public function index(Request $request)
    {
        return Workout::where('user_id', $request->user()->id)->get();
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string',
            'date' => 'required|date',
            'blocks' => 'array'
        ]);

        $workout = Workout::create([
            'name' => $request->name,
            'date' => $request->date,
            'user_id' => $request->user()->id
        ]);

        if ($request->has('blocks')) {
            foreach ($request->blocks as $blockData) {

                $block = Block::create([
                    'workout_id' => $workout->id,
                    'name' => $blockData['name'],
                    'order' => $blockData['order']
                ]);

                if (isset($blockData['exercises'])) {
                    foreach ($blockData['exercises'] as $exerciseData) {

                        BlockExercise::create([
                            'block_id' => $block->id,
                            'exercise_id' => $exerciseData['exercise_id'],
                            'type' => $exerciseData['type'],
                            'reps' => $exerciseData['reps'] ?? null,
                            'time' => $exerciseData['time'] ?? null,
                            'rest' => $exerciseData['rest'] ?? null,
                            'order' => $exerciseData['order']
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

    public function update(Request $request, $id)
    {
        $workout = Workout::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string',
            'date' => 'required|date'
        ]);

        $workout->update($validated);

        return $workout;
    }

    public function display($id)
    {
        $workout = Workout::with('blocks.blockExercises.exercise')->findOrFail($id);

        $result = [
            'name' => $workout->name,
            'blocks' => []
        ];

        foreach ($workout->blocks->sortBy('order') as $block) {

            $blockData = [
                'name' => $block->name,
                'exercises' => []
            ];

            foreach ($block->blockExercises->sortBy('order') as $be) {

                $value = $be->type === 'reps' ? $be->reps : $be->time;

                $blockData['exercises'][] = [
                    'name' => $be->exercise->name,
                    'video_url' => $be->exercise->video_url,
                    'type' => $be->type,
                    'value' => $value,
                    'rest' => $be->rest
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
