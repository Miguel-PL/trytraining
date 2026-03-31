<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Exercise;

class ExerciseController extends Controller
{
    public function index()
    {
        return Exercise::with('categories')->get();
    }

    // Juntar search con index (si entran parametros o no)
    public function search(Request $request)
    {
        $query = Exercise::query();

        // Buscar por nombre
        if ($request->has('name')) {
            $query->where('name', 'like', '%' . $request->name . '%');
        }

        // Filtrar por categoría
        if ($request->has('category_id')) {
            $query->whereHas('categories', function ($q) use ($request) {
                $q->where('categories.id', $request->category_id);
            });
        }

        return $query->with('categories')->get();
    }
    // public function show($id) (ejercicios)

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'video_url' => 'required|url',
            'categories' => 'array'
        ]);

        $exercise = Exercise::create($validated);

        if ($request->has('categories')) {
            $exercise->categories()->attach($request->categories);
        }

        return response()->json($exercise->load('categories'), 201);
    }

    

    public function update(Request $request, $id)
    {
        $exercise = Exercise::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'video_url' => 'required|url'
        ]);

        $exercise->update($validated);

        return response()->json($exercise);
    }

    public function destroy($id)
    {
        $exercise = Exercise::findOrFail($id);
        $exercise->delete();

        return response()->json(['message' => 'Ejercicio eliminado']);
    }
}
