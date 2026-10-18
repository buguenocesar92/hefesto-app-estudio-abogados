<script setup lang="ts">
// Entrada al sistema.
//
// La persona elige el estudio y el proveedor de identidad la autentica. Esta
// pantalla NO pide usuario ni contraseña: la sesión vive en Authentik.

import { api } from '~/tipos/api'

definePageMeta({ layout: false })

const estudios = ref<Array<{ slug: string; nombre: string }>>([])

onMounted(async () => {
  try {
    const respuesta = await api.sesion.estudios()
    estudios.value = respuesta.estudios
  } catch {
    estudios.value = []
  }
})
</script>

<template>
  <main class="entrar">
    <div class="entrar__caja">
      <h1>Estudios de Abogados</h1>
      <p class="entrar__sub">Hefesto Studio</p>

      <p v-if="estudios.length === 0" class="entrar__vacio">
        No hay estudios disponibles. Revise la configuración del proveedor de identidad.
      </p>

      <ul v-else class="entrar__lista">
        <li v-for="estudio in estudios" :key="estudio.slug">
          <NuxtLink :to="`/entrar/${estudio.slug}`">{{ estudio.nombre }}</NuxtLink>
        </li>
      </ul>

      <p class="entrar__nota">
        El nombre de un estudio es público antes de iniciar sesión. No se puede ver nada
        más sin autenticarse.
      </p>
    </div>
  </main>
</template>

<style scoped>
.entrar {
  display: grid;
  place-items: center;
  min-height: 100vh;
  font-family: system-ui, sans-serif;
  background: #2b2a27;
  color: #f0efec;
}

.entrar__caja {
  background: #fff;
  color: #22211f;
  padding: 2rem 2.4rem;
  border-radius: 6px;
  width: min(28rem, 90vw);
}

.entrar h1 { font-size: 1.4rem; margin: 0; }
.entrar__sub { margin: 0.2rem 0 1.4rem; color: #7a756d; font-size: 0.85rem; }
.entrar__lista { list-style: none; padding: 0; margin: 0 0 1.2rem; }
.entrar__lista li { margin-bottom: 0.4rem; }

.entrar__lista a {
  display: block;
  padding: 0.7rem 0.9rem;
  border: 1px solid #d8d5cf;
  border-radius: 4px;
  text-decoration: none;
  color: #22211f;
}

.entrar__lista a:hover { background: #f7f6f3; }

.entrar__vacio { color: #7a756d; }
.entrar__nota { font-size: 0.78rem; color: #6a6a6a; margin: 0; }
</style>