<?php

declare(strict_types=1);

namespace App\Services\Plazos;

use App\Enums\NivelAlertaPlazo;

/**
 * El nivel de alerta de UN plazo, en un momento dado.
 *
 * Es un objeto de sólo lectura. No escribe, no consulta la base, no sabe nada
 * del estudio. Por eso se puede probar entero sin base de datos: es una función
 * del tiempo y de dos números.
 *
 * ---------------------------------------------------------------
 * LO QUE ESTA CLASE NO HACE, Y POR QUÉ NO SE VA A HACER NUNCA
 * ---------------------------------------------------------------
 *
 * ESTE SISTEMA NO CALCULA PLAZOS LEGALES. En ningún archivo.
 *
 * Un estudio trabaja con plazos computados según normativa y códigos de
 * procedimiento, y esos textos cambian. Un programa que calcula un vencimiento
 * está affirmando una cosa jurídica con una certeza que no tiene: si la norma
 * cambió y el sistema no, el abogado pierde un término, y un término perdido no
 * se recupera.
 *
 * Por eso, en este sistema:
 *
 *  - La fecha de vencimiento la INGRESA el abogado. No hay ninguna función que
 *    la derive de una fecha inicial más una cantidad de días.
 *  - El texto de respaldo, que es la RAZÓN de esa fecha, es obligatorio y no se
 *    puede modificar después (garantizado por un disparador en la base).
 *  - `diasAlerta` NO es un plazo. Es un número operativo que el socio elige:
 *    cuántos días antes de la fecha que el abogado escribió, aparece un aviso.
 *    Es un parámetro de alarma interna, no una regla jurídica.
 *
 * LA COMPARACIÓN ES DE CALENDARIO, NO DE DERECHO
 * ----------------------------------------------
 * Esta clase resta dos fechas. Eso es todo. No sabe si el plazo corre en días
 * hábiles o corridos, si hay feriados de por medio, si la causa está
 *uspended la causa o si el plazo se prorroga: nada de eso está en el sistema, y
 * no se averiguará solo. Si hay que computar, lo computa el abogado y lo
 * escribe.
 *
 * POR QUÉ `sinAlerta` ES UNA RESPUESTA Y NO UN VALOR POR OMISIÓN
 * -------------------------------------------------------------
 * Si no hay número configurado, esta clase NO devuelve `enTiempo`, que sería
 * mentir. Devuelve `sinAlerta`: "no tengo con qué compararte, así que no te
 * digo nada". Un tablero con huecos se nota y se corrige. Un tablero con
 * colores falsos se usa, y se termina confiando en un número que salió de la
 * nada.
 */
final class NivelPlazo
{
    private function __construct(
        public readonly NivelAlertaPlazo $nivel,
        public readonly ?int $diasParaVencer,
        public readonly ?int $diasAlerta,
        public readonly ?int $diasAlertaCritica,
    ) {}

    /**
     * Compara la fecha de un plazo con la fecha de hoy.
     *
     * @param  string  $fechaVencimiento  Fecha en formato `AAAA-MM-DD`. La
     *                                      escribió el abogado.
     * @param  string  $hoy               Fecha de referencia, en el mismo
     *                                      formato. Se pasa explícitamente para
     *                                      que la prueba sea determinista y no
     *                                      dependa del reloj del sistema.
     * @param  int|null  $diasAlerta  Días de alarma del estudio. Si es null,
     *                                no se marca nada.
     * @param  int|null  $diasAlertaCritica  Umbral más cercano. Si es null,
     *                                        no se usa y el nivel crítico
     *                                        nunca aparece.
     */
    public static function evaluar(
        string $fechaVencimiento,
        string $hoy,
        ?int $diasAlerta = null,
        ?int $diasAlertaCritica = null,
    ): self {
        $diasParaVencer = self::diasEntre($hoy, $fechaVencimiento);

        // Sin número configurado, no hay comparación posible. Se dice
        // explícitamente, en vez de asumir cero.
        if ($diasAlerta === null) {
            return new self(NivelAlertaPlazo::SinAlerta, $diasParaVencer, null, $diasAlertaCritica);
        }

        // La fecha ya pasó.
        if ($diasParaVencer < 0) {
            return new self(NivelAlertaPlazo::Vencido, $diasParaVencer, $diasAlerta, $diasAlertaCritica);
        }

        // Vence hoy.
        if ($diasParaVencer === 0) {
            return new self(NivelAlertaPlazo::Critico, $diasParaVencer, $diasAlerta, $diasAlertaCritica);
        }

        // Dentro de la ventana crítica, si el estudio la configuró. Sin
        // ventana crítica configurada, se cae al nivel "próximo": es un dato
        // menos, no un dato inventado.
        if ($diasAlertaCritica !== null && $diasParaVencer <= $diasAlertaCritica) {
            return new self(NivelAlertaPlazo::Critico, $diasParaVencer, $diasAlerta, $diasAlertaCritica);
        }

        // Dentro de la ventana de aviso.
        if ($diasParaVencer <= $diasAlerta) {
            return new self(NivelAlertaPlazo::Proximo, $diasParaVencer, $diasAlerta, $diasAlertaCritica);
        }

        return new self(NivelAlertaPlazo::EnTiempo, $diasParaVencer, $diasAlerta, $diasAlertaCritica);
    }

    /**
     * Días calendario entre dos fechas `AAAA-MM-DD`.
     *
     * Positivo si la fecha de vencimiento está en el futuro, negativo si ya
     * pasó, cero si es hoy.
     *
     * Se construye con `DateTimeImmutable` en UTC y no con la zona del
     * estudio, a propósito: la diferencia entre dos fechas no depende del
     * horario de verano y usar la zona local introduce un día de error dos
     * veces al año, que es exactamente la clase de error que este sistema no
     * quiere tener.
     */
    public static function diasEntre(string $desde, string $hasta): int
    {
        $inicio = new \DateTimeImmutable($desde . ' 00:00:00', new \DateTimeZone('UTC'));
        $fin = new \DateTimeImmutable($hasta . ' 00:00:00', new \DateTimeZone('UTC'));

        // El intervalo se redondea hacia abajo con el operador de división de
        // enteros, y se ajusta con el signo de la duración real, para que un
        // intervalo negativo no se trunque a cero.
        $dias = (int) $inicio->diff($fin)->format('%r%a');

        return $dias;
    }

    public function marcado(): bool
    {
        return $this->nivel->marcado();
    }

    public function requiereAviso(): bool
    {
        return $this->nivel->requiereAviso();
    }

    /** Texto para la interfaz. Nunca dice "plazo legal". */
    public function resumen(): string
    {
        if ($this->nivel === NivelAlertaPlazo::SinAlerta) {
            return 'Este plazo no se marca porque el estudio no configuró días de aviso.';
        }

        if ($this->nivel === NivelAlertaPlazo::Vencido) {
            return sprintf('La fecha que se registró ya pasó (hace %d días).', abs($this->diasParaVencer ?? 0));
        }

        if ($this->diasParaVencer === 0) {
            return 'Vence hoy.';
        }

        return sprintf('Faltan %d días para la fecha registrada.', $this->diasParaVencer);
    }
}
