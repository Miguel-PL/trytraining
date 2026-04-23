<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Category;
use Illuminate\Support\Facades\DB;

class CategoryController extends Controller
{
    public function index()
    {
        $driver = DB::getDriverName();
        $query = Category::query();

        if ($driver === 'mysql') {
            $query->orderByRaw('name COLLATE utf8mb4_spanish_ci asc');
        } elseif ($driver === 'pgsql') {
            $query->orderByRaw('name COLLATE "es_ES" asc');
        } else {
            $query->orderBy('name');
        }

        return $query->get();
    }

    public function show($id)
    {
        return Category::findOrFail($id);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:categories,name'
        ]);

        $category = Category::create($validated);

        return response()->json($category, 201);
    }

    public function exercises($id)
    {
        $category = Category::with('exercises')->findOrFail($id);

        return $category->exercises;
    }

    public function update(Request $request, $id)
    {
        $category = Category::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:255'
        ]);

        $category->update($validated);

        return response()->json($category);
    }

    public function destroy($id)
    {
        $category = Category::findOrFail($id);
        $category->delete();

        return response()->json(['message' => 'Categoría eliminada']);
    }
}
