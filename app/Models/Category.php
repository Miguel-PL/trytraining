<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    protected $fillable = ['name'];

    public function exercises()
    {
        return $this->belongsToMany(Exercise::class, 'exercise_category');
    }
}
