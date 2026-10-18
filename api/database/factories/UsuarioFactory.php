<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\RolUsuario;
use App\Models\Estudio;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Usuario>
 */
class UsuarioFactory extends Factory
{
    protected $model = Usuario::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'subject' => 'authentik|' . $this->faker->unique()->uuid(),
            'correo' => $this->faker->unique()->safeEmail(),
            'nombre' => $this->faker->firstName() . ' ' . $this->faker->lastName(),
            'rol' => RolUsuario::Abogado->value,
        ];
    }

    public function de(Estudio $estudio): static
    {
        return $this->state(fn (): array => ['tenant_id' => $estudio->id]);
    }

    public function administrador(): static
    {
        return $this->state(fn (): array => ['rol' => RolUsuario::Administrador->value]);
    }

    public function socio(): static
    {
        return $this->state(fn (): array => ['rol' => RolUsuario::Socio->value]);
    }

    public function abogado(): static
    {
        return $this->state(fn (): array => ['rol' => RolUsuario::Abogado->value]);
    }

    public function pasante(): static
    {
        return $this->state(fn (): array => ['rol' => RolUsuario::Pasante->value]);
    }
}