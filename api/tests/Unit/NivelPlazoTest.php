<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\NivelAlertaPlazo;
use App\Services\Plazos\NivelPlazo;
use Tests\TestCase;

/**
 * El nivel de alerta de un plazo, sin base de datos.
 *
 * -------------------------------------------------------------------
 * ESTO NO ES UN CÁLCULO DE PLAZOS LEGALES
 * -------------------------------------------------------------------
 *
 * Estas pruebas comprueban una COMPARACIÓN entre dos fechas. Entran una fecha
 * que escribió el abogado y la fecha de hoy. Sale un color.
 *
 * Lo que NO se comprueba en ningún archivo, y no hay forma de comprobar, es si
 * esa fecha es la correcta: eso depende de la norma aplicable y del cómputo que
 * hizo la persona, y no está en el sistema. Por eso el caso más importante de
 * esta suite es `test_sin_dias_configurados_no_dice_nada`.
 */
class NivelPlazoTest extends TestCase
{
    public function test_una_fecha_ya_pasada_es_vencida(): void
    {
        $nivel = NivelPlazo::evaluar('2026-10-10', '2026-10-17', 7, 2);

        $this->assertSame(NivelAlertaPlazo::Vencido, $nivel->nivel);
        $this->assertSame(-7, $nivel->diasParaVencer);
        $this->assertTrue($nivel->requiereAviso());
    }

    public function test_la_fecha_de_hoy_es_critica(): void
    {
        $nivel = NivelPlazo::evaluar('2026-10-17', '2026-10-17', 7, 2);

        $this->assertSame(NivelAlertaPlazo::Critico, $nivel->nivel);
        $this->assertSame(0, $nivel->diasParaVencer);
        $this->assertTrue($nivel->requiereAviso());
    }

    public function test_dentro_de_la_ventana_critica_es_critica(): void
    {
        $nivel = NivelPlazo::evaluar('2026-10-19', '2026-10-17', 7, 2);

        $this->assertSame(NivelAlertaPlazo::Critico, $nivel->nivel);
        $this->assertSame(2, $nivel->diasParaVencer);
    }

    public function test_dentro_de_la_ventana_de_aviso_es_proximo(): void
    {
        $nivel = NivelPlazo::evaluar('2026-10-22', '2026-10-17', 7, 2);

        $this->assertSame(NivelAlertaPlazo::Proximo, $nivel->nivel);
        $this->assertSame(5, $nivel->diasParaVencer);
        $this->assertFalse($nivel->requiereAviso());
    }

    public function test_fuera_de_la_ventana_es_en_tiempo(): void
    {
        $nivel = NivelPlazo::evaluar('2026-11-30', '2026-10-17', 7, 2);

        $this->assertSame(NivelAlertaPlazo::EnTiempo, $nivel->nivel);
        $this->assertFalse($nivel->requiereAviso());
    }

    /**
     * EL CASO MÁS IMPORTANTE DE TODA ESTA SUITE.
     *
     * Sin número configurado, la respuesta NO es "en tiempo". Es "sin alerta".
     *
     * Devolver "en tiempo" sería mentir: affirmaría que la fecha está lejana
     * cuando en realidad el sistema no tiene con qué compararla. Devolver
     * "vencido" sería peor: le diría al abogado que perdió un término por un
     * número que nadie eligió.
     */
    public function test_sin_dias_configurados_no_dice_nada(): void
    {
        $nivel = NivelPlazo::evaluar('2026-10-10', '2026-10-17', null, null);

        $this->assertSame(NivelAlertaPlazo::SinAlerta, $nivel->nivel);
        $this->assertFalse($nivel->marcado());
        $this->assertFalse($nivel->requiereAviso());
        $this->assertStringContainsString('no configuró', $nivel->resumen());
    }

    /**
     * Sin días configurados, la fecha NO se devuelve. Se muestra como un gris
     * que dice "sin alerta", y no como un color.
     */
    public function test_sin_alerta_el_color_es_gris(): void
    {
        $this->assertSame('gris', NivelAlertaPlazo::SinAlerta->color());
        $this->assertSame('rojo', NivelAlertaPlazo::Vencido->color());
    }

    /**
     * El umbral crítico es un dato opcional. Si el estudio no lo configuró, se
     * usa el nivel "próximo": es un dato menos, no un dato inventado.
     */
    public function test_sin_ventana_critica_se_usa_el_nivel_proximo(): void
    {
        $nivel = NivelPlazo::evaluar('2026-10-18', '2026-10-17', 7, null);

        $this->assertSame(NivelAlertaPlazo::Proximo, $nivel->nivel);
        $this->assertSame(1, $nivel->diasParaVencer);
    }

    public function test_el_calculo_de_dias_usa_calendario_y_no_zona_horaria(): void
    {
        // Cruza el cambio de horario de verano en Chile. Una comparación hecha
        // con la zona local puede devolver 23 horas en vez de un día, que es
        // exactamente la clase de error que este sistema no quiere tener.
        $dias = NivelPlazo::diasEntre('2026-09-06', '2026-09-07');

        $this->assertSame(1, $dias);
        $this->assertSame(-1, NivelPlazo::diasEntre('2026-09-07', '2026-09-06'));
        $this->assertSame(0, NivelPlazo::diasEntre('2026-09-07', '2026-09-07'));
    }

    public function test_la_diferencia_es_simetrica(): void
    {
        $this->assertSame(
            NivelPlazo::diasEntre('2026-01-01', '2026-03-15'),
            -NivelPlazo::diasEntre('2026-03-15', '2026-01-01'),
        );
    }

    public function test_el_resumen_no_usa_la_palabra_plazo_legal(): void
    {
        $nivel = NivelPlazo::evaluar('2026-10-10', '2026-10-17', 7, 2);

        $this->assertStringNotContainsString('legal', mb_strtolower($nivel->resumen()));
        $this->assertStringNotContainsString('término', mb_strtolower($nivel->resumen()));
        $this->assertStringContainsString('La fecha que se registró', $nivel->resumen());
    }
}