<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Rol de una persona dentro del estudio.
 *
 * El rol vive en la base, no en Authentik: el proveedor de identidad sabe
 * quién es la persona, no qué puede hacer en este despacho.
 *
 * SOBRE EL ROL `ABOGADO`
 * ----------------------
 * Un abogado NO ve todos los expedientes de su estudio. Ve su CARTERA: los
 * expedientes de los que es responsable. Un socio ve todos. Esta es la
 * diferencia entre un sistema donde se trabaja tranquilo y uno donde hay que
 * preguntarse quién está mirando qué.
 */
enum RolUsuario: string
{
    /** Ve todo el estudio y administra usuarios, materias y tipos. */
    case Administrador = 'administrador';

    /** Ve todos los expedientes del estudio. No administra usuarios. */
    case Socio = 'socio';

    /** Ve solo los expedientes de los que es responsable. */
    case Abogado = 'abogado';

    /** Ve solo los expedientes de los que es responsable, y no cambia estados. */
    case Pasante = 'pasante';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Administrador => 'Administrador',
            self::Socio => 'Socio',
            self::Abogado => 'Abogado',
            self::Pasante => 'Pasante',
        };
    }

    /** Si puede ver los expedientes de otro abogado del mismo estudio. */
    public function veCarteraCompleta(): bool
    {
        return $this === self::Administrador || $this === self::Socio;
    }

    /** Si puede cambiar el estado de un expediente. */
    public function cambiaEstadoDeExpediente(): bool
    {
        return $this === self::Administrador || $this === self::Socio || $this === self::Abogado;
    }

    /** Si puede administrar usuarios, materias, tipos de documento y estados. */
    public function administraEstudio(): bool
    {
        return $this === self::Administrador;
    }

    /** @return list<self> */
    public static function valores(): array
    {
        return self::cases();
    }
}