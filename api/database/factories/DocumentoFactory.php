<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Documento;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Documento>
 */
class DocumentoFactory extends Factory
{
    protected $model = Documento::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'clave_natural' => 'DOC-' . $this->faker->unique()->bothify('??###'),
            'titulo' => $this->faker->sentence(3),
            // Huella de ejemplo. No es el resumen de ningún archivo real: en las
            // pruebas la calcula el receptor sobre el contenido de verdad.
            'huella_sha256' => hash('sha256', $this->faker->unique()->uuid()),
            'version_actual' => 1,
            'ruta_archivo' => 'prueba/' . $this->faker->uuid(),
            'estado' => 'vigente',
        ];
    }

    /** Documento con un contenido concreto, para las pruebas de colisión. */
    public function conContenido(string $contenido, int $version = 1): static
    {
        return $this->state(fn (): array => [
            'huella_sha256' => hash('sha256', $contenido),
            'version_actual' => $version,
        ]);
    }
}