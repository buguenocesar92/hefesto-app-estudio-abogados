# 10 - Pruebas y estado

Este documento separa **lo probado** de **lo no probado**, sin adornos.

---

## 1. Estado general

> **CÓDIGO ESCRITO, SIN EJECUTAR.**
>
> En el entorno donde se escribió esta aplicación **no había Docker, ni PHP, ni
> Composer, ni Node, ni PostgreSQL**. No se corrió ni una línea de este código.
>
> **No hay ni un solo resultado de prueba real en esta aplicación.**

| Qué | Estado |
|---|---|
| Código de la API | escrito, **sin ejecutar** |
| Código de la interfaz | escrito, **sin ejecutar** |
| Migraciones | escritas, **sin ejecutar** |
| Semilla | escrita, **sin ejecutar** |
| Pruebas | escritas, **sin ejecutar** |
| Aislamiento entre estudios | **no verificado en una base real** |
| Detección de colisión de versión | **no verificada en una base real** |
| Vigilancia de plazos | **no verificada en una base real** |
| Barrido de caracteres | **ejecutado** (ver sección 5) |
| Sintaxis de `entrypoint.sh` | **ejecutada** con `bash -n` (ver sección 5) |

**Nada de lo que hay en este repositorio se ha visto funcionando.** Lo que hay es
código que refleja una intención y una arquitectura, y una lista de pruebas que
**alguien con un entorno real debería correr antes de confiar en nada de esto**.

---

## 2. Lo único que se ejecutó

### 2.1 Barrido de caracteres no latinos

**Sí se ejecutó.** Sobre todos los archivos de texto de la aplicación.

```
powershell -File tools/barrido-caracteres.ps1 -Ruta apps/estudio-abogados
```

Compara cada carácter contra los rangos de escritura no latina (cirílico,
hebreo, árabe, devanagari, tailandés, chino, coreano, yi, CJK de ancho
completo) y reporta cada coincidencia con archivo, línea, texto y código del
carácter.

**Resultado de la última corrida: 0 marcas.**

> Este barrido se hizo porque el generador de texto mezcló caracteres de otros
> alfabetos en varios comentarios a lo largo de la escritura. Se encontró y se
> corrigió en cada caso. La herramienta quedó en `tools/barrido-caracteres.ps1`.

### 2.2 Sintaxis del script de entrada

**Sí se ejecutó**, con el GNU bash 5.3.9 disponible en el entorno:

```bash
bash -n deploy/api/docker/entrypoint.sh
# código de salida: 0, sin salida
```

Es la única validación de ejecución real que tiene este repositorio.

---

## 3. Lo que NO se ejecutó, y por qué

| No se pudo | Por qué |
|---|---|
| `composer install` | No hay Composer. |
| `php artisan migrate` | No hay PHP ni Composer, y sin `vendor/` la aplicación no arranca. |
| `php artisan test` | Igual que arriba. |
| `npm run build` | No hay Node en el entorno. |
| `docker compose up` | No hay Docker. |
| `bash -n` sobre los archivos YAML | No es shell. Los compose se revisaron a ojo. |

**En consecuencia, no se puede afirmar que ninguna de las quince migraciones sea
válida**, ni que el código PHP no tenga errores de sintaxis, ni que los nombres
de tabla y de columna calcen con los que usan las consultas.

---

## 4. Las pruebas que hay escritas

Son reales y apuntan a lo que importa. **Pero están sin ejecutar.**

### 4.1 Aislamiento entre estudios

`api/tests/Feature/Aislamiento/AislamientoDeDatosTest.php`

Corre contra **PostgreSQL de verdad**, porque el aislamiento está implementado
con Row Level Security, que es una función de PostgreSQL. En SQLite estas
pruebas pasarían sin comprobar nada.

| Prueba | Qué comprueba |
|---|---|
| `test_sin_contexto_de_estudio_la_consulta_falla_en_vez_de_traer_datos` | Sin contexto, la consulta **lanza excepción**, no devuelve cero filas. |
| `test_cada_estudio_solo_ve_sus_propios_expedientes` | Cada estudio ve solo los suyos. |
| `test_el_expediente_de_otro_estudio_no_se_puede_leer_ni_escribir` | No se lee ni se modifica. |
| `test_un_expediente_no_ve_los_documentos_de_otro_estudio` | Los documentos tampoco cruzan. |
| `test_no_se_puede_insertar_una_fila_con_el_estudio_de_otro` | La política `WITH CHECK` impide escribir. |
| `test_los_clientes_de_otro_estudio_no_se_ven` | Los clientes tampoco. |
| `test_los_plazos_de_otro_estudio_no_se_ven` | Los plazos tampoco. |
| `test_las_partes_de_otro_estudio_no_se_ven` | Las partes tampoco. |
| `test_el_contexto_de_estudio_no_sobrevive_a_la_transaccion` | La variable de contexto desaparece al terminar la transacción. |
| `test_la_funcion_de_contexto_falla_cerrada_sin_estudio` | `app_tenant_id()` lanza excepción sin contexto. |

