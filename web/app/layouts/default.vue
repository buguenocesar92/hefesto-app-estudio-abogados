<script setup lang="ts">
const { usuario, salir } = useSesion()
const ruta = useRoute()

// La barra lateral muestra el rol que dijo la API, no un permiso inventado acá.
</script>

<template>
  <div class="caja">
    <aside class="lateral">
      <div class="lateral__marca">
        <strong>Estudios de Abogados</strong>
        <span>Hefesto Studio</span>
      </div>

      <nav class="lateral__menu">
        <NuxtLink to="/" :class="{ activo: ruta.path === '/' }">Tablero</NuxtLink>
        <NuxtLink to="/expedientes" :class="{ activo: ruta.path.startsWith('/expedientes') }">
          Expedientes
        </NuxtLink>
        <NuxtLink to="/plazos" :class="{ activo: ruta.path.startsWith('/plazos') }">Plazos</NuxtLink>
      </nav>

      <div v-if="usuario" class="lateral__usuario">
        <span class="lateral__nombre">{{ usuario.nombre }}</span>
        <span class="lateral__rol">{{ usuario.rol_legible }}</span>
        <button type="button" @click="salir">Salir</button>
      </div>
    </aside>

    <main class="contenido">
      <slot />
    </main>
  </div>
</template>

<style scoped>
.caja {
  display: grid;
  grid-template-columns: 15rem 1fr;
  min-height: 100vh;
  font-family: system-ui, -apple-system, "Segoe UI", sans-serif;
  color: #22211f;
}

.lateral {
  background: #2b2a27;
  color: #f0efec;
  padding: 1.2rem 1rem;
  display: flex;
  flex-direction: column;
  gap: 1.5rem;
}

.lateral__marca { display: flex; flex-direction: column; }
.lateral__marca span { font-size: 0.75rem; color: #a8a49c; }

.lateral__menu { display: flex; flex-direction: column; gap: 0.2rem; }

.lateral__menu a {
  color: #d6d3cc;
  text-decoration: none;
  padding: 0.45rem 0.6rem;
  border-radius: 4px;
  font-size: 0.92rem;
}

.lateral__menu a.activo { background: #3d3c37; color: #fff; }

.lateral__usuario {
  margin-top: auto;
  display: flex;
  flex-direction: column;
  gap: 0.3rem;
  font-size: 0.85rem;
}

.lateral__rol { color: #a8a49c; font-size: 0.78rem; }

.lateral__usuario button {
  margin-top: 0.5rem;
  background: none;
  border: 1px solid #56544e;
  color: #d6d3cc;
  border-radius: 4px;
  padding: 0.35rem;
  cursor: pointer;
  font-size: 0.82rem;
}

.contenido { padding: 1.6rem 2rem; background: #f7f6f3; }
</style>