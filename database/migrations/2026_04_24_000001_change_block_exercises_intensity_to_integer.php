<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('block_exercises', function (Blueprint $table) {
            if (DB::getDriverName() === 'mysql') {
                $table->tinyInteger('intensity_tmp')->nullable()->after('time');
            } else {
                // PostgreSQL (and others) don't have TINYINT
                $table->smallInteger('intensity_tmp')->nullable()->after('time');
            }
        });

        DB::table('block_exercises')
            ->orderBy('id')
            ->chunkById(500, function ($rows) {
                foreach ($rows as $row) {
                    $parsed = $this->parseIntensityToOneTen($row->intensity);
                    DB::table('block_exercises')
                        ->where('id', $row->id)
                        ->update(['intensity_tmp' => $parsed]);
                }
            });

        Schema::table('block_exercises', function (Blueprint $table) {
            $table->dropColumn('intensity');
        });

        Schema::table('block_exercises', function (Blueprint $table) {
            $table->renameColumn('intensity_tmp', 'intensity');
        });

        $driver = DB::getDriverName();
        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE block_exercises MODIFY intensity TINYINT NOT NULL DEFAULT 5');
        } elseif ($driver === 'pgsql') {
            DB::statement('ALTER TABLE block_exercises ALTER COLUMN intensity SET DEFAULT 5');
            DB::statement('ALTER TABLE block_exercises ALTER COLUMN intensity SET NOT NULL');
        }
    }

    public function down(): void
    {
        Schema::table('block_exercises', function (Blueprint $table) {
            $table->string('intensity_tmp', 255)->nullable()->after('time');
        });

        DB::table('block_exercises')
            ->orderBy('id')
            ->chunkById(500, function ($rows) {
                foreach ($rows as $row) {
                    $n = is_numeric($row->intensity) ? (int) $row->intensity : 5;
                    $n = $this->clampOneTen($n);
                    DB::table('block_exercises')
                        ->where('id', $row->id)
                        ->update(['intensity_tmp' => "{$n}/10"]);
                }
            }, 'id', 'id');

        Schema::table('block_exercises', function (Blueprint $table) {
            $table->dropColumn('intensity');
        });

        Schema::table('block_exercises', function (Blueprint $table) {
            $table->renameColumn('intensity_tmp', 'intensity');
        });
    }

    private function parseIntensityToOneTen($raw): int
    {
        if ($raw === null) {
            return 5;
        }

        $s = trim((string) $raw);
        if ($s === '') {
            return 5;
        }

        // "x/y" -> round((x/y)*10)
        if (preg_match('/^\s*(\d+(?:\.\d+)?)\s*\/\s*(\d+(?:\.\d+)?)\s*$/', $s, $m)) {
            $x = (float) $m[1];
            $y = (float) $m[2];
            if ($y == 0.0) {
                return 5;
            }
            $value = (int) round(($x / $y) * 10);
            return $this->clampOneTen($value);
        }

        // "p%" -> round((p/100)*10)
        if (preg_match('/^\s*(\d+(?:\.\d+)?)\s*%\s*$/', $s, $m)) {
            $p = (float) $m[1];
            $value = (int) round(($p / 100) * 10);
            return $this->clampOneTen($value);
        }

        // numeric -> clamp 1..10
        if (is_numeric($s)) {
            $value = (int) round((float) $s);
            return $this->clampOneTen($value);
        }

        return 5;
    }

    private function clampOneTen(int $n): int
    {
        if ($n < 1) {
            return 1;
        }
        if ($n > 10) {
            return 10;
        }
        return $n;
    }
};

