<?php

// Configuración del proveedor de identidad (Authentik).

return [

    'issuer' => env('AUTHENTIK_ISSUER'),

    'client_id' => env('AUTHENTIK_CLIENT_ID'),

    'client_secret' => env('AUTHENTIK_CLIENT_SECRET'),

    'redirect_uri' => env('AUTHENTIK_REDIRECT_URI'),

    'scopes' => env('AUTHENTIK_SCOPES', 'openid profile email'),

    'algoritmo' => env('AUTHENTIK_ALGORITMO', 'RS256'),

    /*
    | Tolerancia de reloj, en minutos, al revisar la vigencia del token.
    |
    | Es una tolerancia técnica entre servidores, no un margen de seguridad. Con
    | dos minutos, un token expirado por más de dos minutos se rechaza igual.
    */
    'tolerancia_minutos' => (int) env('AUTHENTIK_TOLERANCIA_MINUTOS', 2),

];