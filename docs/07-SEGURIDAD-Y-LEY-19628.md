# 07 - Seguridad y Ley 19.628

> **Advertencia: en el entorno donde se escribió esto no había Docker, ni PHP, ni
> Composer.** Nada de lo que está descrito acá se ejecutó. No hay revisión de
> seguridad externa ni pruebas de penetración. Ver
> [10](10-PRUEBAS-Y-ESTADO.md).

---

## 1. Qué clase de dato maneja esta aplicación

**Es el dato más sensible de todo el portafolio de Hefesto.**

En una panadería hay ventas y nombres de clientes. En un estudio de abogados hay
**nombres de personas en causas**: quién demandó a quién, por qué, con qué
documentos, cuánto se le cobra.

> **Un nombre dentro de un expediente basta para dañar a alguien.**

Y no solo hay datos de los clientes: también hay **contrapartes y terceros** que
no contrataron al despacho ywhose presencia en un expediente se maneja con más
cuidado todavía (`clientes.es_externo` y `expediente_partes.es_externo`).

Todo eso está bajo la **Ley 19.628 sobre protección de la vida privada**, y por eso
este módulo se aplica con **estrictez alta**.

---

## 2. Las medidas, en orden de fuerza

### 2.1 Aislamiento en la base de datos

Row Level Security de PostgreSQL sobre las quince tablas de negocio, con
`WITH CHECK`, `FORCE ROW LEVEL SECURITY` y el contexto viajando con
`set_config(..., true)`, de alcance de transacción.

**Es la última línea.** Un error en PHP no basta para filtrar datos.

Verificado antes de cada despliegue con:

```bash
php artisan estudio:verificar-aislamiento
```

Ver [03](03-MULTITENANCY-Y-ROLES.md).

### 2.2 Permisos mínimos dentro del estudio

| Recurso | Abogado | Socio | Administrador |
|---|---|---|---|
| Expedientes | solo su cartera | todos | todos |
| Clientes | los que tienen causa en su cartera | todos | todos |
| Cambiar estado | sí | sí | sí |
| Registrar plazos, documentos, horas | sí | sí | sí |
| Catálogos y usuarios | no | lectura | todo |

El filtro de cartera va **en la consulta**, no en la vista: filtrar después de
traer significa haber recibido los datos ajenos en memoria.

### 2.3 Auditoría de acceso a expedientes

**No es una función de auditoría: es la diferencia entre una pérdida de
confianza y un incidente con nombre y apellido.**

El middleware `AuditarAcceso` registra el acceso a **toda** ruta que muestra un
expediente, sin depender de que alguien se acuerde de auditarlo. Se registra:

| Columna | Para qué |
|---|---|
| `usuario_id` | Quién. |
| `recurso`, `recurso_id` | Qué. |
| `resumen` | Cuál, con folio y número de causa, para no tener que abrir el expediente. |
| `accion` | `consultar`, `crear`, `anular`, `registrar_plazo`, `recibir_documento`, `descargar`, `acceso_denegado`. |
| `direccion_ip`, `agente` | Desde dónde. |
| `ocurrido_en` | Cuándo. |

Y se registran **también los intentos denegados**, que son justamente el caso
interesante.

#### La bitácora solo admite filas nuevas

No hay ruta de actualización ni de borrado en toda la aplicación, y la
migración `2026_10_17_000019_bloquear_borrado_bitacora` deja la garantía **en la
base**, con un disparador que rechaza `UPDATE` y `DELETE`.

**Una auditoría que se puede editar no es una auditoría.**

### 2.4 Datos mínimos en la tabla de clientes

Se guarda: nombre, documento de identidad, correo, teléfono, notas.

**No se guarda:** dirección, teléfono de terceros, antecedentes económicos,
información familiar. Si el estudio necesita algo más, queda en el expediente
físico y se anota en el campo de notas.

### 2.5 Identificadores que no se pueden recorrer

Todos los UUID, en todas las tablas. Ninguna tabla de negocio usa enteros
auto-incrementales.

Con un `id` adivinable se puede recorrer el sistema probando
`/expedientes/41`, `/expedientes/42`... hasta dar con uno real.

---

## 3. Autenticación

### El proveedor de identidad no decide quién entra

Authentik sabe **quién** es la persona. **No sabe si puede entrar a este
estudio**: eso está en la tabla `usuarios`, que administra el estudio.

> **Si el `sub` del token no está dado de alta, la respuesta es 403 y no se crea
> ninguna cuenta.** Un estudio abierto al mundo no es un estudio.

### Qué se comprueba en el token

`VerificadorIdToken` comprueba, en este orden:

1. Que la **firma** sea válida contra las claves públicas del proveedor. Si no
   lo es, el token lo pudo fabricar cualquiera que se haga pasar por el
   proveedor.
2. Que el **estado** sea válido. El estado es de un solo uso y va cifrado con la
   `APP_KEY`, así que no se puede leer ni copiar desde el navegador.
3. Que el **emisor** sea el esperado.
4. Que la **audiencia** sea esta aplicación, para que un token emitido para otro
   cliente no sirva acá.
5. Que no esté **vencido**, con una tolerancia de dos minutos por desfase de
   reloj.

### La regla dura de esa clase

