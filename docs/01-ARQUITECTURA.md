# 01 - Arquitectura

> PostgreSQL corre con dos roles: uno de arranque y otro de aplicación. Solo el
> de aplicación conecta con el sistema, y es el que las políticas de
> aislamiento pueden alcanzar. Ver [03](03-MULTITENANCY-Y-ROLES.md).

Este documento explica las piezas de la aplicación y, sobre todo, **por qué el
sistema no calcula plazos legales**.

---

## 1. La advertencia que define el proyecto

Un estudio de abogados trabaja con plazos computados según normativa y códigos
de procedimiento, y esos textos cambian. Si el sistema inventa un plazo, el
abogado pierde un término, y un término perdido no se recupera.

Por eso, con estas palabras exactas:

> **Este sistema no interpreta la ley y no calcula plazos legales.**
>
> El registro de plazos es **una fecha y un texto de respaldo** que el abogado
> ingresa, y que el sistema **vigila**, no que calcula.
>
> **El sistema vigila y avisa; no decide.**

No es una limitación de la versión ni una funcionalidad pendiente. Es una
decisión de diseño, y hay tres cosas que la sostienen:

1. **La fecha la escribe el abogado.** No hay ninguna función en toda la
   aplicación que derive un vencimiento de otra fecha ni que multiplique una
   cantidad de días.
2. **El texto de respaldo es obligatorio e inmutable.** La razón por la que se
   escribió esa fecha importa más que la fecha misma, y hay un disparador en la
   base de datos que impide cambiarlo.
3. **Sin número configurado, no se marca nada.** El sistema no tiene un valor
   por omisión para "días de aviso": si el estudio no lo eligió, el plazo se
   muestra en gris diciendo que no hay alerta configurada.

La tercera es la más importante en la práctica, y la razón está en
[`NivelPlazo`](../../api/app/Services/Plazos/NivelPlazo.php): un "rojo" con un
número inventado le diría al abogado que un plazo se venció cuando el sistema
no tiene idea. **Un tablero con huecos se nota y se corrige. Un tablero con
colores falsos se usa.**

---

## 2. El producto: asociar documentos y avisar cuando algo no encaja

Lo que se pierde en un estudio no es el expediente: es el plazo. Y lo que más
cuesta es la **recepción de documentos nuevos**.

El expediente vive en un archivador físico. Llega un documento. Hay que
asociarlo al expediente correcto, y lo que casi nunca se detecta es que ese
documento **ya estaba**, o que llegó con una **versión distinta** de la que se
tiene. Cuando eso pasa y nadie lo nota, el estudio sigue trabajando sobre un
documento que ya no es el vigente.

Ese es el producto, y son dos detecciones:

| Detección | Qué compara | Qué hace |
|---|---|---|
| **Colisión de versión** | La huella del contenido y la versión, contra la versión vigente | **No sobrescribe.** Abre una incidencia de severidad alta y avisa. |
| **Documento en otra causa** | La huella del contenido contra las demás causas del estudio | Avisa que el mismo contenido ya estaba archivado en otro expediente. |

Ambas se resuelven con el mismo mecanismo: tres datos que están en la base.
Ver la sección 5.

---

## 3. Las piezas

```
                    ┌──────────────┐
                    │  Navegador   │  (persona del estudio)
                    └──────┬───────┘
                           │ HTTPS
                    ┌──────▼───────┐
                    │  Caddy       │  TLS, cabeceras de seguridad
                    └──────┬───────┘
                    ┌──────▼───────┐
                    │  Nuxt (SSR)  │  interfaz, proxy hacia la API
                    └──────┬───────┘
                           │ red interna
              ┌────────────▼─────────────┐
              │  API (Laravel)           │
              │                          │
              │  Middleware              │
              │   ResolverTenant ────┐   │
              │   AuditarAcceso       │   │
              │                      ▼   │
              │  Policies            ContextoTenant
              │   ExpedientePolicy  (set_config alcance
              │   DocumentoPolicy     de transacción)
              │   PlazoPolicy                │
              │   ClientePolicy              │
              │   TareaPolicy                │
              │                            ▼
              │  Servicios                PostgreSQL
              │   DetectorDeColision    ┌──────────────┐
              │   ReceptorDeDocumento   │  Row Level   │
              │   VigilanteDePlazos     │   Security   │
              │   NivelPlazo            └──────────────┘
              └────────────┬─────────────┘
                           │
              ┌────────────▼─────────────┐
              │  Redis: caché y colas    │
              └──────────────────────────┘

              ┌──────────────────────────┐
              │  Authentik (OIDC)       │  identidad
              └──────────────────────────┘
```

