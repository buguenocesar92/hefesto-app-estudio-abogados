# 03 - Multitenancy y roles

Este es el documento más importante del proyecto, después del aviso de plazos.

El riesgo número uno de esta aplicación no es que falle una pantalla: es que el
**Estudio Bravo** vea las causas del **Estudio Cortés**. Todo lo demás —la
interfaz, la rapidez, el diseño— es menos importante que esto.

---

## 1. El problema, en una frase

Una sola instalación de PostgreSQL guarda los datos de todos los estudios, y
cada petición HTTP trae un `tenant_id` que dice a quién pertenece lo que se
pide. Si en algún punto una consulta se ejecuta sin ese `tenant_id`, o con el de
otro, el sistema **no da ningún aviso**: devuelve filas que no debería y la
interfaz se ve perfecta.

Por eso el aislamiento **no puede depender solo del código de la aplicación**. Se
implementa en dos capas:

| Capa | Qué hace | Qué detecta |
|---|---|---|
| Aplicación (Policies y middleware) | Decide y filtra | Errores de lógica, consultas mal escritas |
| Base de datos (Row Level Security) | Impide que PostgreSQL devuelva filas ajenas | Todo lo que se le escape a la capa de arriba |

La base es la última línea. Si la aplicación tiene un error, la base sigue sin
entregar datos de otro estudio.

---

## 2. Lo que se eligió: columna `tenant_id` + RLS

Todas las tablas de negocio llevan `tenant_id`, y PostgreSQL aplica una política
de **Row Level Security** que exige que `tenant_id` sea igual al de la petición.

Este es el patrón ya decidido y documentado en
`apps/portal-autoservicio/docs/03-MULTITENANCY.md`, **reutilizado tal cual**. No
se inventó un cuarto enfoque.

### Por qué no un esquema por estudio

La alternativa clásica es darle a cada estudio su propio esquema
(`bravo.expedientes`, `cortes.expedientes`). Se evaluó y se descartó:

1. **Las migraciones se multiplican.** Con esquema por cliente, cada migración
   tiene que aplicarse en TODOS los esquemas. Con 30 estudios son 30 ejecuciones.
   Con 300, 300. Y cada ejecución es una oportunidad de que unas queden
   desalineadas.

2. **El pool de conexiones se vuelve el cuello de botella.** El pool se
   dimensiona por el número de combinaciones (esquema, rol) que se usan de
   verdad. Con columna única, todas las peticiones comparten el mismo pool.

3. **Laravel obliga a duplicar la lógica de esquema.** La conexión pasa a
   depender del usuario conectado, y cada consulta, cada migración, cada prueba
   y cada cola necesita acordarse del esquema correcto. Es la clase de
   acoplamiento que produce fugas: un `DB::table(...)` olvidado, una relación de
   Eloquent mal definida.

4. **Las herramientas de administración se complican.** Respaldar un estudio es
   `pg_dump -n bravo`, lo cual obliga a tener scripts a medida por cliente, y el
   operador termina respaldando la base entera igual.

5. **El aislamiento queda en manos de la aplicación.** El `search_path` es un
   parámetro de sesión. Si queda mal puesto —una conexión reutilizada del pool, un
   error del middleware— la consulta va al esquema equivocado y **no falla**:
   devuelve datos del estudio equivocado sin error visible.

### Por qué entonces columna única + RLS

- Una sola migración, un solo esquema, un solo pool.
- La política se aplica en la base, no en PHP: no se puede "olvidar".
- Un `where tenant_id = ?` olvidado no fuga datos: filtra de más, que es un
  error visible, no invisible.
- Respaldar es `pg_dump` de una base, sin scripts por cliente.

El costo es que **todas** las consultas deben tener el contexto de estudio. Ese
costo está resuelto y forzado por el diseño (sección 4).

---

## 3. El mecanismo, en detalle

Definido en `api/database/migrations/2026_10_17_000021_activar_row_level_security.php`.

### 3.1 La función de contexto

```sql
CREATE FUNCTION public.app_tenant_id() RETURNS uuid ...
    valor := current_setting('app.tenant_id', true);
    IF valor IS NULL OR valor = '' THEN
        RAISE EXCEPTION ...
    RETURN valor::uuid;
```

La función **lanza excepción** si no hay estudio. Podría haber devuelto `NULL` y
filtrado todo silenciosamente, pero se eligió que falle de forma ruidosa:

