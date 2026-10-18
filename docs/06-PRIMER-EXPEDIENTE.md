# 06 - Primer expediente

Un expediente de punta a punta: desde que entra la carta hasta que se anulan las
horas.

> Recordatorio: **este sistema no calcula plazos legales.** En cada paso donde
> aparece una fecha, esa fecha la escribió el abogado. Lo que hace el sistema es
> vigilant.

---

## 1. Antes de empezar: días de aviso

Si el estudio no configuró días de aviso, **ningún plazo se va a marcar**, y el
resto de este recorrido se va a ver en gris.

Para este recorrido, en **Configuración**:

```json
{ "dias_alerta_plazo": 7, "dias_alerta_critica": 2 }
```

Estos son días de alarma interna. **No son plazos legales.**

---

## 2. Registrar al cliente

**Clientes → Nuevo**

| Campo | Ejemplo | Nota |
|---|---|---|
| Tipo | Persona natural | |
| Nombre | Carla Provenzo | |
| Documento de identidad | 11.111.111-1 | |
| Correo | *(opcional)* | |
| Teléfono | *(opcional)* | |

**Qué NO se guarda:** dirección, teléfono de terceros, antecedentes económicos. Si
el estudio necesita algo más, queda en el expediente físico y se anota en el
campo de notas.

> Un cliente en un estudio de abogados no es un dato de contacto: es la lista de
> a quiénes representa este despacho.

---

## 3. Abrir el expediente

**Expedientes → Nuevo**

| Campo | Ejemplo | Quién lo pone |
|---|---|---|
| Título | Despido sin carta de aviso | La persona |
| Número de causa | `C-1234-2026-4521` | La persona, **de la boleta o del tribunal** |
| Materia | Laboral | Catálogo del estudio |
| Cliente | Carla Provenzo | |
| Abogado responsable | Andrea Soto | Define la **cartera** |
| Fecha de inicio | 2026-04-08 | La persona |
| Detalle | *(texto libre)* | |

El `folio` lo genera la aplicación (`EXP-2026-0001`) y es interno: no significa
nada fuera del sistema.

> **El número de causa no lo genera esta aplicación.** Lo coloca el tribunal o el
> estudio. Inventar un número de causa es exactamente el tipo de dato que un
> programa no puede suponer.

---

## 4. Registrar las partes, con su rol

Al abrir el expediente, o después:

| Cliente | Rol en esta causa | Ajena al estudio |
|---|---|---|
| Carla Provenzo | Demandante | no |
| Empresa Contratista SpA | Demandado | **sí** |

**El rol pertenece a la relación con esta causa, no a la persona.** La misma
persona puede ser demandada en una causa y cliente del estudio en otra, y el
sistema lo representa sin duplicarla.

La marca "ajena al estudio" importa: son los terceros que no contratan al
despacho, y sus datos se manejan con más cuidado.

---

## 5. Registrar un plazo

**Expediente → Plazos → Registrar**

| Campo | Ejemplo |
|---|---|
| Título | Presentar prueba documental |
| Fecha de vencimiento | `2026-11-14` |
| Texto de respaldo | *"Fecha fijada por el tribunal en la resolución del 2026-10-30 para el Away."* |

### Lo que pasa al guardar

1. La fecha se guarda **tal como la escribió la persona**.
2. El texto de respaldo se copia al expediente como **texto inmutable**.
3. Se copian los días de aviso del estudio al plazo (7 y 2 en este recorrido).

### Lo que NO pasa

- No se calcula la fecha a partir de nada.
- No se verifica que la fecha sea la correcta.
- No se avisa si el texto tiene sentido jurídico.

### El respaldo no se puede modificar

Si la fecha estaba mal, el sistema **rechaza** el cambio con un error claro:

> *El texto de respaldo de un plazo no se puede modificar. Si la fecha estaba mal,
> anule el plazo y registre uno nuevo.*

El motivo está en [02](02-MODELO-DE-DATOS.md): la razón por la que se escribió
esa fecha es la prueba que el estudio necesita cuando la fecha ya pasó.

---

## 6. Recibir un documento

**Expediente → Documentos → Subir**

| Campo | Ejemplo | Nota |
|---|---|---|
| Tipo de documento | Contrato | Catálogo |
| **Clave natural** | `CONTRATO-2026-0412` | Cómo lo identifica el estudio. **Obligatoria.** |
| Título | Contrato de trabajo | |
| **Versión declarada** | `1` | Qué versión trae esta copia |
| Fecha del documento | 2026-01-15 | La del documento, no la de hoy |
| Archivo | *(el PDF)* | |

> **La huella no se pide.** El servidor la calcula. Si el formulario aceptara una
> huella mandada por el cliente, podría mandar "la misma" para un contenido
> distinto, y toda la detección de colisiones sería decorativa.

