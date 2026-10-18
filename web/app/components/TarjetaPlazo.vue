<script setup lang="ts">
// Tarjeta de un plazo.
//
// LO QUE ESTA TARJETA MUESTRA Y LO QUE NO
// ---------------------------------------
// Muestra la fecha que escribió el abogado, el texto de respaldo y el color que
// le puso la API.
//
// NO muestra un plazo legal, NO dice que se venció ante la ley y NO calcula
// nada. Cuando la API responde `sin_alerta`, el color es gris y el texto lo
// dice: "este plazo no se marca porque el estudio no configuró días de aviso".
//
// Un gris con explicación es información. Un verde con un número inventado es
// un error que alguien va a creer.

import type { Plazo } from '~/tipos/api'

defineProps<{ plazo: Plazo }>()
</script>

<template>
  <article class="plazo" :class="`plazo--${plazo.color}`">
    <header class="plazo__cabecera">
      <h3 class="plazo__titulo">{{ plazo.titulo }}</h3>
      <span class="plazo__color" :aria-label="plazo.resumen">{{ plazo.resumen }}</span>
    </header>

    <p class="plazo__fecha">
      <strong>Fecha registrada:</strong>
      <time :datetime="plazo.fecha_vencimiento">{{ plazo.fecha_vencimiento }}</time>
    </p>

    <p v-if="plazo.nivel === 'sin_alerta'" class="plazo__nota">
      Este plazo no se marca porque el estudio no configuró días de aviso. La fecha
      sigue aquí, escrita por el abogado.
    </p>

    <details class="plazo__respaldo">
      <summary>Ver el texto de respaldo</summary>
      <blockquote>{{ plazo.texto_respaldo }}</blockquote>
      <p class="plazo__inmutable">
        Este texto no se puede modificar. Es la razón por la que se escribió esa fecha.
      </p>
    </details>

    <footer class="plazo__pie">
      <span>{{ plazo.expediente }}</span>
      <span v-if="plazo.abogado">{{ plazo.abogado }}</span>
    </footer>
  </article>
</template>

<style scoped>
.plazo {
  border: 1px solid #d8d5cf;
  border-left-width: 5px;
  border-radius: 4px;
  padding: 0.9rem 1rem;
  background: #fff;
  margin-bottom: 0.75rem;
}

.plazo--rojo { border-left-color: #b3261e; }
.plazo--naranja { border-left-color: #c4690a; }
.plazo--amarillo { border-left-color: #b08b00; }
.plazo--verde { border-left-color: #2e6b3f; }
.plazo--gris { border-left-color: #8a8a8a; }

.plazo__cabecera {
  display: flex;
  justify-content: space-between;
  align-items: baseline;
  gap: 1rem;
}

.plazo__titulo { font-size: 1rem; margin: 0; }

.plazo__color { font-size: 0.85rem; color: #4a4a4a; }

.plazo__fecha { margin: 0.4rem 0; font-size: 0.9rem; }

.plazo__nota {
  font-size: 0.85rem;
  color: #5a5a5a;
  font-style: italic;
}

.plazo__respaldo blockquote {
  margin: 0.5rem 0;
  padding-left: 0.8rem;
  border-left: 3px solid #d8d5cf;
  font-size: 0.9rem;
}

.plazo__inmutable { font-size: 0.78rem; color: #6a6a6a; }

.plazo__pie {
  display: flex;
  gap: 1rem;
  font-size: 0.8rem;
  color: #6a6a6a;
  margin-top: 0.5rem;
}
</style>