- Si devuelve `NULL`, la consulta devuelve cero filas sin aviso. Alguien lo
  "arregla" devolviendo `NULL` cuando hace falta y la puerta queda abierta sin
  que nada lo delate.
- Si lanza excepción, el error aparece en el registro **en el momento**, y se
  sabe exactamente qué operación se escapó del middleware.

Es el mismo criterio que un candado: importa que falle cerrado.

### 3.2 La política

Para cada tabla con datos de negocio:

```sql
ALTER TABLE public.expedientes ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.expedientes FORCE ROW LEVEL SECURITY;

CREATE POLICY politica_aislamiento_expedientes ON public.expedientes
    FOR ALL
    TO PUBLIC
    USING (tenant_id = public.app_tenant_id())
    WITH CHECK (tenant_id = public.app_tenant_id());
```

Tres detalles que importan:

- **`USING`** filtra lo que se **lee**.
- **`WITH CHECK`** impide **escribir** una fila con un `tenant_id` distinto. Sin
  esto, un `INSERT` con el estudio equivocado pasaría la lectura y dejaría una
  fila invisible que nadie podría ver ni borrar.
- **`FORCE ROW LEVEL SECURITY`** hace que la política aplique **también al dueño
  de la tabla**. Sin FORCE, el dueño la saltaría. En desarrollo el rol de la
  aplicación suele ser el mismo que creó las tablas: sin FORCE, el aislamiento
  sería decorativo justo en el entorno donde se prueba.

La migración verifica **antes** de tocar nada que cada tabla de la lista exista
y tenga columna `tenant_id`, y falla con `RuntimeException` si no es así. Un error
de tipeo en la lista dejaría una tabla de clientes y causas sin política, y la
migración pasaría igual.

### 3.3 Cómo viaja el estudio

El contexto se fija con:

```php
DB::select('SELECT set_config(?, ?, true)', ['app.tenant_id', $tenantId]);
```

El `true` final es lo más importante del archivo: significa **alcance de
transacción**. Al confirmar o revertir, PostgreSQL descarta el valor solo.

Con un `SET` normal (alcance de sesión), el valor sobreviviría a la petición. Y
el worker de colas **reutiliza la misma conexión** para trabajos de estudios
distintos: un `SET` a sesión significa que un trabajo del Estudio Bravo podría
leer los datos del Estudio Cortés. Ese es exactamente el fallo que este diseño
evita, y por eso `ContextoTenant::establecer()` **lanza excepción si no está
dentro de una transacción**.

### 3.4 Quién fija el contexto

Solo una clase: `App\Services\Tenancy\ContextoTenant`, y solo a través del
middleware `App\Http\Middleware\ResolverTenant`, que envuelve la petición
autenticada en una transacción con el estudio ya fijado.

---

## 4. El punto ciego: la tabla `estudios`

`estudios` **no lleva RLS**, a propósito: se consulta durante el inicio de
sesión, cuando todavía no hay sesión de la que sacar un `app.tenant_id`.

Esa excepción se compensa de dos maneras:

**Compensación 1: la vista `vw_estudios_publicos`.** Antes del inicio de sesión,
la aplicación no lee `estudios` crudo: lee esta vista, que expone **tres**
columnas (`slug`, `nombre`, `activo`) y solo de los estudios activos. No expone
correos, no expone nombres de abogados, no expone nada de clientes o causas.

**Compensación 2: el rol de la aplicación no es superusuario.** Quien tenga
acceso SQL directo a la base puede leer `estudios` entera. Por eso el despliegue
exige revisar los permisos y `estudio:verificar-aislamiento` comprueba que el rol
no sea superusuario ni tenga `BYPASSRLS`.

**Riesgo residual, dicho con todas las letras:** un `slug` se puede enumerar antes
de autenticarse, porque el slug es público (aparece en la URL de entrada). Un
atacante puede descubrir que el Estudio Bravo usa esta aplicación. **No puede ver
más que su nombre.** Si incluso eso se considera inaceptable, la alternativa es
dejar el campo en blanco hasta el inicio de sesión y resolver siempre por dominio
de correo; queda anotado como decisión pendiente del cliente, no como algo
resuelto.

---

## 5. Doble filtro dentro del estudio

La RLS ya garantiza que nada sale del estudio. Dentro del estudio hay un segundo
filtro, en las Policies.