> La primera prueba es la que más importa, y va al revés de lo intuitivo: lo que
> se espera **no** es "cero filas", sino un **error**. Cero filas sería más
> cómodo de probar, pero dejaría pasar el caso peligroso: que alguien lo
> "arregle" devolviendo `NULL` y termine leyendo filas del estudio equivocado sin
> que nada lo delate.

### 4.2 Un abogado no ve la cartera de otro

`api/tests/Feature/Aislamiento/CarteraDelAbogadoTest.php`

Esta es la segunda prueba más importante, y cubre el otro lado del aislamiento,
que la RLS **no** resuelve: dentro de un mismo estudio hay varios abogados, y las
dos carteras son del mismo tenant.

| Prueba | Qué comprueba |
|---|---|
| `test_un_abogado_solo_ve_las_causas_de_su_cartera` | El filtro va en la consulta. |
| `test_un_abogado_no_puede_abrir_una_causa_de_otro_abogado` | La Policy lo frena. |
| `test_el_socio_ve_toda_la_cartera_del_estudio` | El socio sí ve todo. |
| `test_el_administrador_ve_toda_la_cartera_del_estudio` | El administrador también. |
| `test_una_cuenta_sin_ficha_de_abogado_no_ve_nada` | Una cuenta sin ficha de abogado no ve nada. |
| `test_el_pasante_ve_su_cartera_pero_no_abre_causas` | El pasante no abre expedientes. |

### 4.3 La colisión de versión de un documento

`api/tests/Feature/Documentos/ColisionDeVersionTest.php`

| Prueba | Qué comprueba |
|---|---|
| `test_un_documento_nuevo_se_registra_como_version_uno` | Caso normal. |
| `test_una_version_nueva_se_agrega_y_sustituye_a_la_anterior` | La anterior **no se borra**. |
| `test_un_duplicado_exacto_no_agrega_nada` | No crea versiones de más. |
| **`test_la_colision_no_sobreescribe_y_abre_incidencia`** | **El caso central.** |
| `test_una_version_menor_tambien_es_colision` | El documento no retrocede solo. |
| `test_avisa_cuando_el_documento_ya_estaba_en_otro_expediente` | El aviso más fácil de pasarse por alto. |
| `test_la_huella_se_calcula_en_el_servidor_y_no_se_acepta_del_formulario` | La huella no se puede mandar. |

La prueba de la colisión comprueba cuatro cosas, en este orden:

1. Se detectó: el resultado es `colision_version`.
2. **No se sobrescribió nada:** huella, versión y ruta siguen iguales.
3. Se abrió una incidencia de severidad **alta**.
4. La copia que llegó quedó guardada como evidencia, pero **no** como versión
   vigente.

### 4.4 Un plazo vencido se marca solo

`api/tests/Feature/Plazos/PlazoVencidoSeMarcaSoloTest.php`

| Prueba | Qué comprueba |
|---|---|
| `test_el_vigilante_marca_el_plazo_cuya_fecha_ya_paso` | El caso normal. |
| `test_no_marca_un_plazo_que_aun_no_llega` | No marca antes de tiempo. |
| `test_no_marca_un_plazo_cumplido_todavia_vencido` | Un plazo se puede cumplir con atraso, y eso es información. |
| **`test_sin_dias_de_alerta_no_marca_nada_aunque_la_fecha_haya_pasado`** | **La prueba inversa.** |
| `test_la_marca_de_vencimiento_no_se_pisa_al_correr_de_nuevo` | La fecha en que el estudio se enteró no depende de cuántas veces se corrió. |
| `test_el_texto_de_respaldo_no_se_puede_modificar` | El disparador de la base funciona. |
| `test_la_fecha_de_vencimiento_no_se_puede_modificar` | Ídem para la fecha. |

> La cuarta es la más importante: un plazo con la fecha de hace un año **no se
> marca** si el estudio no configuró días de aviso. Un tablero con huecos se nota
> y se corrige; uno con colores falsos se usa.

### 4.5 Sin rutas de borrado

`api/tests/Feature/Documentos/DocumentosNoSeBoranTest.php`

| Prueba | Qué comprueba |
|---|---|
| `test_no_existe_ninguna_ruta_delete_en_toda_la_api` | Cero rutas `DELETE`. |
| `test_no_existe_ningun_metodo_destroy_en_los_controladores` | Cero métodos `destroy()`. |
| `test_no_existe_ruta_para_modificar_el_respaldo_de_un_plazo` | El respaldo no se cambia por la API. |

