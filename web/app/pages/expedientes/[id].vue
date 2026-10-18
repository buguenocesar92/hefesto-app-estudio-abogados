<script setup lang="ts">
// Un expediente, de punta a punta.
//
// En una pantalla están las cuatro cosas que se necesitan juntas para saber si
// una causa está en regla: los plazos y su respaldo, los documentos y sus
// versiones, las partes con su rol, y las horas registradas.

definePageMeta({ middleware: 'auth', layout: 'default' })

import { api } from '~/tipos/api'

const ruta = useRoute()
const folio = ruta.params.id as string

const expediente = ref<Record<string, any> | null>(null)
const plazos = ref<any[]>([])
const documentos = ref<any[]>([])
const partes = ref<any[]>([])
const incidencias = ref<any[]>([])
const cargando = ref(true)

onMounted(async () => {
  try {
    const respuesta = await api.expedientes.uno(folio)
    expediente.value = respuesta.datos ?? null
    plazos.value = respuesta.plazos ?? []
    documentos.value = respuesta.documentos ?? []
    partes.value = respuesta.partes ?? []
    incidencias.value = respuesta.incidencias ?? []
  } catch {
    expediente.value = null
  } finally {
    cargando.value = false
  }
})
</script>

<template>
  <div>
    <p v-if="cargando">Cargando...</p>

    <div v-else-if="expediente === null" class="vacio">
      No se encontró ese expediente en su cartera. Si no aparece, es que no es suyo.
    </div>

    <template v-else>
      <header class="encabezado">
        <h1>{{ expediente.titulo }}</h1>
        <p>
          {{ expediente.folio }}
          <span v-if="expediente.numero_causa"> · {{ expediente.numero_causa }}</span>
        </p>
      </header>

      <p class="aviso-sistema">
        Los plazos de esta pantalla los escribió el abogado con el respaldo que acompaña
        a cada uno. El sistema los vigila: no los calcula.
      </p>

      <section v-if="incidencias.length > 0">
        <h2>Incidencias documentales</h2>
        <p class="vacio">Algo no cuadró al recibir un documento. Nada se sobrescribió.</p>
        <TarjetaIncidencia
          v-for="incidencia in incidencias"
          :key="incidencia.id"
          :tipo="incidencia.tipo"
          :severidad="incidencia.severidad"
          :descripcion="incidencia.descripcion"
          :revisada="incidencia.revisada"
        />
      </section>

      <section>
        <h2>Plazos</h2>
        <p v-if="plazos.length === 0" class="vacio">
          No hay plazos registrados en este expediente.
        </p>
        <ul v-else class="planos">
          <li v-for="plazo in plazos" :key="plazo.id">
            <strong>{{ plazo.titulo }}</strong>
            <time :datetime="plazo.fecha_vencimiento">{{ plazo.fecha_vencimiento }}</time>
            <blockquote>{{ plazo.texto_respaldo }}</blockquote>
          </li>
        </ul>
      </section>

      <section>
        <h2>Documentos</h2>
        <p v-if="documentos.length === 0" class="vacio">
          No hay documentos asociados a este expediente.
        </p>
        <table v-else class="tabla">
          <thead>
            <tr>
              <th>Clave</th>
              <th>Documento</th>
              <th>Versión vigente</th>
              <th>Huella</th>
              <th>Versiones recibidas</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="documento in documentos" :key="documento.id">
              <td><code>{{ documento.clave_natural }}</code></td>
              <td>{{ documento.titulo }}</td>
              <td>v{{ documento.version_actual }}</td>
              <td><code>{{ documento.huella_sha256?.slice(0, 12) }}</code></td>
              <td>{{ documento.versiones?.length ?? 0 }}</td>
            </tr>
          </tbody>
        </table>
      </section>

      <section>
        <h2>Partes</h2>
        <p class="vacio">
          El rol pertenece a la relación con esta causa, no a la persona: la misma
          persona puede ser demandada en una causa y cliente en otra.
        </p>
        <table v-if="partes.length > 0" class="tabla">
          <thead>
            <tr>
              <th>Persona</th>
              <th>Rol en esta causa</th>
              <th>Ajena al estudio</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="parte in partes" :key="parte.id">
              <td>{{ parte.cliente?.nombre }}</td>
              <td>{{ parte.rol }}</td>
              <td>{{ parte.es_externo ? 'sí' : 'no' }}</td>
            </tr>
          </tbody>
        </table>
      </section>
    </template>
  </div>
</template>

<style scoped>
h1 { font-size: 1.25rem; margin: 0; }
h2 { font-size: 1rem; margin: 1.6rem 0 0.5rem; }

.encabezado p { margin: 0.2rem 0 1rem; color: #6a6a6a; font-size: 0.88rem; }

.tabla {
  width: 100%;
  border-collapse: collapse;
  background: #fff;
  font-size: 0.88rem;
}

.tabla th, .tabla td {
  text-align: left;
  padding: 0.5rem 0.65rem;
  border-bottom: 1px solid #e6e3dd;
}

.planos { list-style: none; padding: 0; }

.planos li {
  background: #fff;
  border: 1px solid #d8d5cf;
  border-radius: 4px;
  padding: 0.7rem 0.9rem;
  margin-bottom: 0.6rem;
  font-size: 0.9rem;
}

.planos blockquote {
  margin: 0.4rem 0 0;
  padding-left: 0.7rem;
  border-left: 3px solid #d8d5cf;
  color: #4a4a4a;
}

.vacio { color: #6a6a6a; font-size: 0.88rem; }
</style>