### Por qué el SSR está activado

No es una decisión estética. El nombre de un cliente y el título de una causa son
datos personales protegidos por la Ley 19.628. Con renderizado del lado del
servidor, el HTML que sale del servidor ya viene con la sesión aplicada: no
queda una pantalla vacía que se rellene después en la máquina de la persona.

### Por qué todo pasa por el proxy del servidor de la interfaz

`web/server/api/[...ruta].ts` reenvía el tráfico del navegador a la API. Dos
motivos:

1. La cookie de sesión nunca queda expuesta al JavaScript del navegador.
2. La API nunca es accesible directamente, así que no se puede saltar el proxy
   desde el cliente.

---

## 4. El módulo de plazos, en detalle

La regla más importante de todo el sistema:

> **Si el estudio no configuró días de aviso, no se marca nada.**

Un plazo puede llevar trescientos días esperando y el sistema no lo marca,
porque no hay ningún número con que compararlo.

### El flujo completo

```
El abogado escribe          El sistema             El sistema         La pantalla
la fecha y el respaldo  →   vigila           →     avisa        →     muestra color
                           (compara)              (guarda el       (y su explicación)
                                                 registro)
```

### Las cuatro piezas del módulo

| Pieza | Archivo | Qué hace |
|---|---|---|
| `NivelPlazo` | `api/app/Services/Plazos/NivelPlazo.php` | Compara dos fechas. Función pura, sin base de datos. |
| `VigilanteDePlazos` | `api/app/Services/Plazos/VigilanteDePlazos.php` | Marca los vencidos. Corre sola, todas las mañanas. |
| `Plazo` | `api/app/Models/Plazo.php` | Guarda la fecha, el respaldo y las marcas. |
| Disparador | `migración 2026_10_17_000018` | Impide cambiar el respaldo y la fecha, en la base. |

### Por qué `NivelAlertaPlazo::SinAlerta` es una respuesta

Porque no tener número configurado **no es lo mismo** que tener todo en orden.
Devolver `EnTiempo` sería mentir: affirmaría que la fecha está lejana cuando en
realidad el sistema no tiene con qué compararla.

El valor `SinAlerta` se muestra en gris, con el texto: *"Este plazo no se marca
porque el estudio no configuró días de aviso."*

### Por qué el estado se guarda y no se calcula al mostrar

Para que se pueda preguntar *"¿cuándo nos dimos cuenta?"*. Un tablero que
calcula el color al mostrar responde "hoy está rojo" y no dice desde cuándo.

Guardar `vencido_detectado_at` deja constancia de que el sistema lo verá el
martes, aunque la fecha haya pasado el lunes. Y la segunda corrida del vigilante
no pisa esa marca: **la fecha en que el estudio se enteró no depende de cuántas
veces se corrió el vigilante.**

### Lo que el sistema NO sabe

`NivelPlazo` no sabe si el plazo corre en días hábiles o corridos, si hay
feriados de por medio, si la causa está suspendida o si hubo una prórroga. Nada
de eso está en el sistema y no se va a inventar.

El cálculo se hace con fechas en UTC a propósito: la diferencia entre dos fechas
no depende del horario de verano, y usar la zona local introduce un día de error
dos veces al año, que es exactamente la clase de error que este sistema no quiere
tener.

---

## 5. El módulo de documentos, en detalle

Tres columnas hacen la detección posible:

| Columna | Qué es | Ejemplo |
|---|---|---|
| `clave_natural` | Cómo identifica el estudio a este documento | `"C-1234-2026-4521"`, `"CONTRATO-0412"` |
| `huella_sha256` | Resumen criptográfico del CONTENIDO | 64 caracteres |
| `version_actual` | Qué versión se considera vigente | `2` |

