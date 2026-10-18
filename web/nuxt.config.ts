import { defineNuxtConfig } from 'nuxt/config'

// Configuracion de la interfaz de estudios de abogados.
//
// SSR ACTIVADO
// ------------
// El renderizado del lado del servidor no es una decision estetica: el nombre de
// un cliente y el titulo de una causa son datos personales protegidos por la
// Ley 19.628. Con SSR, el HTML que se envia al navegador ya viene con la sesion
// aplicada y no queda una pantalla vacia que se rellene despues en una maquina
// del cliente.

export default defineNuxtConfig({
  compatibilityDate: '2025-01-15',

  ssr: true,

  devtools: { enabled: false },

  runtimeConfig: {
    // Solo el servidor. La cookie de sesion viaja por acá y nunca se expone al
    // cliente.
    sessionCookie: '',

    public: {
      baseApi: '',
    },
  },

  app: {
    head: {
      title: 'Gestión de Estudios de Abogados',
      htmlAttrs: { lang: 'es-CL' },
      meta: [
        { charset: 'utf-8' },
        { name: 'viewport', content: 'width=device-width, initial-scale=1' },
        {
          name: 'description',
          content:
            'Organiza expedientes, documentos y horas, y avisa cuando algo no encaja. No calcula plazos legales.',
        },
        // El nombre de un expediente es dato personal. No se indexa ni se
        // guarda en ningun cache compartido.
        { name: 'robots', content: 'noindex, nofollow, noarchive' },
      ],
    },
  },

  nitro: {
    // La interfaz nunca guarda datos de expedientes en el navegador de forma
    // persistente: el estado vive en la sesion del servidor.
    preset: 'node-server',
  },
})