| Recurso | Abogado | Socio | Administrador |
|---|---|---|---|
| Expedientes | **solo los de su cartera** | todos | todos |
| Plazos | los de sus expedientes | todos | todos |
| Documentos | los de sus expedientes | todos | todos |
| Clientes | **los que tienen causa en su cartera** | todos | todos |
| Tareas | las de sus expedientes | todas | todas |
| Cambiar estado de un expediente | sí | sí | sí |
| Catálogos (materias, tipos, estados) | lectura | lectura | lectura y escritura |

### La regla que más importa: la cartera

> Un abogado con rol `abogado` **no ve la cartera de otro abogado del mismo
> estudio**.

La RLS no ayuda acá: las dos carteras son del mismo estudio y la política las
deja pasar. Lo que filtra es `ExpedientePolicy`.

El filtro va **en la consulta**, no en la vista. Filtrar después de traer
significa haber recibido los datos de la cartera ajena en memoria.

### Por qué se responde 404 y no 403

Cuando una Policy rechaza, la respuesta es **404, no 403**: un 403 confirmaría que
el recurso existe. Y como la RLS impide que el recurso exista a ojos de otro
estudio, 404 es además la respuesta correcta en el caso habitual.

---

## 6. Qué hace `estudio:verificar-aislamiento`

Es un comando de despliegue, y **falla con código de salida distinto de cero** si
encuentra un problema:

| Comprobación | Por qué importa |
|---|---|
| El rol no es superusuario | Con superusuario, PostgreSQL **ignora todas las políticas**. El aislamiento se evapora y nada falla. |
| El rol no tiene `BYPASSRLS` | Lo mismo, sin necesidad de ser superusuario. |
| Cada tabla tiene RLS activado | Una tabla de clientes sin RLS devuelve filas de todos los estudios. |
| Cada tabla tiene FORCE RLS | Sin FORCE, el dueño de la tabla se salta la política. |
| Cada tabla tiene su política | Sin política, no hay filtro. |
| `app_tenant_id()` falla cerrada | Si devolviera `NULL`, una consulta sin estudio filtraría en silencio. |

Es la diferencia entre "arrancó el contenedor" y "el aislamiento está puesto".

---

## 7. El riesgo número uno, y qué se hizo para reducirlo

**Riesgo: fuga de datos entre estudios.** Con nombres de clientes, causas,
documentos y horas. El dato más sensible de todo el portafolio de Hefesto.

Mitigaciones, en orden de fuerza:

1. **La base de datos lo impide**, no la aplicación. Un error en PHP no basta
   para filtrar datos.
2. **La política tiene `WITH CHECK`.** No se pueden crear filas en nombre de otro
   estudio.
3. **El contexto tiene alcance de transacción.** El worker de colas no puede
   arrastrar el estudio de otro trabajo.
4. **`FORCE ROW LEVEL SECURITY`.** El dueño de la tabla tampoco salta la
   política.
5. **La función de contexto falla cerrada.** Una consulta sin estudio se rompe de
   forma ruidosa en vez de devolver nada en silencio.
6. **Pruebas de aislamiento** contra PostgreSQL de verdad, que verifican el cruce
   con identificadores reales de otro estudio. Ver `api/tests/Feature/Aislamiento/`.
7. **`estudio:verificar-aislamiento`**, que se corre en cada despliegue.
8. **Bitácora de acceso** a expedientes, con solo agregar y garantía en la base.

**Lo que NO se hizo y sigue siendo un riesgo:**

- No hay revisión de seguridad externa.
- No hay pruebas de penetración.
- Las pruebas de aislamiento **están escritas pero no se ejecutaron**: en el
  entorno donde se escribió esto no había PostgreSQL. Ver
  [10](10-PRUEBAS-Y-ESTADO.md).
- La política protege el dato, pero no el archivo: los documentos se sirven desde
  la API después de autorizar, y la comprobación de ruta se hace en
  `DocumentoController::descargar`. Si se escribiera una ruta maliciosa en la base,
  la comparación con el volumen real impide salir de la carpeta.

---

## 8. Resumen en una tabla

| | Esquema por estudio (descartado) | Columna + RLS (elegido) |
|---|---|---|
| Migraciones | Una por esquema x N estudios | Una sola |
| Pool de conexiones | Se dimensiona por estudio | Uno solo |
| Aislamiento | En la aplicación (`search_path`) | En la base de datos |
| Error de contexto | Falla en silencio | Falla ruidosamente |
| Riesgo de fuga | Alto (depende de cada consulta) | Bajo (la base lo impide) |
| Complejidad en Laravel | Alta | Baja |

