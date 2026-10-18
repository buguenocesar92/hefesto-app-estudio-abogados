<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Cliente;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Cliente>
 */
class ClienteFactory extends Factory
{
    protected $model = Cliente::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tipo' => 'persona_natural',
            'nombre' => $this->faker->firstName() . ' ' . $this->faker->lastName(),
            'documento_identidad' => $this->faker->unique()->numerify('########-#'),
            'correo' => $this->faker->unique()->safeEmail(),
            'telefono' => '+56 9 ' . $this->faker->numerify('#### ####'),
            'activo' => true,
        ];
    }

    /** Contraparte o tercero: la parte ajena al estudio. */
    public function externo(): static
    {
        return $this->state(fn (): array => [
            'correo' => null,
            'telefono' => null,
        ]);
    }
}