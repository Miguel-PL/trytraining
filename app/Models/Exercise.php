<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Exercise extends Model
{
    protected $fillable = [
        'name',
        'video_url',
        'video_status',
        'video_storage_path',
        'video_processing_token',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'video_processing_token',
    ];

    public function categories()
    {
        return $this->belongsToMany(Category::class, 'exercise_category');
    }

    public function blockExercises()
    {
        return $this->hasMany(BlockExercise::class);
    }
}