### 4.6 El nivel de alerta, sin base de datos

`api/tests/Unit/NivelPlazoTest.php`

Ocho pruebas de `NivelPlazo`, que es una función pura. Se puede correr sin
PostgreSQL: `php artisan test --testsuite=Unit`.

| Prueba | Qué comprueba |
|---|---|
| `test_una_fecha_ya_pasada_es_vencida` | |
| `test_la_fecha_de_hoy_es_critica` | |
| `test_dentro_de_la_ventana_critica_es_critica` | |
| `test_dentro_de_la_ventana_de_aviso_es_proximo` | |
| `test_fuera_de_la_ventana_es_en_tiempo` | |
| **`test_sin_dias_configurados_no_dice_nada`** | **Devuelve `sin_alerta`, no `en_tiempo`.** |
| `test_sin_alerta_el_color_es_gris` | Un gris, no un verde. |
| `test_sin_ventana_critica_se_usa_el_nivel_proximo` | Un dato menos, no uno inventado. |
| `test_el_calculo_de_dias_usa_calendario_y_no_zona_horaria` | El cambio de horario no lo altera. |
| `test_la_diferencia_es_simetrica` | |
| `test_el_resumen_no_usa_la_palabra_plazo_legal` | Ni "legal" ni "término" en lo que se muestra. |

---

## 5. Cómo correrlas, cuando haya un entorno real

```bash
# Base de datos de pruebas. TIENE que ser PostgreSQL.
createdb -U postgres estudio_abogados_test

# Preparar.
cd api
composer install
php artisan key:generate

# Correr todo.
php artisan test

# Por Priorities.
php artisan test --testsuite=Aislamiento
php artisan test --testsuite=Documentos
php artisan test --testsuite=Plazos
php artisan test --testsuite=Unit
```

> **El aviso de `phpunit.xml`:** `DB_DATABASE=estudio_abogados_test` y
> `DB_CONNECTION=pgsql`. **No cambiar a SQLite.** Las pruebas de aislamiento
> pasarían sin comprobar nada, y eso es peor que no tenerlas.

Y antes de recibir datos reales:

```bash
php artisan estudio:verificar-aislamiento
```

---

## 6. Los riesgos que quedan

Ordenados por lo que duelen.

### 6.1 El aislamiento entre estudios no está verificado en una base real

**El riesgo:** que un estudio vea las causas, los clientes y los documentos de
otro.

Es lo más grave porque los datos son de personas identificables en causas, y
porque el fallo es silencioso: una fuga en este sistema se ve como una pantalla
que funciona bien y muestra un nombre que no debería.

**Qué lo reduce:** la RLS está en la base y no en el código, la política falla
cerrada, y `estudio:verificar-aislamiento` corre en el despliegue.

**Qué falta:** correr las pruebas, y correr el verificador contra una base
verdadera.

### 6.2 La detección de colisión no está verificada

**El riesgo:** que el sistema sobrescriba un documento cuando debería avisar, o al
revés, que avise cuando debería guardar la versión nueva.

**Qué lo reduce:** la lógica de decisión está en una función pura
(`DetectorDeColision`) que se puede probar sin base de datos, y la escritura
está en otra clase.

**Qué falta:** correr las pruebas.

### 6.3 No hay revisión de seguridad externa

**El riesgo:** lo que no se pensó. Aislamiento de estudio y cartera están
cubiertos en el diseño; el resto del código **no ha sido mirado por nadie más que
quien lo escribió**.

### 6.4 No hay pruebas de penetración

**El riesgo:** que la superficie HTTP tenga un agujero que nadie buscó.

### 6.5 El respaldo no se ha probado

**El riesgo:** descubrir que el respaldo no se restaura cuando haga falta. Un
respaldo sin restauración probada no es un respaldo.

### 6.6 No hay gestión de incidentes

**El riesgo:** que haya que saber qué hacer si un expediente se filtra, y no
tenerlo escrito.

### 6.7 No hay política de retención

**El riesgo:** conservar datos personales indefinidamente. La Ley 19.628 pide que
no se guarden para siempre, y **esta aplicación no tiene ninguna política de
retención configurable**.

### 6.8 El volumen de documentos no está cifrado en reposo

**El riesgo:** quien acceda al disco lee los documentos.

### 6.9 La verificación de firma del token no se pudo terminar de probar

La implementación usa `firebase/php-jwt` con `JWK::parseKeySet`, que es una
librería que **sí** hace la verificación criptográfica completa, incluida la
comprobación de la firma contra la clave que corresponde al `kid` del token.

