<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BlockExercise extends Model
{
    protected $fillable = [
        'block_id',
        'exercise_id',
        'type',
        'reps',
        'time',
        'intensity',
        'order'
    ];

    protected $casts = [
        'intensity' => 'integer',
    ];

    public function block()
    {
        return $this->belongsTo(Block::class);
    }

    public function exercise()
    {
        return $this->belongsTo(Exercise::class);
    }
}
