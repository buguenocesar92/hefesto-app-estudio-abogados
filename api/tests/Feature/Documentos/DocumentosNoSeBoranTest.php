<?php

declare(strict_types=1);

namespace Tests\Feature\Documentos;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * NO HAY RUTAS DE BORRADO, Y ESTAS PRUEBAS LO VIGILAN.
 *
 * -------------------------------------------------------------------
 * POR QUÉ UNA AUSENCIA NECESITA UNA PRUEBA
 * -------------------------------------------------------------------
 *
 * Una prueba que comprueba que algo NO existe es la única forma de revisar
 * esa cosa siga sin existir. En un sistema donde se acumulan funciones y rutas,
 * el día que alguien agrega un `destroy()` para "limpieza", esta prueba se pone
 * roja. Sin ella, nadie se enteraría hasta que un documento desapareciera de
 * verdad.
 *
 * UN EXPEDIENTE SIN SU HISTORIAL DOCUMENTAL NO VALE NADA
 * ------------------------------------------------------
 * En un estudio, la pregunta "¿qué versión del contrato tenía en marzo?" tiene
 * que tener respuesta. Y la pregunta "¿por qué este documento ya no está?",
 * también: se responde con una anotación de anulación, no con un hueco.
 *
 * Lo que se comprueba:
 *  - No hay ruta DELETE para expedientes.
 *  - No hay ruta DELETE para documentos ni para sus versiones.
 *  - No hay ruta DELETE para plazos.
 *  - No hay ruta para cambiar el texto de respaldo ni la fecha de un plazo.
 */
class DocumentosNoSeBoranTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    private function rutasDeBorrado(): array
    {
        return collect(Route::getRoutes()->getRoutes())
            ->filter(function ($ruta): bool {
                return in_array('DELETE', $ruta->methods(), true);
            })
            ->map(fn ($ruta): string => $ruta->uri())
            ->values()
            ->all();
    }

    public function test_no_existe_ninguna_ruta_delete_en_toda_la_api(): void
    {
        $borrados = $this->rutasDeBorrado();

        $this->assertSame(
            [],
            $borrados,
            'Esta aplicación no tiene rutas DELETE. Apareció una: ' . implode(', ', $borrados),
        );
    }

    public function test_no_existe_ningun_metodo_destroy_en_los_controladores(): void
    {
        $controladores = glob(app_path('Http/Controllers/Api/*.php'));

        $encontrados = [];

        foreach ($controladores as $archivo) {
            $contenido = file_get_contents($archivo);

            if (preg_match('/function\s+destroy\s*\(/', $contenido) === 1) {
                $encontrados[] = basename($archivo);
            }
        }

        $this->assertSame(
            [],
            $encontrados,
            'Apareció un método destroy() en: ' . implode(', ', $encontrados)
            . '. Para dejar de usar un registro, se anula; no se borra.',
        );
    }

    public function test_no_existe_ruta_para_modificar_el_respaldo_de_un_plazo(): void
    {
        $uris = collect(Route::getRoutes()->getRoutes())
            ->map(fn ($ruta): string => $ruta->uri())
            ->values()
            ->all();

        $prohibidas = array_filter($uris, function (string $uri): bool {
            return str_contains($uri, 'respaldo') || str_contains($uri, 'texto_respaldo');
        });

        $this->assertSame(
            [],
            array_values($prohibidas),
            'El texto de respaldo de un plazo no se modifica por la API. La garantía está en un disparador de la base.',
        );
    }
}
