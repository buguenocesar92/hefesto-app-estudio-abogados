<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\TipoDocumento;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TipoDocumento>
 */
class TipoDocumentoFactory extends Factory
{
    protected $model = TipoDocumento::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $nombre = $this->faker->unique()->randomElement([
            'Contrato',
            'Poder',
            'Sentencia',
            'Carta de solicitud',
            'Instrumento',
        ]);

        return [
            'codigo' => strtoupper(str_replace(' ', '-', $nombre)) . '-' . $this->faker->unique()->numberBetween(1, 999),
            'nombre' => $nombre,
            'exige_version' => true,
            'activo' => true,
        ];
    }
}