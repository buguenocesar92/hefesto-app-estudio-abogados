<script setup lang="ts">
// Tablero: la pantalla que el abogado abre primero.
//
// QUÉ MUESTRA
// -----------
// 1. La advertencia del sistema, siempre visible. Está arriba de todo y no se
//    puede sacar: quien mira un color tiene que saber qué significa.
// 2. Los expedientes que le tocan. El filtro lo hizo la API por cartera.
// 3. Cuántos plazos exigen atención.

definePageMeta({ middleware: 'auth', layout: 'default' })

const { conAviso, vencidos, sinAlerta, cargando, cargar } = usePlazos()
const { usuario } = useSesion()

const referencia = ref<string>('')

onMounted(async () => {
  await cargar()

  const { api } = await import('~/tipos/api')

  try {
    const respuesta = await api.tablero.indice()
    referencia.value = respuesta.fecha_referencia
  } catch {
    referencia.value = ''
  }
})
</script>

<template>
  <div>
    <header class="encabezado">
      <h1>Tablero</h1>
      <p v-if="referencia" class="encabezado__fecha">Hoy es {{ referencia }}</p>
    </header>

    <p class="aviso-sistema">
      Este sistema <strong>no calcula plazos legales</strong> y no acredita que un plazo
      se haya cumplido. Muestra las fechas que escribió cada abogado y avisa cuando la
      fecha registrada ya pasó. El color depende de los días de aviso que configuró el
      estudio, no de ninguna norma.
    </p>

    <section class="cifras">
      <div class="cifra cifra--roja">
        <strong>{{ vencidos.length }}</strong>
        <span>con la fecha ya pasada</span>
      </div>
      <div class="cifra cifra--gris">
        <strong>{{ sinAlerta.length }}</strong>
        <span>sin alerta configurada</span>
      </div>
      <div class="cifra">
        <strong>{{ conAviso.length }}</strong>
        <span>en seguimiento</span>
      </div>
    </section>

    <p v-if="cargando">Cargando...</p>

    <section v-else class="lista">
      <h2>Plazos en seguimiento</h2>

      <p v-if="conAviso.length === 0" class="vacio">
        No hay plazos con días de aviso configurados en la cartera de
        {{ usuario?.nombre }}. Si el estudio no configuró días de aviso, no se marca nada.
      </p>

      <TarjetaPlazo v-for="plazo in conAviso" :key="plazo.id" :plazo="plazo" />
    </section>

    <section v-if="sinAlerta.length > 0" class="lista">
      <h2>Sin alerta configurada</h2>
      <p class="vacio">
        Estos plazos tienen fecha registrada, pero el estudio no configuró días de aviso,
        así que el sistema no los marca. Se muestran en gris a propósito.
      </p>
      <TarjetaPlazo v-for="plazo in sinAlerta" :key="plazo.id" :plazo="plazo" />
    </section>
  </div>
</template>

<style scoped>
.encabezado { display: flex; align-items: baseline; gap: 1rem; }
.encabezado h1 { font-size: 1.3rem; margin: 0 0 1rem; }
.encabezado__fecha { color: #6a6a6a; font-size: 0.85rem; }

.cifras { display: flex; gap: 1rem; margin: 1.2rem 0; }

.cifra {
  border: 1px solid #d8d5cf;
  border-radius: 4px;
  background: #fff;
  padding: 0.8rem 1.1rem;
  min-width: 10rem;
  display: flex;
  flex-direction: column;
}

.cifra strong { font-size: 1.6rem; }
.cifra span { font-size: 0.8rem; color: #6a6a6a; }
.cifra--roja { border-left: 5px solid #b3261e; }
.cifra--gris { border-left: 5px solid #8a8a8a; }

.lista h2 { font-size: 1rem; margin: 1.5rem 0 0.6rem; }
.vacio { color: #6a6a6a; font-size: 0.9rem; }
</style>