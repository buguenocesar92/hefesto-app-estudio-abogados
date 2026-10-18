<?php

// Configuración de la aplicación de estudios de abogados.

return [

    /*
    |--------------------------------------------------------------------------
    | CARPETA DE DOCUMENTOS
    |--------------------------------------------------------------------------
    |
    | Debe estar en un volumen con respaldo. Ver docs/08.
    |
    */

    'documentos_path' => env('DOCUMENTOS_PATH', base_path('storage/app/documentos')),

    /*
    |--------------------------------------------------------------------------
    | DISCO DE DOCUMENTOS
    |--------------------------------------------------------------------------
    |
    | Disco local, fuera de `public`: los documentos de un expediente no son
    | un recurso público. Se sirven desde el controlador DESPUÉS de autorizar,
    | comprobando que la ruta no se salga del volumen.
    |
    */

    'disco_documentos' => env('DOCUMENTOS_DISCO', 'documentos'),

    /*
    |--------------------------------------------------------------------------
    | AVISO DE DOCUMENTO DUPLICADO EN OTRO EXPEDIENTE
    |--------------------------------------------------------------------------
    |
    | Cuando está activo, el sistema avisa si un documento ya estaba archivado
    | en otra causa del estudio.
    |
    | ESTE NÚMERO NO ES UN PLAZO. No tiene relación con ninguna regla jurídica.
    |
    */

    'avisar_documento_duplicado' => env('ESTUDIO_AVISAR_DOCUMENTO_DUPLICADO', false),

    /*
    |--------------------------------------------------------------------------
    | HORA DEL VIGILANTE DE PLAZOS
    |--------------------------------------------------------------------------
    |
    | A qué hora corre el vigilante. No es un plazo: es la hora en que el
    | sistema repasa qué fechas ya pasaron.
    |
    */

    'vigilancia_plazos_cron' => env('ESTUDIO_VIGILANCIO_PLAZOS_CRON', '0 7 * * *'),

];