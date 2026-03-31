<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Exercise extends Model
{
    protected $fillable = ['name', 'video_url'];

    public function categories()
    {
        return $this->belongsToMany(Category::class, 'exercise_category');
    }

    public function blockExercises()
    {
        return $this->hasMany(BlockExercise::class);
    }
}