Con las tres, la decisión es una tabla y no un juicio:

| Contenido | Versión que trae | Resultado | Por qué |
|---|---|---|---|
| Distinto | No hay documento | **Nuevo** | Primera vez que llega |
| Distinto | Mayor | **Versión nueva** | Es la evolución esperada |
| Igual | Igual | **Duplicado exacto** | Es la misma copia de nuevo |
| **Distinto** | **Igual o menor** | **COLISIÓN** | El documento volvió cambiado |

### Por qué la colisión no se resuelve automáticamente

Porque el sistema no puede saber cuál de las dos copias es la buena. Puede ser
que alguien respaldó mal el archivo, o que dos personas recibieran versiones
distintas y no lo sepan, o que el estudio no tenga la última copia en el
archivador físico.

Cualquier decisión automática de este caso puede dejar al abogado trabajando
sobre el documento equivocado. Por eso la colisión **no sobrescribe**: levanta
una incidencia y que una persona vaya al archivador a mirar.

### Por qué la huella se calcula en el servidor

`RecibirDocumentoRequest` **elimina** `huella_sha256` del formulario antes de
validar. Si el cliente pudiera mandar la huella, podría mandar "la misma" para un
contenido distinto, y toda la detección sería decorativa.

### Por qué la copia descartada se guarda igual

En una colisión, esa copia es la evidencia: es el segundo contenido distinto que
existe para el mismo documento. Sin guardarla, el estudio tendría que volver al
correo donde llegó para poder comparar.

---

## 6. Los roles, en una tabla

El expediente es el centro, y **no todas las personas del estudio ven todos los
expedientes**.

| | Administrador | Socio | Abogado | Pasante |
|---|---|---|---|---|
| Toda la cartera del estudio | sí | sí | **no** | **no** |
| Abrir expedientes | sí | sí | sí | no |
| Cambiar estado de causa | sí | sí | sí | no |
| Registrar plazos, documentos, horas | sí | sí | sí | sí |
| Ver clientes con causa en su cartera | sí | sí | sí | sí |
| Ver clientes de todo el estudio | sí | sí | **no** | **no** |
| Administrar catálogos y usuarios | sí | no | no | no |

La fila del abogado es la que define el producto: **un abogado ve su cartera, no
la del colega**. El filtro va en la consulta, no en la vista: filtrar después de
traer significa haber recibido los datos ajenos en memoria.

---

## 7. Dos filtros, no uno

| Capa | Qué hace | Qué detecta |
|---|---|---|
| Base de datos (Row Level Security) | Impide que PostgreSQL devuelva filas de otro estudio | Todo lo que se le escape al código |
| Aplicación (Policies) | Decide y filtra dentro del estudio | Errores de lógica y consultas mal escritas |

La base es la última línea. Ver [03](03-MULTITENANCY-Y-ROLES.md).

---

## 8. Lo que este sistema NO hace

Escrito en negrita en todas partes, porque es lo que más se malentiende:

- **NO calcula plazos legales.** Ni en el código, ni en la base, ni en un
  archivo de configuración por omisión.
- **NO interpreta la ley.** No dice si una demanda prospera, ni si un documento
  sirve, ni si un plazo se cumplió de verdad.
- **NO acredita el cumplimiento de un plazo.** Un "vencido" significa
  exactamente *"la fecha que escribiste ya pasó"*, y nada más.
- **NO emite documentos tributarios.** No timbre, no factura. Lo que hay es un
  registro de lo que el estudio ya emitió por su cuenta.
- **NO borra expedientes, documentos, versiones ni plazos.** Para dejar de usar
  un registro se anula, con su motivo. Dos pruebas vigilan que esas rutas no
  aparezcan.
- **NO genera números de causa.** Ese dato lo coloca el tribunal o el estudio.
  El único número que la aplicación genera es el `folio`, que es interno y no
  significa nada fuera del sistema.

Ver [09](09-API.md) para las rutas que no existen.

---

Siguiente: [02 Modelo de datos](02-MODELO-DE-DATOS.md).