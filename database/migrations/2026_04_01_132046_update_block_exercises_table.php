<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('block_exercises', function (Blueprint $table) {
            $table->dropColumn('rest');
            $table->string('intensity')->nullable();
        });
    }

    public function down()
    {
        Schema::table('block_exercises', function (Blueprint $table) {
            $table->integer('rest')->nullable();
            $table->dropColumn('intensity');
        });
    }
};