### Qué pasa con cada versión

| Situación | Resultado en pantalla |
|---|---|
| Primera vez | **Documento nuevo.** Se guarda la versión 1. |
| Mismo contenido, misma versión | **Duplicado exacto.** No se agrega nada. |
| Contenido nuevo, versión mayor | **Versión nueva.** Se agrega y la anterior queda sustituida. |
| **Contenido nuevo, versión igual o menor** | **Colisión de versión.** No se sobrescribe nada. |

### Un ejemplo de colisión

El estudio tiene la versión 1 de `CONTRATO-2026-0412`. Llega otra copia
declarada como versión 1, pero con **otro contenido**:

```
Colisión de versión

La copia difiere de la versión 1 vigente (huella 4f2a1c9e77b0
contra 8b3d5e1a2f60) pero trae la versión 1. Un documento no cambia
de contenido sin cambiar de versión, así que hay dos copias distintas
del mismo documento y el sistema no sabe cuál es la buena. NO se
sobrescribió nada: revise el archivador físico y decida.
```

Y queda una **incidencia de severidad alta** en el expediente, con el registro
de que pasó y cuándo.

### Por qué el sistema no decide

Porque no puede saber cuál de las dos copias es la buena. Puede ser que alguien
respaldó mal el archivo, o que dos personas recibieran versiones distintas y no
lo sepan, o que el estudio no tenga la última copia en el archivador.

Cualquier decisión automática puede dejar al abogado trabajando sobre el
documento equivocado.

### El otro aviso: "ya estaba en otro expediente"

Si el mismo contenido ya está archivado en otra causa del estudio, aparece:

```
Ya estaba en otro expediente

El contenido de "Contrato de trabajo" (huella 4f2a1c9e77b0) ya
estaba registrado en el expediente EXP-2025-0007. Revise si
corresponde a esta causa o si se archivó en la carpeta equivocada.
```

**No se mueve el documento.** Se avisa, y decide una persona.

---

## 7. Cargar horas

**Expediente → Horas → Registrar**

| Campo | Ejemplo |
|---|---|
| Abogado | Andrea Soto |
| Título | Redacción de la contestación |
| Fecha | 2026-04-15 |
| Minutos | `240` |

**Resumen del expediente:**

| Concepto | Horas |
|---|---|
| Imputadas | 5,50 |
| Facturables | 5,50 |
| Facturadas | 0,00 |
| **No cobradas** | **5,50** |

Esa última fila es el motivo del módulo: a fin de mes hay trabajo hecho que no
está en ninguna parte, y esa plata no se factura nunca.

> **Esto no factura nada.** No emite documentos tributarios. Cuando el estudio
> factura por su cuenta, se registra acá con su número real.

---

## 8. Ver el tablero

**Tablero**

Lo que el abogado ve al abrir el sistema:

```
Este sistema no calcula plazos legales ni acredita que un plazo se haya
cumplido. Muestra las fechas que escribió cada abogado y avisa cuando la
fecha registrada ya pasó.

  2   con la fecha ya pasada
  1   sin alerta configurada
  4   en seguimiento
```

Debajo, cada plazo con su fecha, su color y su texto de respaldo desplegable.

El plazo sin días de aviso aparece en gris, con el texto: *"Este plazo no se
marca porque el estudio no configuró días de aviso."*

---

## 9. Marcar un plazo como cumplido

**Expediente → Plazos → Marcar cumplido**

Queda registrado quién lo hizo y cuándo. Si el plazo ya estaba vencido, **el
sistema lo respeta y no lo devuelve a pendiente**: un plazo puede haberse
cumplido con dos días de atraso, y eso es información que el estudio necesita, no
un error que haya que esconder.

---

## 10. Anular un expediente

**Expediente → Anular**

Se pide el motivo y el expediente pasa a un estado final del catálogo. **No se
borra**: la causa sigue existiendo con todo su historial documental y sus horas.

Un expediente mal abierto se anula. Un expediente mal abierto y borrado no se
puede defender.

---

## 11. Qué quedó registrado

Después de todo el recorrido, la bitácora tiene:

| Recurso | Acción |
|---|---|
| Expediente | `crear`, `consultar`, `anular` |
| Expediente | `registrar_plazo` |
| Expediente | `recibir_documento` |
| Expediente | `registrar_tarea` |
| Documento | `descargar` |
| Incidencia | (en `incidencias_documentales`) |

Cada fila dice **quién**, **qué recurso**, **desde qué dirección** y **cuándo**.

Y en la tabla de la base, la bitácora **solo admite filas nuevas**: hay un
disparador que rechaza `UPDATE` y `DELETE`.

---

Siguiente: [07 Seguridad y Ley 19.628](07-SEGURIDAD-Y-LEY-19628.md).