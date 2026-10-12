<?php

namespace Tests\Unit;

use App\Http\Resources\ExerciseResource;
use App\Models\Exercise;
use Illuminate\Http\Request;
use Tests\TestCase;

class ExerciseResourceTest extends TestCase
{
    public function test_it_defaults_video_status_to_ready(): void
    {
        $exercise = new Exercise(['name' => 'Sentadilla']);
        $exercise->video_status = null;

        $array = (new ExerciseResource($exercise))->toArray(Request::create('/'));

        $this->assertSame('ready', $array['video_status']);
        $this->assertSame('Sentadilla', $array['name']);
    }

    public function test_it_keeps_an_explicit_video_status(): void
    {
        $exercise = new Exercise(['name' => 'Peso muerto']);
        $exercise->video_status = 'processing';

        $array = (new ExerciseResource($exercise))->toArray(Request::create('/'));

        $this->assertSame('processing', $array['video_status']);
    }
}
