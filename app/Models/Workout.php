<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Workout extends Model
{
    protected $fillable = ['name', 'date', 'user_id'];
    
    public function blocks()
    {
        return $this->hasMany(Block::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
