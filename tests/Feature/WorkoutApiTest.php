<?php

namespace Tests\Feature;

use App\Models\Block;
use App\Models\BlockExercise;
use App\Models\Exercise;
use App\Models\User;
use App\Models\Workout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use RuntimeException;
use Tests\TestCase;

class WorkoutApiTest extends TestCase
{
    use RefreshDatabase;

    private function createExercise(string $name = 'Sentadilla'): Exercise
    {
        return Exercise::create([
            'name' => $name,
            'video_url' => 'https://example.test/video.mp4',
        ]);
    }

    private function createWorkout(User $user, string $name = 'Rutina'): Workout
    {
        return Workout::create([
            'name' => $name,
            'date' => '2026-01-01',
            'user_id' => $user->id,
        ]);
    }

    public function test_guest_cannot_access_workouts(): void
    {
        $this->getJson('/api/workouts')->assertUnauthorized();
    }

    public function test_user_only_lists_their_own_workouts(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $this->createWorkout($user, 'Mia');
        $this->createWorkout($other, 'Ajena');

        Sanctum::actingAs($user);

        $this->getJson('/api/workouts')
            ->assertOk()
            ->assertJsonFragment(['name' => 'Mia'])
            ->assertJsonMissing(['name' => 'Ajena']);
    }

