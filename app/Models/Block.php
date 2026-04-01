<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Block extends Model
{
    protected $fillable = ['workout_id', 'name', 'order'];

    public function workout()
    {
        return $this->belongsTo(Workout::class);
    }

    public function blockExercises()
    {
        return $this->hasMany(BlockExercise::class)->orderBy('order');
    }
}
