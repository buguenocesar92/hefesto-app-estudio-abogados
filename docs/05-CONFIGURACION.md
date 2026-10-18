# 05 - Configuración

> **Este sistema no calcula plazos legales.**
>
> No hay ningún valor de esta tabla que sea un plazo. Los únicos números
> configurables son **días de aviso**, que son una alarma interna del estudio:
> cuántos días antes de la fecha que escribió el abogado aparece un aviso.
>
> Si un campo de aquí significa "cuánto tiene que durar un plazo", es un error de
> este documento, no una funcionalidad pendiente.

---

## 1. Lo que se configura, y lo que no

| Se configura | No se configura, y no habrá |
|---|---|
| Cuántos días antes de la fecha registrada se avisa | Cuántos días tiene un plazo para contestar |
| Cuántos días antes el aviso pasa a crítico | Cuántos días tiene un plazo para apelar |
| La zona horaria del estudio | Cuántos días hábiles o corridos dura algo |
| La moneda y el valor de la hora imputable | Si un plazo se prorroga |
| Los catálogos: materias, tipos, estados | Si una demanda prospera |
| Qué días de la semana se trabaja | Si un documento sirve como prueba |

**La razón por la que la segunda columna no se llena nunca** es que un estudio
trabaja con plazos computados según normativa y códigos de procedimiento, y esos
textos cambian. Un sistema que calcula un vencimiento está afirmando una cosa
jurídica con una certeza que no tiene: si la norma cambió y el sistema no, el
abogado pierde un término, y un término perdido no se recupera.

---

## 2. Los días de aviso

### Qué son

Una alarma interna. El socio elige cuántos días antes de la fecha registrada
quiere que el sistema avise.

Un plazo con fecha **2026-11-14** y `dias_alerta_plazo = 7` aparece en amarillo a
partir del **2026-11-07**. Eso es aritmética de calendario: restar dos fechas.

### Qué NO son

No son términos. No dicen si ese plazo vence en días hábiles o corridos, ni
consideran feriados, ni snsuspensión de la causa, ni prórrogas. Nada de eso está
en el sistema, y no se va a inventar.

### La regla que más importa de todo el sistema

> **Si el estudio no configuró días de aviso, no se marca nada.**

Un plazo puede llevar trescientos días esperando y el sistema no lo marca,
porque no hay ningún número con que compararlo. El tablero muestra el aviso
pidiendo configurar los días, y el plazo aparece en gris con el texto: *"Este
plazo no se marca porque el estudio no configuró días de aviso."*

El motivo es concreto: un "rojo" con un umbral inventado le diría a un abogado
"esto se venció", y alguien movería plazos de personas reales en función de un
número que salió de la nada. **Un tablero con huecos se nota y se corrige. Un
tablero con números falsos se usa.**

### Cómo se cambia

Pantalla **Configuración**, o por API:

```
PUT /api/v1/configuracion

{
  "dias_alerta_plazo": 7,
  "dias_alerta_critica": 2
}
```

Para dejar de marcar:

```json
{ "dias_alerta_plazo": null, "dias_alerta_critica": null }
```

### Los tres estados posibles de un plazo

| Nivel | Cuándo | Color |
|---|---|---|
| `sin_alerta` | No hay `dias_alerta` en este plazo | Gris |
| `vencido` | La fecha registrada ya pasó | Rojo |
| `critico` | Vence hoy, o faltan menos días que `dias_alerta_critica` | Naranja |
| `proximo` | Faltan menos días que `dias_alerta_plazo` | Amarillo |
| `en_tiempo` | Falta más de lo que la ventana de aviso cubre | Verde |

**`sin_alerta` no es "verde".** No tener número configurado no es lo mismo que
tener todo en orden, y el sistema lo dice.

### El umbral crítico es opcional

Si el estudio no configura `dias_alerta_critica`, el nivel crítico solo aparece
para el día en que vence. Es un dato menos, no un dato inventado.

Si se configura el crítico y no el aviso, la API lo rechaza con un 422: una
ventana crítica sin ventana de aviso no tiene sentido y no se deja guardar.

### El snapshot

Al crear un plazo se copian los días de aviso del estudio al propio plazo
(`plazos.dias_alerta`). Si mañana el socio cambia el número de 7 a 15:

- Los plazos nuevos usan 15.
- Los plazos viejos siguen con 7.

El motivo: si se usara el valor actual, cambiar el número reescribiría el pasado.
Un plazo creado en enero quedaría marcado según lo que el socio decidió en junio.

### Cambiar la configuración no reescribe el pasado

El vigilante nunca **desmarca** un plazo. Solo marca. Si un socio sube los días
de aviso de 7 a 30, los plazos que ya estaban marcados en rojo siguen en rojo:
esa información es correcta y no se tira a la basura por cambiar un parámetro.

---

## 3. Variables de entorno

### Aplicación

| Variable | Por omisión | Nota |
|---|---|---|
| `APP_ENV` | `local` | |
| `APP_KEY` | — | **Obligatoria.** Generar con `php artisan key:generate`. |
| `APP_URL` | `http://localhost:8000` | |
| `APP_WEB_URL` | `http://localhost:3000` | |
| `ESTUDIO_DOMINIO` | `estudio.ejemplo.cl` | Lo usa Caddy para el certificado TLS. |

### Base de datos

