<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Estudio;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Entrada de la semilla.
 *
 * IMPORTANTE
 * -----------
 * `estudios` NO tiene Row Level Security, a propósito: se consulta antes del
 * inicio de sesión. Por eso la semilla puede escribir en ella directamente.
 *
 * Para todo lo demás hay que fijar el contexto con `set_config`, porque la RLS
 * exige un estudio. Ver `EstudioDemoSeeder`.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            EstudioDemoSeeder::class,
        ]);
    }
}