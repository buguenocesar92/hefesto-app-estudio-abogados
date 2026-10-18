<?php

declare(strict_types=1);

namespace App\Services\Auth;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Verifica el token de identificacion que entrega Authentik.
 *
 * QUE SE COMPRUEBA
 * ----------------
 * 1. Que la FIRMA sea valida, contra las claves publicas del proveedor. Si la
 *    firma no es valida, el token lo pudo fabricar cualquiera que se haga pasar
 *    por el proveedor.
 * 2. Que el EMISOR sea el esperado, para que un token de otro proveedor no sea
 *    aceptado.
 * 3. Que la AUDIENCIA sea esta aplicacion, para que un token emitido para otro
 *    cliente no sirva aca.
 * 4. Que no este VENCIDO, con una tolerancia pequena por desfase de reloj.
 *
 * LO QUE NO SE COMPRUEBA
 * ----------------------
 * Que la persona pueda o no pueda entrar al estudio. Eso lo decide el rol
 * guardado en la base, no el proveedor de identidad. Ver `AuthController`.
 *
 * REGLA DURA DE ESTA CLASE
 * ------------------------
 * Si algo no se puede comprobar, LANZA EXCEPCION. Nunca devuelve los datos
 * "por si acaso". Un token sin firma comprobada es texto que escribe cualquiera:
 * devolver sus afirmaciones sin verificarlas seria una puerta abierta con
 * aspecto de puerta cerrada.
 */
class VerificadorIdToken
{
    public function __construct(private readonly ProveedorOidcAuthentik $proveedor) {}

    /**
     * @return array{sub: string, email: ?string, name: ?string, slug_estudio: string}
     *
     * @throws RuntimeException si algo no se puede comprobar.
     */
    public function verificar(?string $estado, ?string $codigo): array
    {
        if ($estado === null || $codigo === null) {
            throw new RuntimeException('Faltan parametros en el callback del proveedor de identidad.');
        }

        // El estado se verifica PRIMERO: si el estado no es valido, no se pide
        // nada al proveedor. Además es la unica fuente fidedigna del estudio:
        // viene del estado cifrado que esta clase misma creo, no del navegador.
        $slugEstudio = $this->proveedor->verificarEstado($estado);

        $token = $this->pedirToken($codigo);

        $afirmaciones = $this->verificarFirma($token);

        $this->revisarEmisor($afirmaciones);
        $this->revisarAudiencia($afirmaciones);
        $this->revisarVencimiento($afirmaciones);

        return [
            'sub' => (string) ($afirmaciones['sub'] ?? ''),
            'email' => isset($afirmaciones['email']) ? (string) $afirmaciones['email'] : null,
            'name' => isset($afirmaciones['name']) ? (string) $afirmaciones['name'] : null,
            'slug_estudio' => $slugEstudio,
        ];
    }

    /** @return array<string, mixed> */
    private function pedirToken(string $codigo): array
    {
        $respuesta = Http::asForm()->timeout(10)->post(
            $this->proveedor->configuracion()['token_endpoint'],
            [
                'grant_type' => 'authorization_code',
                'client_id' => (string) config('authentik.client_id'),
                'client_secret' => (string) config('authentik.client_secret'),
                'redirect_uri' => (string) config('authentik.redirect_uri'),
                'code' => $codigo,
            ],
        );

        if (! $respuesta->successful() || ! isset($respuesta->json()['id_token'])) {
            throw new RuntimeException('El proveedor de identidad no devolvio un token de identificacion.');
        }

        return $respuesta->json();
    }

    /**
     * @param  array<string, mixed>  $respuesta
     * @return array<string, mixed>
     */
    private function verificarFirma(array $respuesta): array
    {
        $afirmaciones = (new \Firebase\JWT\JWT())->decode(
            (string) $respuesta['id_token'],
            $this->clavePublica(),
        );

        return (array) $afirmaciones;
    }

    /**
     * Trae el juego de claves publicas del proveedor y se lo entrega a
     * firebase/php-jwt, que escoge la correcta segun el `kid` del token.
     */
    private function clavePublica(): \Firebase\JWT\Key
    {
        $emisor = $this->proveedor->issuer();

        $respuesta = Http::timeout(10)->get($emisor . '/application/j/' . config('authentik.client_id') . '/jwks/');

        if (! $respuesta->successful()) {
            throw new RuntimeException('No se pudieron obtener las claves publicas del proveedor de identidad.');
        }

        return \Firebase\JWT\JWK::parseKeySet(
            $respuesta->json(),
            (string) config('authentik.algoritmo'),
        );
    }

    /** @param array<string, mixed> $afirmaciones */
    private function revisarEmisor(array $afirmaciones): void
    {
        $esperado = $this->proveedor->issuer();

        if (rtrim((string) ($afirmaciones['iss'] ?? ''), '/') !== $esperado) {
            throw new RuntimeException('El token de identificacion viene de otro emisor.');
        }
    }

    /** @param array<string, mixed> $afirmaciones */
    private function revisarAudiencia(array $afirmaciones): void
    {
        $esperado = (string) config('authentik.client_id');
        $audiencia = $afirmaciones['aud'] ?? null;

        $coincide = is_array($audiencia)
            ? in_array($esperado, $audiencia, true)
            : $audiencia === $esperado;

        if (! $coincide) {
            throw new RuntimeException('El token de identificacion no es para esta aplicacion.');
        }
    }

    /** @param array<string, mixed> $afirmaciones */
    private function revisarVencimiento(array $afirmaciones): void
    {
        $tolerancia = (int) config('authentik.tolerancia_minutos', 2) * 60;
        $expira = (int) ($afirmaciones['exp'] ?? 0);

        if ($expira === 0 || $expira < (time() - $tolerancia)) {
            throw new RuntimeException('El token de identificacion esta vencido.');
        }
    }
}