| Variable | Por omisión | Nota |
|---|---|---|
| `DB_DATABASE` | `estudio_abogados_dev` | |
| `DB_USERNAME` | `estudio_app` | |
| `DB_PASSWORD` | — | |
| `DB_HOST` | `postgres` | |
| `DB_PORT` | `5432` | |

> **El rol de esta conexión NO puede ser superusuario ni tener `BYPASSRLS`.** Con
> cualquiera de las dos, PostgreSQL ignora las políticas de Row Level Security y
> un estudio vería las causas de otro. Lo comprueba
> `php artisan estudio:verificar-aislamiento`.

### Vigilancia de plazos

| Variable | Por omisión | Nota |
|---|---|---|
| `ESTUDIO_DIAS_ALERTA_PLAZO` | *(vacío)* | **Vacío significa que no se marca nada.** |
| `ESTUDIO_DIAS_ALERTA_CRITICA` | *(vacío)* | |
| `ESTUDIO_VIGILANCIO_PLAZOS_CRON` | `0 7 * * *` | La **hora** en que corre el vigilante. No es un plazo: es la hora de la revisión. |

### Documentos

| Variable | Por omisión | Nota |
|---|---|---|
| `DOCUMENTOS_PATH` | `/var/www/html/storage/app/documentos` | Debe estar en un volumen con respaldo. |
| `ESTUDIO_AVISAR_DOCUMENTO_DUPLICADO` | `false` | Avisa si un documento ya estaba en otra causa. |

### Proveedor de identidad

| Variable | Nota |
|---|---|
| `AUTHENTIK_ISSUER` | Debe terminar en `/`, y con la aplicación correcta. |
| `AUTHENTIK_CLIENT_ID` | |
| `AUTHENTIK_CLIENT_SECRET` | |
| `AUTHENTIK_REDIRECT_URI` | Debe coincidir **exactamente** con la del proveedor. |
| `AUTHENTIK_SCOPES` | `openid profile email` |
| `AUTHENTIK_ALGORITMO` | `RS256` |
| `AUTHENTIK_TOLERANCIA_MINUTOS` | `2`. Tolerancia de reloj entre servidores, **no** un margen de seguridad: un token expirado por más de dos minutos se rechaza igual. |

### `.env.example` no contiene ningún valor real

Todas las contraseñas y secretos quedan vacíos. Se generan con:

```bash
openssl rand -base64 48                    # APP_KEY
openssl rand -base64 60 | tr -d '\n'       # AUTHENTIK_SECRET_KEY
openssl rand -hex 32                       # AUTHENTIK_BOOTSTRAP__TOKEN
```

Ojo: PostgreSQL no acepta contraseñas de más de 99 caracteres.

---

## 4. Facturación electrónica: lo que no se hace

Esta aplicación **no emite, no timbre, no anula y no envía documentos tributarios
electrónicos**, y no habrá código que lo haga.

Hefesto tiene el kit **LibreDTE**, pero su versión publicada es una **beta** y su
API es de **solo lectura**: no permite automatizar la emisión.

Lo que hay en `tareas` es un **registro**: el estudio escribe a mano lo que ya
emitió por su cuenta, con su número real.

| Campo | Qué es |
|---|---|
| `minutos_imputados` | Horas que el abogado registró trabajar. |
| `minutos_facturados` | Lo que el estudio ya cargó a facturación por su cuenta. |
| `numero_factura` | El número real que el estudio emitió. |
| `fecha_facturacion` | Cuándo. |

Es un respaldo contable, **no un emisor**. Ver
[TareaController](../../api/app/Http/Controllers/Api/TareaController.php).

---

## 5. Los catálogos del estudio

| Catálogo | Ejemplos | Quién los cambia |
|---|---|---|
| Materias | Civil, Laboral, Familia y Sucesiones | Administrador |
| Tipos de documento | Contrato, Poder, Sentencia, Carta | Administrador |
| Estados de expediente | Ingresada, En revisión, En tramitación, Terminada | Administrador |
| Días de aviso | 7 y 2 | Administrador |

El sistema **no sabe qué materias existen** ni **qué documentos exige una
materia**. Solo los guarda como etiqueta para agrupar y buscar.

### Por qué solo el administrador cambia los catálogos

Un abogado puede proponer una materia nueva escribiéndoselo al socio, pero
cambiarla desde la pantalla sería una forma discreta de renombrar el historial de
causas ya clasificadas.

### La máquina de estados

Cada estado declara por cuáles se puede pasar. El sistema **impide** una
transición que el estudio no declaró, pero **no decide** cuál corresponde: el
sistema no sabe si una causa está lista para archivar; lo sabe el abogado.

Ninguna transición tiene una cantidad de días asociada. Ver
[MaquinaDeEstadosExpediente](../../api/app/Services/Expedientes/MaquinaDeEstadosExpediente.php).

---

## 6. Una configuración recomendada para empezar

| Parámetro | Valor | Por qué |
|---|---|---|
| `dias_alerta_plazo` | `7` | Una semana. Es un número operativo, no un plazo. |
| `dias_alerta_critica` | `2` | Para tener margen de reacción. |
| `zona_horaria` | `America/Santiago` | |
| `valor_hora_imputable` | *(el del estudio)* | No hay valor por omisión: el sistema no sabe cuánto cobra un abogado. |
| `avisar_documento_duplicado` | `true` | Activado desde el principio. |

---

Siguiente: [06 Primer expediente](06-PRIMER-EXPEDIENTE.md).