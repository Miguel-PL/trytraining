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
            $table->string('reps', 255)->nullable()->change();
            $table->string('time', 255)->nullable()->change();
            $table->string('intensity', 255)->nullable()->change();
        });
    }

    public function down(): void
    {
        DB::table('block_exercises')
            ->orderBy('id')
            ->chunkById(500, function ($rows) {
                foreach ($rows as $row) {
                    DB::table('block_exercises')
                        ->where('id', $row->id)
                        ->update([
                            'reps' => $this->nullableIntFromString($row->reps),
                            'time' => $this->nullableIntFromString($row->time),
                            'intensity' => $this->intensityStringToOneTen($row->intensity),
                        ]);
                }
            });

        Schema::table('block_exercises', function (Blueprint $table) {
            $table->integer('reps')->nullable()->change();
            $table->integer('time')->nullable()->change();
            if (DB::getDriverName() === 'mysql') {
                $table->tinyInteger('intensity')->default(5)->change();
            } else {
                $table->smallInteger('intensity')->default(5)->change();
            }
        });

        $driver = DB::getDriverName();
        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE block_exercises MODIFY intensity TINYINT NOT NULL DEFAULT 5');
        } elseif ($driver === 'pgsql') {
            DB::statement('ALTER TABLE block_exercises ALTER COLUMN intensity SET DEFAULT 5');
            DB::statement('ALTER TABLE block_exercises ALTER COLUMN intensity SET NOT NULL');
        }
    }

    private function nullableIntFromString(mixed $raw): ?int
    {
        if ($raw === null) {
            return null;
        }

        $s = trim((string) $raw);
        if ($s === '' || ! is_numeric($s)) {
            return null;
        }

        return (int) $s;
    }

    private function intensityStringToOneTen(mixed $raw): int
    {
        if ($raw === null) {
            return 5;
        }

        $s = trim((string) $raw);
        if ($s === '') {
            return 5;
        }

        if (preg_match('/^\s*(\d+(?:\.\d+)?)\s*\/\s*(\d+(?:\.\d+)?)\s*$/', $s, $m)) {
            $x = (float) $m[1];
            $y = (float) $m[2];
            if ($y == 0.0) {
                return 5;
            }

            return $this->clampOneTen((int) round(($x / $y) * 10));
        }

        if (preg_match('/^\s*(\d+(?:\.\d+)?)\s*%\s*$/', $s, $m)) {
            $p = (float) $m[1];

            return $this->clampOneTen((int) round(($p / 100) * 10));
        }

        if (is_numeric($s)) {
            return $this->clampOneTen((int) round((float) $s));
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
