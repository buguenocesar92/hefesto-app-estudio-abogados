# Estudios de Abogados

Aplicación propia de **Hefesto Studio** para estudios de abogados en Chile:
expedientes, plazos, control documental, partes y horas imputables.

Software original escrito por Hefesto. No es un kit de despliegue de ninguna
herramienta de terceros.

---

## LEER ANTES QUE NADA

> **ESTO NO ESTÁ LISTO PARA UN ESTUDIO REAL.**
>
> Un estudio de abogados maneja los nombres de las personas en causas: quién
> demandó a quién, por qué, con qué documentos. Un error de registro no es un
> inconveniente: es el nombre de alguien, en un expediente, para siempre.
>
> Esta aplicación está en estado **"código escrito, sin ejecutar"**. **No se ha
> corrido en ningún entorno real.** Ver
> [docs/10](docs/10-PRUEBAS-Y-ESTADO.md), que separa lo probado de lo no probado
> sin adornos.

### La restricción que define todo el proyecto

> **Este sistema no interpreta la ley y no calcula plazos legales.**
>
> Un estudio trabaja con plazos computados según normativa y códigos de
> procedimiento, y esos textos cambian. Si el sistema inventa un plazo, el abogado
> pierde un término, y un término perdido no se recupera.
>
> Por eso el registro de plazos es **una fecha y un texto de respaldo que el
> abogado ingresa**, y que el sistema **vigila, no que calcula**.
>
> **El sistema vigila y avisa; no decide.**

No es una limitación de la versión: es una decisión de diseño, y hay tres cosas
que la sostienen:

- **La fecha la escribe el abogado.** No hay ninguna función en toda la
  aplicación que derive un vencimiento de otra fecha, ni que multiplique una
  cantidad de días.
- **El texto de respaldo es obligatorio e inmutable.** La razón por la que se
  escribió esa fecha importa más que la fecha misma, y hay un disparador en la
  base de datos que impide cambiarlo.
- **Sin número configurado, no se marca nada.** Si el estudio no eligió días de
  aviso, el plazo se muestra en gris diciendo que no hay alerta configurada. Un
  tablero con huecos se nota y se corrige. **Un tablero con colores falsos se
  usa**, y se termina confiando en un número que salió de la nada.

**Lo que el sistema NO hace, nunca:**

| No hace | Por qué |
|---|---|
| Calcula plazos legales | Afirmaría una cosa jurídica con una certeza que no tiene. |
| Interpreta la ley | No sabe si una demanda prospera ni si un documento sirve. |
| Acredita el cumplimiento de un plazo | Un "vencido" significa *"la fecha que escribiste ya pasó"*, y nada más. |
| Emite documentos tributarios | Hay un registro de lo que el estudio ya emitió por su cuenta. |
| Borra expedientes, documentos ni plazos | Para dejar de usar un registro se anula, con su motivo. |
| Genera números de causa | Ese dato lo pone el tribunal o el estudio. |

---

## El producto: asociar documentos y avisar cuando algo no encaja

Lo que se pierde en un estudio no es el expediente: es el plazo. Un término que
se pasa no se recupera.

Y lo que más cuesta es la **recepción de documentos nuevos**. El expediente vive
en un archivador físico. Llega un documento por correo o por mano. Hay que
asociarlo al expediente correcto, y lo que casi nunca se detecta es que ese
documento **ya estaba**, o que llegó con una **versión distinta** de la que se
tiene. Cuando eso pasa y nadie lo nota, el estudio sigue trabajando sobre un
documento que ya no es el vigente.

Por eso el centro del sistema es el expediente, y sobre él se detectan dos cosas:

| Detección | Qué compara | Qué hace |
|---|---|---|
| **Colisión de versión** | La huella del contenido y la versión, contra la versión vigente | **No sobrescribe.** Abre una incidencia de severidad alta y avisa. |
| **Documento en otra causa** | La huella del contenido contra las demás causas del estudio | Avisa que el mismo contenido ya estaba archivado en otro expediente. |

Ninguna de las dos se resuelve sola. Las dos dejan constancia y esperan que una
persona vaya al archivador físico y decida.

---

## Módulos

| Módulo | Qué resuelve |
|---|---|
| **Expedientes** | Una causa, con cliente, materia, abogado responsable y estado. **El centro del sistema.** |
| **Plazos y vencimientos** | Fecha registrada y texto de respaldo. Alerta configurable. **Se copia al expediente como texto inmutable.** |
| **Documentos** | Con versión y fecha. **Detecta la colisión.** Nunca se sobrescribe ni se borra. |
| **Partes y clientes** | Clientes, y las partes de cada causa. **El rol es de la relación, no de la persona.** |
| **Tareas y horas** | Horas imputables por expediente. **El módulo que evita que el estudio no cobre.** |
| **Administración** | Abogados, materias, tipos de documento y estados. Catálogos del estudio. |

---

## Plazos: vigilar, no calcular

Lo que el sistema hace, exactamente:

1. El abogado escribe **la fecha** y **el texto que la respalda**.
2. El sistema **compara** esa fecha con la de hoy. Nada más: es aritmética de
   calendario.
3. El vigilante marca, una vez al día, los plazos cuya fecha registrada ya pasó.
4. La pantalla muestra el color, **junto con el texto de respaldo**.

Lo que el sistema NO sabe:

- Si el plazo corre en días hábiles o corridos.
- Si hay feriados de por medio.
- Si la causa está suspendida.
- Si hubo una prórroga.

