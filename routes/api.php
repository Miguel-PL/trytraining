<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ExerciseController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\WorkoutController;
use App\Http\Controllers\Api\AuthController;



Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {

    Route::post('/logout', [AuthController::class, 'logout']);

    Route::get('/exercises', [ExerciseController::class, 'index']);
    Route::post('/exercises', [ExerciseController::class, 'store']);
    Route::put('/exercises/{id}', [ExerciseController::class, 'update']);
    Route::delete('/exercises/{id}', [ExerciseController::class, 'destroy']);
    Route::get('/exercises/search', [ExerciseController::class, 'search']);

    Route::get('/categories', [CategoryController::class, 'index']);
    Route::get('/categories/{id}', [CategoryController::class, 'show']);
    Route::post('/categories', [CategoryController::class, 'store']);
    Route::put('/categories/{id}', [CategoryController::class, 'update']);
    Route::delete('/categories/{id}', [CategoryController::class, 'destroy']);
    Route::get('/categories/{id}/exercises', [CategoryController::class, 'exercises']);

    Route::post('/workouts', [WorkoutController::class, 'store']);
    Route::get('/workouts/{id}', [WorkoutController::class, 'show']);
    Route::get('/workouts/{id}/display', [WorkoutController::class, 'display']); // sacar de sanctum
    Route::get('/workouts', [WorkoutController::class, 'index']);
    Route::put('/workouts/{id}', [WorkoutController::class, 'update']);
    Route::delete('/workouts/{id}', [WorkoutController::class, 'destroy']);
});
