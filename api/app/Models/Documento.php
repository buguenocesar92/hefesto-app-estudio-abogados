<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EstadoDocumento;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Documento: la identidad estable de un documento dentro de un expediente.
 *
 * LAS TRES COLUMNAS QUE HACEN LA COLISION DETECTABLE
 * -------------------------------------------------
 *  - `clave_natural`: como identifica el estudio a este documento.
 *  - `huella_sha256`: el resumen criptografico del CONTENIDO de la version
 *    vigente. Es la prueba de que el contenido no cambio.
 *  - `version_actual`: que version se considera vigente.
 *
 * Con las tres se distingue, sin ambiguedad, entre "llego otra vez lo mismo",
 * "llego una version nueva" y "llego el mismo documento pero distinto". El
 * ultimo caso es la colision, y no se resuelve solo. Ver
 * `App\Services\Documentos\DetectorDeColision`.
 *
 * NUNCA SE SOBRESCRIBE NI SE BORRA
 * -------------------------------
 * Las copias que llegan son versiones de este documento, y cada una queda en
 * `documento_versiones`. Si una copia llega con contenido distinto y una
 * version que no corresponde, NO se guarda encima: se abre una incidencia.
 *
 * @property string $clave_natural
 * @property string $huella_sha256
 * @property int $version_actual
 * @property string $ruta_archivo
 * @property EstadoDocumento $estado
 */
class Documento extends ModeloDelEstudio
{
    protected $fillable = [
        'tenant_id',
        'expediente_id',
        'tipo_documento_id',
        'clave_natural',
        'titulo',
        'huella_sha256',
        'version_actual',
        'fecha_documento',
        'ruta_archivo',
        'estado',
    ];

    protected $casts = [
        'version_actual' => 'integer',
        'fecha_documento' => 'date',
        'estado' => EstadoDocumento::class,
    ];

    /** @return BelongsTo<Expediente, $this> */
    public function expediente(): BelongsTo
    {
        return $this->belongsTo(Expediente::class);
    }

    /** @return BelongsTo<TipoDocumento, $this> */
    public function tipo(): BelongsTo
    {
        return $this->belongsTo(TipoDocumento::class, 'tipo_documento_id');
    }

    /**
     * Todas las copias recibidas, sin excepción.
     *
     * @return HasMany<DocumentoVersion, $this>
     */
    public function versiones(): HasMany
    {
        return $this->hasMany(DocumentoVersion::class);
    }

    /** @return HasMany<IncidenciaDocumental, $this> */
    public function incidencias(): HasMany
    {
        return $this->hasMany(IncidenciaDocumental::class);
    }

    /** Ruta completa en disco, a partir de la ruta relativa guardada. */
    public function rutaAbsoluta(): string
    {
        return rtrim((string) config('estudio.documentos_path'), '/') . '/' . ltrim($this->ruta_archivo, '/');
    }

    /**
     * Nombre legible de la huella, para las pantallas.
     *
     * Los primeros doce caracteres alcanzan para que una persona distinga dos
     * huellas mirando, y no se expone el resumen entero.
     */
    public function huellaCorta(): string
    {
        return substr($this->huella_sha256, 0, 12);
    }
}