Nada de eso está en el sistema, y no se va a inventar. Si hay que computar, lo
computa el abogado y lo escribe.

### La regla que más importa

> **Si el estudio no configuró días de aviso, no se marca nada.**

Los días de aviso son una **alarma interna** que elige el socio, no un plazo
legal. Un plazo puede llevar trescientos días esperando y el sistema no lo
marca, porque no hay ningún número con que compararlo.

Ver [docs/01](docs/01-ARQUITECTURA.md), sección "El módulo de plazos".

---

## Documentos: por qué no hay un botón de borrar

Un expediente sin su historial documental no vale nada. Si hay que demostrar qué
versión del contrato tenía en marzo, la respuesta tiene que estar en el sistema.

Por eso, en toda la aplicación:

- **No hay ninguna ruta `DELETE`.** No existen `destroy()` en los controladores.
- Subir una versión nueva **no borra** la anterior: la marca como sustituida, con
  fecha, y sigue descargable.
- Un documento mal incorporado se **anula**, con su motivo.
- Un expediente mal abierto se **anula**, con su motivo.

Y si el mismo documento vuelve con contenido distinto y una versión que no
corresponde, el sistema **no lo guarda encima**: levanta una incidencia y avisa.

Dos pruebas vigilan que esas rutas no aparezcan. Ver
[docs/09](docs/09-API.md).

---

## Datos personales: el dato más sensible del portafolio

Los datos de los clientes y de las causas están protegidos por la **Ley 19.628**,
con estrictez alta. **El nombre de alguien en un expediente basta para dañarlo.**

- Aislamiento por estudio en la **base de datos** (Row Level Security), no solo
  en el código.
- **Un abogado no ve la cartera de otro**, ni dentro del mismo estudio.
- **Auditoría de acceso** a expedientes, en modo solo agregar, con el disparador
  en la base.
- Identificadores UUID: no hay `id` adivinables que se puedan recorrer.

Ver [docs/07](docs/07-SEGURIDAD-Y-LEY-19628.md).

---

## Aislamiento entre estudios

Columna `tenant_id` más **Row Level Security** de PostgreSQL, con `WITH CHECK`,
`FORCE ROW LEVEL SECURITY` y el contexto viajando con `set_config(..., true)`, de
alcance de transacción, para que un worker de colas no arrastre el contexto de
otro estudio.

Es el patrón ya decidido y documentado en
`apps/portal-autoservicio/docs/03-MULTITENANCY.md`, **reutilizado tal cual**, sin
inventar un cuarto enfoque.

Verificado antes de cada despliegue con:

```bash
php artisan estudio:verificar-aislamiento
```

Ver [docs/03](docs/03-MULTITENANCY-Y-ROLES.md).

---

## Estado: código escrito, sin ejecutar

En el entorno donde se escribió esto **no había Docker, ni PHP, ni Composer, ni
Node, ni PostgreSQL**. No se corrió ni una línea.

| Qué | Estado |
|---|---|
| Código de la API | escrito, **sin ejecutar** |
| Código de la interfaz | escrito, **sin ejecutar** |
| Migraciones | escritas, **sin ejecutar** |
| Semilla | escrita, **sin ejecutar** |
| Pruebas | escritas, **sin ejecutar** |
| Aislamiento entre estudios | **no verificado en una base real** |
| Detección de colisión | **no verificada** |
| Vigilancia de plazos | **no verificada** |

Lo único que se ejecutó de verdad: un barrido de caracteres sobre todos los
archivos (`tools/barrido-caracteres.ps1`, resultado 0 marcas) y la comprobación
de sintaxis de `entrypoint.sh` con `bash -n` (código de salida 0).

Ver [docs/10](docs/10-PRUEBAS-Y-ESTADO.md) para el detalle honesto.

---

## Estructura

```
apps/estudio-abogados/
├── README.md
├── .env.example        # sin ningún valor real
├── docs/               # 01 a 10
├── api/                # Laravel + PostgreSQL (RLS)
├── web/                # Nuxt (SSR)
├── deploy/             # Docker Compose con versiones fijadas
└── tools/              # barrido de caracteres no latinos
```

## Documentación

| Documento | De qué trata |
|---|---|
| [01 Arquitectura](docs/01-ARQUITECTURA.md) | Las piezas, el módulo de plazos y el de documentos |
| [02 Modelo de datos](docs/02-MODELO-DE-DATOS.md) | Las tablas, y sobre todo **qué no está en ninguna** |
| [03 Multitenancy y roles](docs/03-MULTITENANCY-Y-ROLES.md) | Aislamiento en la base, y quién puede qué |
| [04 Instalación](docs/04-INSTALACION.md) | Cómo se levanta, y el orden que importa |
| [05 Configuración](docs/05-CONFIGURACION.md) | Los parámetros configurables, y los que no van a existir |
| [06 Primer expediente](docs/06-PRIMER-EXPEDIENTE.md) | Una causa de punta a punta |
| [07 Seguridad y Ley 19.628](docs/07-SEGURIDAD-Y-LEY-19628.md) | Datos personales, auditoría y lo que falta |
| [08 Respaldos](docs/08-RESPALDOS.md) | Respaldo, restauración, y lo que falta |
| [09 API](docs/09-API.md) | Endpoints, y **las rutas que no existen** |
| [10 Pruebas y estado](docs/10-PRUEBAS-Y-ESTADO.md) | Lo probado, lo no probado, y los riesgos |

---

**Hefesto Studio**

Software con criterio. Y con las advertencias escritas antes que los elogios.