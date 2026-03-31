<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('block_exercises', function (Blueprint $table) {
            $table->id();

            $table->foreignId('block_id')->constrained()->onDelete('cascade');
            $table->foreignId('exercise_id')->constrained()->onDelete('cascade');

            $table->string('type'); // reps o time
            $table->integer('reps')->nullable();
            $table->integer('time')->nullable();
            $table->integer('rest')->nullable();

            $table->integer('order');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('block_exercises');
    }
};