> **Si algo no se puede comprobar, lanza excepción. Nunca devuelve los datos
> "por si acaso".**

Un token sin firma comprobada es texto que escribe cualquiera. Devolver sus
afirmaciones sin verificarlas sería una puerta abierta con apariencia de puerta
cerrada.

El mismo criterio está en el layout de la base: el estado del plazo no se
calcula al mostrar, porque eso no se puede comprobar.

---

## 4. Lo que protege la aplicación y lo que no

### Protege

| | |
|---|---|
| Aislamiento entre estudios | Row Level Security en la base |
| Aislamiento dentro del estudio | Policies, con filtro en la consulta |
| Identidad | OIDC con firma verificada |
| Sesión | Cookie `httpOnly`, `secure` en producción, `sameSite=Lax` |
| Formularios | CSRF en el grupo `web` |
| Entrada | Validación con listas de valores permitidos |
| Integridad de la evidencia | Disparadores en la base para el respaldo de plazos y la bitácora |
| Rutas de archivo | Autorización antes de tocar el disco, y comparación con el volumen real |
| Cachés | `Cache-Control: no-store, private` en toda respuesta |
| Indexación | `<meta name="robots" content="noindex, nofollow, noarchive">` |

### NO protege

| | Por qué |
|---|---|
| El contenido de los documentos | El sistema organiza y registra. No sabe si un documento es auténtico ni si una firma es válida. |
| La ley | **No interpreta la ley.** No dice si una demanda prospera ni si un plazo se cumplió. |
| El cumplimiento de un plazo | Un "vencido" significa *"la fecha que escribiste ya pasó"*, y nada más. |
| Errores de tipeo del abogado | Si escribe mal una fecha, el sistema la vigila tal como está. No sabe si es la correcta. |
| La disponibilidad | No hay réplicas ni respaldo en caliente. Ver [08](08-RESPALDOS.md). |

---

## 5. CSRF

Todas las rutas viven en el grupo `web`, que aplica CSRF. La interfaz Nuxt manda
el token en la cabecera `X-CSRF-TOKEN`, que es lo que espera Laravel.

**El tráfico no pasa por el navegador directamente a la API:** todo va por el
proxy del servidor de la interfaz
(`web/server/api/[...ruta].ts`), que reenvía la cookie de sesión. Dos
consecuencias:

1. La cookie de sesión nunca queda expuesta al JavaScript del navegador.
2. La API nunca es accesible directamente, así que no se puede saltar el proxy.

---

## 6. CORS

**No hay configuración de CORS en la API**, y esa es la decisión: sin
`Access-Control-Allow-Origin`, el navegador de otro dominio no puede hablar
directo con la API. Todo el tráfico legítimo pasa por el proxy del mismo origen.

---

## 7. Descarga de documentos

El orden de las operaciones importa, y es deliberado:

1. Se resuelve el **documento desde la base**, donde la RLS ya filtró y donde la
   Policy autorizó.
2. Se comprueba que la ruta no se salga del volumen:

```php
$base     = realpath(config('estudio.documentos_path'));
$rutaReal = realpath($ruta);

abort_if(
    $rutaReal === false || ! str_starts_with($rutaReal, $base),
    404,
);
```

**Si `realpath` devuelve `false`** —porque la base no existe, o la ruta no está—
la comprobación aborta. Es lo correcto: es mejor fallar ahí que servir un
archivo de cualquier parte del disco.

En `nginx.conf`, además, todo `storage/` está denegado. El único camino para
llegar a un documento es la API.

---

## 8. Lo que falta, dicho con todas las letras

- **No hay revisión de seguridad externa.**
- **No hay pruebas de penetración.**
- **No hay análisis de dependencias automatizado.**
- **Las pruebas de aislamiento están escritas pero no se ejecutaron**: en el
  entorno donde se escribió esto no había PostgreSQL. Ver
  [10](10-PRUEBAS-Y-ESTADO.md).
- **No hay gestión de incidentes**: qué hacer si se filtra un expediente.
- **No hay cifrado en reposo del volumen de documentos.** Si alguien accede al
  disco, lee los documentos.
- **No hay retención configurada.** No hay política de cuánto tiempo se guarda un
  expediente archivado, y la Ley 19.628 pide que no se conserven datos para
  siempre.

**Nada de esto está resuelto, y es la razón por la que el README dice que esto
no está listo para un estudio real.**

---

## 9. Checklist antes de recibir datos reales

- [ ] `php artisan estudio:verificar-aislamiento` sale con código 0.
- [ ] El rol de la base **no** es superusuario ni tiene `BYPASSRLS`.
- [ ] HTTPS funcionando, y la cookie de sesión con `secure`.
- [ ] `Cache-Control: no-store` en las respuestas.
- [ ] Bitácora de accesos funcionando, y el disparador de solo agregar activo.
- [ ] Disparador del texto de respaldo activo.
- [ ] Volumen de documentos **con respaldo** funcionando.
- [ ] Copia de seguridad probada y **restaurada** al menos una vez.
- [ ] Política de retención de datos acordada con el estudio.
- [ ] Revisión de seguridad externa hecha.
- [ ] Una sesión de uso real, con un estudio piloto, antes de abrir al resto.

---

Siguiente: [08 Respaldos](08-RESPALDOS.md).
