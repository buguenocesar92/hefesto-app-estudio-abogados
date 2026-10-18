<script setup lang="ts">
// Aviso de una incidencia documental.
//
// Una incidencia responde "¿por qué este documento no quedó guardado?". Es la
// pregunta más incómoda de un estudio, y por eso la pantalla la muestra entera:
// el tipo, la severidad y la descripción que escribió el sistema.

defineProps<{
  tipo: string
  severidad: string
  descripcion: string
  revisada: boolean
}>()

const ETIQUETAS: Record<string, string> = {
  colision_version: 'Colisión de versión',
  duplicado_exacto: 'Duplicado exacto',
  duplicado_otro_expediente: 'Ya estaba en otro expediente',
}

const etiqueta = (tipo: string): string => ETIQUETAS[tipo] ?? tipo
</script>

<template>
  <article class="incidencia" :class="`incidencia--${severidad}`">
    <header>
      <strong>{{ etiqueta(tipo) }}</strong>
      <span class="incidencia__estado">{{ revisada ? 'Revisada' : 'Sin revisar' }}</span>
    </header>

    <p class="incidencia__texto">{{ descripcion }}</p>

    <p v-if="tipo === 'colision_version'" class="incidencia__alerta">
      Hay dos copias distintas del mismo documento y el sistema no sabe cuál es la
      buena. <strong>No se sobrescribió nada.</strong> Revise el archivador físico
      y decida cuál corresponde.
    </p>
  </article>
</template>

<style scoped>
.incidencia {
  border: 1px solid #d8d5cf;
  border-radius: 4px;
  padding: 0.8rem 1rem;
  background: #fbfaf7;
  margin-bottom: 0.75rem;
}

.incidencia--alta { border-left: 5px solid #b3261e; }
.incidencia--media { border-left: 5px solid #c4690a; }
.incidencia--baja { border-left: 5px solid #8a8a8a; }

.incidencia header {
  display: flex;
  justify-content: space-between;
  gap: 1rem;
}

.incidencia__estado { font-size: 0.8rem; color: #6a6a6a; }
.incidencia__texto { font-size: 0.9rem; }

.incidencia__alerta {
  font-size: 0.88rem;
  background: #fff;
  border: 1px dashed #b3261e;
  padding: 0.5rem 0.7rem;
}
</style>