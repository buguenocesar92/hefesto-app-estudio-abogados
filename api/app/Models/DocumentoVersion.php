<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ResultadoRecepcion;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Version de documento: cada copia que se recibio, sin excepcion.
 *
 * Esto es lo que hace auditable un expediente. Un documento que cambio tres
 * veces tiene tres filas aca, con la huella de cada contenido. Una version
 * anterior nunca se borra: queda `no_vigente`, pero se sigue descargando.
 *
 * `resultado_recepcion` documenta que decidio el sistema con cada copia, incluso
 * con las que no se guardaron. Es lo que responde "por que este documento no
 * quedo guardado?".
 *
 * @property int $version
 * @property string $huella_sha256
 * @property ResultadoRecepcion $resultado_recepcion
 * @property string $estado
 */
class DocumentoVersion extends ModeloDelEstudio
{
    protected $fillable = [
        'tenant_id',
        'documento_id',
        'version',
        'huella_sha256',
        'ruta_archivo',
        'tamano_bytes',
        'fecha_documento',
        'recibido_en',
        'origen',
        'registrado_por_usuario_id',
        'estado',
        'resultado_recepcion',
    ];

    protected $casts = [
        'version' => 'integer',
        'tamano_bytes' => 'integer',
        'fecha_documento' => 'date',
        'recibido_en' => 'datetime',
        'resultado_recepcion' => ResultadoRecepcion::class,
    ];

    /** @return BelongsTo<Documento, $this> */
    public function documento(): BelongsTo
    {
        return $this->belongsTo(Documento::class);
    }

    /** Si esta copia se conservo como parte del historial. */
    public function conservada(): bool
    {
        return $this->resultado_recepcion->seGuardoComoVersion();
    }

    public function rutaAbsoluta(): string
    {
        return rtrim((string) config('estudio.documentos_path'), '/') . '/' . ltrim($this->ruta_archivo, '/');
    }
}