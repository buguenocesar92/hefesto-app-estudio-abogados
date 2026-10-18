<script setup lang="ts">
// Plazos de la cartera.
//
// LA ADVERTENCIA DE ESTA PANTALLA
// -------------------------------
// La lista que llega ya trae el nivel evaluado por la API. Esta pantalla no
// calcula nada, no ordena por urgencia propia y no inventa categorías.
//
// Cuando la API dice `sin_alerta`, el plazo aparece en gris con el motivo. No
// se agrupa como "los que están bien": no hay evidencia de que estén bien.

import type { Plazo } from '~/tipos/api'

definePageMeta({ middleware: 'auth', layout: 'default' })

const plazos = ref<Plazo[]>([])
const cargando = ref(true)

onMounted(async () => {
  const { api } = await import('~/tipos/api')

  try {
    const respuesta = await api.tablero.plazos()
    plazos.value = respuesta.plazos
  } catch {
    plazos.value = []
  } finally {
    cargando.value = false
  }
})

const grupos = computed(() => ({
  vencidos: plazos.value.filter((p) => p.nivel === 'vencido' || p.nivel === 'critico'),
  proximos: plazos.value.filter((p) => p.nivel === 'proximo'),
  sinAlerta: plazos.value.filter((p) => p.nivel === 'sin_alerta'),
  enTiempo: plazos.value.filter((p) => p.nivel === 'en_tiempo'),
}))
</script>

<template>
  <div>
    <h1>Plazos</h1>

    <p class="aviso-sistema">
      Estas fechas las escribió cada abogado, con su texto de respaldo. Esta pantalla
      <strong>no calcula plazos</strong>: solo muestra qué fechas ya pasaron. Un plazo
      marcado acá significa que la fecha que alguien escribió ya pasó, y nada más.
    </p>

    <p v-if="cargando">Cargando...</p>

    <p v-else-if="plazos.length === 0" class="vacio">
      No hay plazos registrados en su cartera.
    </p>

    <template v-else>
      <section v-if="grupos.vencidos.length > 0">
        <h2>La fecha registrada ya pasó o está por pasar</h2>
        <TarjetaPlazo v-for="plazo in grupos.vencidos" :key="plazo.id" :plazo="plazo" />
      </section>

      <section v-if="grupos.proximos.length > 0">
        <h2>Dentro de la ventana de aviso</h2>
        <TarjetaPlazo v-for="plazo in grupos.proximos" :key="plazo.id" :plazo="plazo" />
      </section>

      <section v-if="grupos.sinAlerta.length > 0">
        <h2>Sin alerta configurada</h2>
        <p class="vacio">
          El estudio no configuró días de aviso para estos plazos, así que el sistema no
          los marca. Están en gris a propósito: no saber no es lo mismo que estar bien.
        </p>
        <TarjetaPlazo v-for="plazo in grupos.sinAlerta" :key="plazo.id" :plazo="plazo" />
      </section>

      <section v-if="grupos.enTiempo.length > 0">
        <h2>Fuera de la ventana de aviso</h2>
        <TarjetaPlazo v-for="plazo in grupos.enTiempo" :key="plazo.id" :plazo="plazo" />
      </section>
    </template>
  </div>
</template>

<style scoped>
h1 { font-size: 1.3rem; margin: 0 0 1rem; }
h2 { font-size: 1rem; margin: 1.6rem 0 0.6rem; }
.vacio { color: #6a6a6a; font-size: 0.9rem; }
</style>