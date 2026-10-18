<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Documento;
use App\Models\Usuario;

/**
 * Quién puede tocar un documento.
 *
 * Un documento hereda los permisos de su expediente. Si no se puede ver la
 * causa, no se puede ver lo que hay en ella.
 *
 * NUNCA SE BORRA
 * --------------
 * `eliminar` devuelve siempre false y el controlador no expone ruta de borrado.
 * Para dejar de usar un documento mal incorporado se ANULA, con su motivo.
 *
 * Ver `api/tests/Feature/Documentos/DocumentosNoSeBoranTest.php`, que vigila que
 * esas rutas no aparezcan.
 */
class DocumentoPolicy
{
    public function viewAny(Usuario $usuario): bool
    {
        return true;
    }

    public function view(Usuario $usuario, Documento $documento): bool
    {
        return $this->veElDocumento($usuario, $documento);
    }

    /** Asociar una copia nueva al expediente. */
    public function registrar(Usuario $usuario, Documento $documento): bool
    {
        return $this->veElDocumento($usuario, $documento);
    }

    public function actualizar(Usuario $usuario, Documento $documento): bool
    {
        return $this->veElDocumento($usuario, $documento);
    }

    public function eliminar(Usuario $usuario, Documento $documento): bool
    {
        return false;
    }

    private function veElDocumento(Usuario $usuario, Documento $documento): bool
    {
        if ($documento->tenant_id !== $usuario->tenant_id) {
            return false;
        }

        return $usuario->can('view', $documento->expediente);
    }
}