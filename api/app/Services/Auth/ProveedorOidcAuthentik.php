<?php

declare(strict_types=1);

namespace App\Services\Auth;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Datos del proveedor de identidad (Authentik) y estado del inicio de sesion.
 *
 * POR QUE EL ESTADO VA CIFRADO
 * ---------------------------
 * Sin cifrar, el estado es un texto opaco que se puede leer y copiar. Copiado,
 * sirve para reenviarle a otra persona este formulario de inicio de sesion y
 * entrar en su lugar. Cifrado con la APP_KEY, solo el servidor lo puede leer.
 *
 * Y es de UN SOLO USO: al verificarlo se borra de la cache. Un estado que se
 * puede reutilizar sirve para repetir el mismo ataque las veces que haga falta.
 */
class ProveedorOidcAuthentik
{
    /** Diez minutos alcanzan para que una persona termine de entrar. */
    private const VIGENCIA_ESTADO = 600;

    public function issuer(): string
    {
        return rtrim((string) config('authentik.issuer'), '/');
    }

    /** @return array<string, string> */
    public function configuracion(): array
    {
        return [
            'authorization_endpoint' => $this->issuer() . '/authorize/',
            'token_endpoint' => $this->issuer() . '/token/',
            'userinfo_endpoint' => $this->issuer() . '/userinfo/',
            'end_session_endpoint' => $this->issuer() . '/end-session/',
        ];
    }

    public function urlDeAutorizacion(string $slugEstudio): string
    {
        $estado = $this->crearEstado($slugEstudio);

        $parametros = [
            'client_id' => (string) config('authentik.client_id'),
            'response_type' => 'code',
            'scope' => (string) config('authentik.scopes'),
            'redirect_uri' => (string) config('authentik.redirect_uri'),
            'state' => $estado,
        ];

        return $this->configuracion()['authorization_endpoint']
            . '?' . http_build_query($parametros, '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * Valida el estado y devuelve el estudio al que pertenecia.
     *
     * @throws RuntimeException si el estado no existe, ya se uso o expiro.
     */
    public function verificarEstado(string $estado): string
    {
        $cifrado = Cache::pull('oidc:estado:' . $estado);

        if ($cifrado === null) {
            throw new RuntimeException('El estado del inicio de sesion no es valido o ya se uso.');
        }

        $texto = Crypt::decryptString($cifrado);

        $partes = explode('|', $texto, 2);

        if (count($partes) !== 2) {
            throw new RuntimeException('El estado del inicio de sesion tiene un formato inesperado.');
        }

        if ((int) $partes[1] < time() - self::VIGENCIA_ESTADO) {
            throw new RuntimeException('El estado del inicio de sesion expiro.');
        }

        return $partes[0];
    }

    private function crearEstado(string $slugEstudio): string
    {
        $estado = Str::random(64);

        $cifrado = Crypt::encryptString($slugEstudio . '|' . time());

        Cache::put('oidc:estado:' . $estado, $cifrado, self::VIGENCIA_ESTADO);

        return $estado;
    }
}