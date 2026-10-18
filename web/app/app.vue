<template>
  <div class="aplicacion">
    <NuxtLayout>
      <NuxtPage />
    </NuxtLayout>
  </div>
</template>

<style>
/* Lo mínimo global. Cada pantalla trae sus propios estilos. */
* { box-sizing: border-box; }

body {
  margin: 0;
  background: #f7f6f3;
}

/*
  Aviso permanente, en todas las pantallas.
  El sistema organiza y vigila. No calcula plazos legales ni acredita que un
  plazo se haya cumplido. Estaba bien que el abogado que está mirando lo sepa
  antes de confiar en un color.
*/
.aviso-sistema {
  border: 1px solid #c9c4bb;
  background: #fffdf7;
  border-radius: 4px;
  padding: 0.7rem 0.9rem;
  font-size: 0.85rem;
  color: #4a4741;
  margin-bottom: 1rem;
}
</style>