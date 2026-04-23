<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Exercise;
use Illuminate\Support\Facades\DB;

class ExerciseController extends Controller
{
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
            $query->where('name', 'like', '%' . $request->name . '%');
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

        return $query->with('categories')->paginate(10);
    }

    public function show($id)
    {
        $exercise = Exercise::with('categories')->findOrFail($id);

        return response()->json($exercise);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'video_url' => 'required|string|max:255',
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
            'video_url' => 'required|string|max:255',
            'categories' => 'array'
        ]);

        $exercise->update([
            'name' => $validated['name'],
            'video_url' => $validated['video_url']
        ]);

        // 🔥 sincronizar categorías
        if ($request->has('categories')) {
            $exercise->categories()->sync($request->categories);
        }

        return response()->json(
            $exercise->load('categories')
        );
    }

    public function destroy($id)
    {
        $exercise = Exercise::findOrFail($id);
        $exercise->delete();

        return response()->json(['message' => 'Ejercicio eliminado']);
    }
}