    public function test_user_can_create_a_workout_with_blocks_and_exercises(): void
    {
        $user = User::factory()->create();
        $exercise = $this->createExercise();

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/workouts', [
            'name' => 'Pierna',
            'date' => '2026-01-10',
            'blocks' => [
                [
                    'name' => 'Bloque 1',
                    'order' => 1,
                    'exercises' => [
                        [
                            'exercise_id' => $exercise->id,
                            'type' => 'reps',
                            'reps' => '10',
                            'time' => null,
                            'intensity' => '8',
                            'order' => 1,
                        ],
                    ],
                ],
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('name', 'Pierna')
            ->assertJsonPath('blocks.0.name', 'Bloque 1')
            ->assertJsonPath('blocks.0.block_exercises.0.type', 'reps');

        $this->assertDatabaseHas('workouts', ['name' => 'Pierna', 'user_id' => $user->id]);
        $this->assertDatabaseHas('blocks', ['name' => 'Bloque 1']);
        $this->assertDatabaseHas('block_exercises', [
            'exercise_id' => $exercise->id,
            'type' => 'reps',
            'reps' => '10',
            'intensity' => '8',
        ]);
    }

    public function test_null_ids_for_new_blocks_and_exercises_are_accepted(): void
    {
        $user = User::factory()->create();
        $exercise = $this->createExercise();

        Sanctum::actingAs($user);

        // store(): un id nulo debe tratarse como "crear", no como error de validación.
        $create = $this->postJson('/api/workouts', [
            'name' => 'Con ids nulos',
            'date' => '2026-04-01',
            'blocks' => [
                [
                    'id' => null,
                    'name' => 'Bloque nuevo',
                    'order' => 1,
                    'exercises' => [
                        [
                            'id' => null,
                            'exercise_id' => $exercise->id,
                            'type' => 'reps',
                            'reps' => '8',
                            'order' => 1,
                        ],
                    ],
                ],
            ],
        ]);

        $create->assertCreated();

        $this->assertDatabaseHas('blocks', ['name' => 'Bloque nuevo']);
        $this->assertDatabaseHas('block_exercises', [
            'exercise_id' => $exercise->id,
            'type' => 'reps',
            'reps' => '8',
        ]);

        $workoutId = $create->json('id');

        // update(): un id nulo tampoco debe provocar un 422.
        $this->putJson("/api/workouts/{$workoutId}", [
            'name' => 'Con ids nulos',
            'date' => '2026-04-02',
            'blocks' => [
                [
                    'id' => null,
                    'name' => 'Bloque actualizado',
                    'order' => 1,
                    'exercises' => [
                        [
                            'id' => null,
                            'exercise_id' => $exercise->id,
                            'type' => 'reps',
                            'reps' => '12',
                            'order' => 1,
                        ],
                    ],
                ],
            ],
        ])->assertOk();

        $this->assertDatabaseHas('blocks', ['name' => 'Bloque actualizado']);
        $this->assertDatabaseHas('block_exercises', [
            'exercise_id' => $exercise->id,
            'type' => 'reps',
            'reps' => '12',
        ]);
    }

    public function test_user_can_update_their_own_workout(): void
    {
        $user = User::factory()->create();
        $workout = $this->createWorkout($user);

        Sanctum::actingAs($user);

        $this->putJson("/api/workouts/{$workout->id}", [
            'name' => 'Actualizada',
            'date' => '2026-02-02',
        ])
            ->assertOk()
            ->assertJsonPath('name', 'Actualizada');

        $this->assertDatabaseHas('workouts', ['id' => $workout->id, 'name' => 'Actualizada']);
    }

    public function test_user_can_delete_their_own_workout(): void
    {
        $user = User::factory()->create();
        $workout = $this->createWorkout($user);

        Sanctum::actingAs($user);

        $this->deleteJson("/api/workouts/{$workout->id}")->assertOk();

        $this->assertDatabaseMissing('workouts', ['id' => $workout->id]);
    }

    public function test_user_cannot_view_update_or_delete_another_users_workout(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $workout = $this->createWorkout($owner, 'Privada');

        Sanctum::actingAs($intruder);

        $this->getJson("/api/workouts/{$workout->id}")->assertNotFound();
        $this->putJson("/api/workouts/{$workout->id}", [
            'name' => 'Hackeada',
            'date' => '2026-03-03',
        ])->assertNotFound();
        $this->deleteJson("/api/workouts/{$workout->id}")->assertNotFound();

        $this->assertDatabaseHas('workouts', ['id' => $workout->id, 'name' => 'Privada']);
    }

    public function test_update_rejects_a_block_from_another_workout(): void
    {
        $user = User::factory()->create();
        $workout = $this->createWorkout($user);
        $otherWorkout = $this->createWorkout($user, 'Otra rutina');
        $foreignBlock = Block::create([
            'workout_id' => $otherWorkout->id,
            'name' => 'Ajena',
            'order' => 1,
        ]);

        Sanctum::actingAs($user);

        $this->putJson("/api/workouts/{$workout->id}", [
            'name' => 'Rutina',
            'date' => '2026-01-01',
            'blocks' => [
                ['id' => $foreignBlock->id, 'name' => 'Modificada', 'order' => 1],
            ],
        ])->assertNotFound();

        $this->assertDatabaseHas('blocks', ['id' => $foreignBlock->id, 'name' => 'Ajena']);
    }

    public function test_update_rejects_an_exercise_from_another_block(): void
    {
        $user = User::factory()->create();
        $workout = $this->createWorkout($user);
        $block = Block::create(['workout_id' => $workout->id, 'name' => 'Bloque', 'order' => 1]);
        $otherBlock = Block::create(['workout_id' => $workout->id, 'name' => 'Otro', 'order' => 2]);
        $foreignExercise = BlockExercise::create([
            'block_id' => $otherBlock->id,
            'exercise_id' => $this->createExercise()->id,
            'type' => 'reps',
            'reps' => '5',
            'order' => 1,
        ]);

        Sanctum::actingAs($user);

        $this->putJson("/api/workouts/{$workout->id}", [
            'name' => 'Rutina',
            'date' => '2026-01-01',
            'blocks' => [
                [
                    'id' => $block->id,
                    'name' => 'Bloque',
                    'order' => 1,
                    'exercises' => [
                        ['id' => $foreignExercise->id, 'exercise_id' => $foreignExercise->exercise_id, 'type' => 'reps', 'reps' => '99', 'order' => 1],
                    ],
                ],
                [
                    'id' => $otherBlock->id,
                    'name' => 'Otro',
                    'order' => 2,
                ],
            ],
        ])->assertNotFound();

        $this->assertDatabaseHas('block_exercises', ['id' => $foreignExercise->id, 'reps' => '5']);
    }

    public function test_store_rejects_invalid_nested_data(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        // Falta el nombre y el tipo no está permitido.
        $this->postJson('/api/workouts', [
            'name' => 'Rutina',
            'date' => '2026-01-01',
            'blocks' => [
                [
                    'order' => 1,
                    'exercises' => [
                        [
                            'exercise_id' => 99999,
                            'type' => 'cardio',
                            'order' => 1,
                        ],
                    ],
                ],
            ],
        ])->assertStatus(422)
            ->assertJsonValidationErrors([
                'blocks.0.name',
                'blocks.0.exercises.0.exercise_id',
                'blocks.0.exercises.0.type',
            ]);

        $this->assertDatabaseCount('workouts', 0);
    }

    public function test_store_rejects_invalid_top_level_data(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/workouts', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'date']);

        $this->assertDatabaseCount('workouts', 0);
    }

    public function test_display_endpoint_is_public_and_only_exposes_routine_data(): void
    {
        $owner = User::factory()->create([
            'name' => 'Propietario',
            'email' => 'owner@example.test',
        ]);
        $workout = $this->createWorkout($owner);
        $exercise = $this->createExercise();
        $block = Block::create(['workout_id' => $workout->id, 'name' => 'Bloque 1', 'order' => 1]);
        BlockExercise::create([
            'block_id' => $block->id,
            'exercise_id' => $exercise->id,
            'type' => 'reps',
            'reps' => '12',
            'intensity' => '7',
            'order' => 1,
        ]);

        // Sin autenticación.
        $response = $this->getJson("/api/workouts/{$workout->id}/display");

        $response->assertOk()
            ->assertJsonPath('name', 'Rutina')
            ->assertJsonPath('blocks.0.name', 'Bloque 1')
            ->assertJsonPath('blocks.0.exercises.0.name', 'Sentadilla')
            ->assertJsonPath('blocks.0.exercises.0.value', '12');

        $response->assertJsonMissingPath('user_id');
        $response->assertDontSee('owner@example.test');
    }

    public function test_display_returns_not_found_for_unknown_workout(): void
    {
        $this->getJson('/api/workouts/99999/display')->assertNotFound();
    }

    public function test_store_rolls_back_every_write_when_a_nested_write_fails(): void
    {
        $user = User::factory()->create();
        $exercise = $this->createExercise();

        Sanctum::actingAs($user);

        // Forzamos un fallo al crear el segundo bloque para comprobar que la
        // transacción revierte el entrenamiento y el primer bloque.
        Block::creating(function (Block $block): void {
            if ($block->name === '__force_failure__') {
                throw new RuntimeException('forced failure');
            }
        });

        $this->withoutExceptionHandling();

        try {
            $this->postJson('/api/workouts', [
                'name' => 'Con fallo',
                'date' => '2026-03-03',
                'blocks' => [
                    ['name' => 'Primero', 'order' => 1],
                    ['name' => '__force_failure__', 'order' => 2],
                ],
            ]);

            $this->fail('Se esperaba una excepción al crear el segundo bloque.');
        } catch (RuntimeException $exception) {
            $this->assertSame('forced failure', $exception->getMessage());
        }

        $this->assertDatabaseCount('workouts', 0);
        $this->assertDatabaseCount('blocks', 0);
    }
}
