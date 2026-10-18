<script setup lang="ts">
// Lista de expedientes.
//
// Cada fila es una causa. El listado que llega ya viene filtrado por cartera: la
// API no mandó las causas de otro abogado, así que esta pantalla no tiene que
// filtrar nada.

definePageMeta({ middleware: 'auth', layout: 'default' })

import { api, type Expediente } from '~/tipos/api'

const expedientes = ref<Expediente[]>([])
const cargando = ref(true)

onMounted(async () => {
  try {
    const respuesta = await api.expedientes.lista()
    expedientes.value = respuesta.data ?? []
  } catch {
    expedientes.value = []
  } finally {
    cargando.value = false
  }
})
</script>

<template>
  <div>
    <h1>Expedientes</h1>

    <p class="aviso-sistema">
      Solo se ven los expedientes de su cartera. Un abogado no ve las causas de otro,
      aunque pertenezcan al mismo estudio.
    </p>

    <p v-if="cargando">Cargando...</p>

    <p v-else-if="expedientes.length === 0" class="vacio">
      Todavía no hay expedientes en su cartera.
    </p>

    <table v-else class="tabla">
      <thead>
        <tr>
          <th>Folio</th>
          <th>Número de causa</th>
          <th>Causa</th>
          <th>Materia</th>
          <th>Estado</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="expediente in expedientes" :key="expediente.folio">
          <td>{{ expediente.folio }}</td>
          <td>{{ expediente.numero_causa ?? 'sin número' }}</td>
          <td>
            <NuxtLink :to="`/expedientes/${expediente.folio}`">
              {{ expediente.titulo }}
            </NuxtLink>
          </td>
          <td>{{ expediente.materia ?? '—' }}</td>
          <td>{{ expediente.estado ?? '—' }}</td>
        </tr>
      </tbody>
    </table>
  </div>
</template>

<style scoped>
h1 { font-size: 1.3rem; margin: 0 0 1rem; }

.tabla {
  width: 100%;
  border-collapse: collapse;
  background: #fff;
  font-size: 0.9rem;
}

.tabla th, .tabla td {
  text-align: left;
  padding: 0.55rem 0.7rem;
  border-bottom: 1px solid #e6e3dd;
}

.tabla th { font-size: 0.78rem; color: #6a6a6a; text-transform: uppercase; }

.vacio { color: #6a6a6a; }
</style>