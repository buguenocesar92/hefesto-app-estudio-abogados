<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Rol de una parte EN UNA CAUSA.
 *
 * El rol no es de la persona: es de la relación entre la persona y el
 * expediente. La misma persona puede ser `Demandado` en una causa y
 * `Demandante` en otra, y en el sistema son dos filas distintas en
 * `expediente_partes` sobre la MISMA fila de `clientes`.
 *
 * QUE NO HACE ESTE SISTEMA CON ESTOS ROLES
 * -----------------------------------------
 * No sabe qué implican en derecho. No valida que una demanda tenga un
 * demandado. No comprueba que los roles sean coherentes entre sí. Sólo los
 * registra, los muestra y permite filtrar por ellos.
 *
 * La lista es corta y es del estudio: si un despacho necesita "adversario
 * provisional" o "apoderado con faculties especiales", lo agrega en su
 * catálogo. El sistema no decide qué roles existen en un juicio chileno.
 */
enum RolParte: string
{
    case Demandante = 'demandante';
    case Demandado = 'demandado';
    case Tercero = 'tercero';
    case Testigo = 'testigo';
    case Apoderado = 'apoderado';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Demandante => 'Demandante',
            self::Demandado => 'Demandado',
            self::Tercero => 'Tercero',
            self::Testigo => 'Testigo',
            self::Apoderado => 'Apoderado',
        };
    }

    /**
     * Si es una parte AJENA al estudio. La contraparte y el tercero lo son.
     *
     * Sirve para el dato más sensible de todos: las partes externas son
     * terceros que no contrataron al estudio, y su presencia en un expediente
     * se maneja con más cuidado.
     */
    public function esContraparte(): bool
    {
        return $this === self::Demandado || $this === self::Tercero;
    }

    /** @return list<self> */
    public static function valores(): array
    {
        return self::cases();
    }
}