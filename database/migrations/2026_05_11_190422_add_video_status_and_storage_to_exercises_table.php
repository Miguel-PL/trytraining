<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('exercises', function (Blueprint $table) {
            $table->string('video_status', 30)->default('ready');
            $table->string('video_storage_path', 255)->nullable();
            $table->string('video_url', 255)->nullable()->change();
        });

        // Para registros existentes, asumimos que el video_url ya era un recurso listo.
        DB::table('exercises')->whereNull('video_status')->update(['video_status' => 'ready']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('exercises', function (Blueprint $table) {
            $table->dropColumn('video_storage_path');
            $table->dropColumn('video_status');

            $table->string('video_url', 255)->nullable(false)->change();
        });
    }
};