---

Siguiente: [04 Instalación](04-INSTALACION.md).

---

## 9. Los dos roles de PostgreSQL

La base de esta aplicación corre con **dos** roles, no con uno. La separación es
deliberada: es lo que hace que el aislamiento entre estudios funcione en vez de
quedar como decoración.

### Qué es cada rol

| Rol | Nombre | De dónde sale | Para qué sirve | ¿Lo usa la aplicación? |
|---|---|---|---|---|
| **Arranque** | `estudio_abogados_admin` | `POSTGRES_USER` | Crear la base y las tareas administrativas del despliegue | **Nunca** |
| **Aplicación** | `estudio_app` | `DB_USERNAME`, creado por `deploy/postgres/inicializar-rol.sh` | Todo el tráfico de la API | **Sí, este y solo este** |

Dos nombres que conviene mirar con calma: el rol de aplicación es `estudio_app`,
no `estudio_abogados_app`, y el valor por omisión de la base en el `compose`
lleva el sufijo `_dev` (`estudio_abogados_dev`). Ninguna de las dos cosas es un
error de tipeo.

El rol de arranque no es una convención del estudio: la imagen oficial de
PostgreSQL ejecuta `initdb --username="$POSTGRES_USER"`, así que ese rol nace
**superusuario**.

### Por qué con un solo rol no servía

La documentación de PostgreSQL 18 lo dice de forma textual:

> *«Superusers and roles with the `BYPASSRLS` attribute always bypass the row
> security system when accessing a table. Table owners normally bypass row
> security as well, though a table owner can choose to be subject to row
> security with `ALTER TABLE ... FORCE ROW LEVEL SECURITY`.*

La segunda frase es la clave. `FORCE ROW LEVEL SECURITY` sujeta al **dueño** de
la tabla, pero **no al superusuario**. Mientras la aplicación conectara con el
rol de `POSTGRES_USER`, ninguna política de este documento se iba a aplicar: un
estudio habría visto las causas, los documentos y los plazos de los demás, sin
error y sin aviso. El aislamiento entre estudios era decorativo.

### Los atributos van todos escritos

`deploy/postgres/inicializar-rol.sh`, montado en
`/docker-entrypoint-initdb.d/`, crea el rol con `NOSUPERUSER`, `NOCREATEDB`,
`NOCREATEROLE`, `NOINHERIT` y `NOBYPASSRLS` **explícitos todos**, aunque
`NOBYPASSRLS` ya sea el valor por omisión.

Se escriben igual a propósito: un rol verificado no debería depender de un valor
por omisión que una versión nueva puede cambiar. El script también falla
ruidosamente si falta `POSTGRES_DB`, `APP_DB_ROLE` o `APP_DB_PASSWORD`.

### Por qué el rol de aplicación es dueño de la base

Justo después de crearlo, el script corre:

```sql
ALTER DATABASE estudio_abogados_dev OWNER TO estudio_app;
```

Las migraciones de Laravel se ejecutan con el rol de la aplicación y necesitan
**crear tablas**. La única forma de darles ese permiso sin convertirlos en
superusuario es que ese rol sea el dueño de la base. Desde PostgreSQL 15 el
esquema `public` pertenece al dueño de la base, así que con esto el rol ya
puede crear tablas ahí.

Y este es el punto exacto del diseño: al ser el dueño, **las tablas quedan a su
nombre**, y por eso el `FORCE ROW LEVEL SECURITY` que usan las tablas sí lo
sujeto. Ser dueño no es saltarse la política; es lo que hace que la política lo
alcance. Un superusuario la saltearía siempre.

### Cómo se comprueba

```
php artisan estudio:verificar-aislamiento
```

El prefijo del comando es `estudio:`, no `estudio-abogados:`. El comando consulta
`pg_roles` sobre el rol de la conexión y falla con código 1 si ese rol es
superusuario o tiene `BYPASSRLS`, y después revisa que cada tabla tenga RLS
activada y forzada con su política, y que la función de contexto siga fallando
cerrada.

**Este comando no se ha ejecutado.** En el equipo donde se escribió este cambio
no había PostgreSQL ni Docker. El diseño es correcto y está sostenido en la
documentación oficial, pero nadie lo ha corrido. Hay además una duda sobre el
comando que no se puede resolver sin una base de verdad: ver
[10-PRUEBAS-Y-ESTADO.md](10-PRUEBAS-Y-ESTADO.md).