Lo que **no** se pudo hacer es probarlo contra un Authentik real, porque no hay
proveedor de identidad en este entorno. La clase está escrita para **lanzar
excepción** ante cualquier duda, y en ningún camino devuelve afirmaciones sin
comprobar: si no se pueden obtener las claves públicas, lanza excepción; si la
firma no cuadra, `firebase/php-jwt` lanza excepción.

> La regla que se siguió: **si algo no se puede comprobar, se lanza excepción en
> vez de devolver datos.** No hay ningún camino en que esta clase devuelva
> afirmaciones sin haber comprobado firma, emisor, audiencia y vigencia.

**Riesgo residual:** que el comportamiento real con un Authentik mal configurado
sea distinto de lo esperado. Por eso hay que probarlo antes de usarlo.

---

## 7. Conclusión

**Esto no está listo para un estudio real.**

No porque el diseño esté mal, sino porque **nada de esto se ha ejecutado**. Hay
código, hay migraciones, hay pruebas y hay documentación, y ninguna de esas
cuatro cosas se ha visto funcionando.

La advertencia del proyecto se sostiene en que **este sistema organiza y
vigila; no acredita el cumplimiento de un plazo**. Un "vencido" significa
exactamente *"la fecha que escribiste ya pasó"*, y nada más. Ni la fecha es
correcta por el hecho de que el sistema la vigile, ni el respaldo es válido por
el hecho de que esté escrito.

Antes de poner esto delante de una persona:

1. Montar un entorno con Docker.
2. Correr las migraciones.
3. Correr `estudio:verificar-aislamiento`.
4. Correr **todas** las pruebas.
5. Probar el ingreso con un Authentik de verdad.
6. Probar un respaldo, restaurándolo.
7. Una revisión de seguridad externa.
8. Recién entonces, un estudio piloto.

---

Volver al [README](../README.md).

---

## Los dos roles de PostgreSQL: escritos, no ejecutados

La separación entre el rol de arranque (`estudio_abogados_admin`) y el rol de la
aplicación (`estudio_app`) está escrita en el `compose`, en
`deploy/postgres/inicializar-rol.sh` y en
[03-MULTITENANCY-Y-ROLES.md](03-MULTITENANCY-Y-ROLES.md). **No se ha ejecutado
ninguna vez.**

En el equipo donde se escribió este cambio no había PostgreSQL ni Docker. Queda
pendiente:

1. Levantar el `compose` con el volumen de PostgreSQL vacío y confirmar que el
   rol `estudio_app` se crea sin error.
2. Confirmar con `SELECT rolname, rolsuper, rolbypassrls FROM pg_roles` que el
   rol de la conexión no es superusuario ni salta la RLS.
3. Correr `php artisan estudio:verificar-aislamiento` y confirmar que sale con
   código 0.
4. Probar el escenario que motiva todo el cambio: un estudio intentando leer
   causas o plazos de otro.

### La columna equivocada en el comando verificador: corregida, sin comprobar

Al revisar `EstudioVerificarAislamientoCommand.php` se vio que la consulta que
lee los atributos del rol pedía la columna `usesuper`:

```sql
SELECT current_user AS nombre, usesuper AS superusuario, rolbypassrls AS bypass
  FROM pg_roles WHERE rolname = current_user
```

La columna que existe en `pg_roles` se llama `rolsuper`, no `usesuper`. Con la
columna equivocada la consulta revienta, así que el comando no verificaba nada:
la comprobación del rol —que es la primera y la más grave— quedaba sin hecho
siempre, sin importar el resto del comando.

**Se corrigió.** La consulta pide ahora `rolsuper`, y el mensaje de fallo dice
qué se detectó, por qué anula el aislamiento, qué hacer (`POSTGRES_USER` debe
ser el rol de arranque `estudio_abogados_admin`, que la aplicación nunca usa; el
rol de la aplicación se crea `NOSUPERUSER NOBYPASSRLS` en
`deploy/postgres/inicializar-rol.sh`) y dónde está el patrón de referencia
(`apps/almacen/deploy/postgres/inicializar-rol.sh`).

**Sigue sin comprobarse contra una base real:** en el equipo donde se hizo el
cambio no había PostgreSQL ni Docker. La corrección es estática. Lo verificado es
`bash -n` sobre los scripts, YAML válido y el barrido de `tools/validar_apps.py`.

El mismo error estaba en `restaurante`, y se corrigió allá también.

Lo verificado es estático: `bash -n` pasa sobre el script y el `compose` es YAML
válido, ambas cosas comprobadas por `tools/validar_apps.py`. El razonamiento se
apoya en la documentación oficial de PostgreSQL 18, no en una ejecución.
