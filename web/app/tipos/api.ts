// Cliente de la API.
//
// TODA la lógica de autorización vive en la API, no acá.
// Esta interfaz reenvía la cookie de sesión y muestra lo que le devuelven. No
// decide ni filtra nada: un usuario sin permisos recibe lo que la API le
// devuelve, y la API ya no se lo devuelve.

export interface Respuesta<T> {
  datos: T
}

const BASE = process.env.NUXT_API_BASE || 'http://localhost:8000'

const NOMBRE_COOKIE = process.env.NUXT_SESSION_COOKIE || 'estudio_session'

function leerCookie(nombre: string): string {
  if (typeof document === 'undefined') {
    return ''
  }

  const trozo = document.cookie.split('; ').find((fila) => fila.startsWith(nombre + '='))

  return trozo ? decodeURIComponent(trozo.split('=').slice(1).join('=')) : ''
}

async function pedir<T>(ruta: string, opciones: RequestInit = {}): Promise<T> {
  const respuesta = await fetch(`${BASE}/api/v1${ruta}`, {
    ...opciones,
    headers: {
      Accept: 'application/json',
      'X-Requested-With': 'XMLHttpRequest',
      ...(opciones.headers ?? {}),
    },
  })

  if (!respuesta.ok) {
    // Un 401 o un 403 acá significa que la sesión no sirve. No se reintenta a
    // ciegas: se propaga el error para que la interfaz vuelva a pedir el
    // ingreso.
    throw new Error(`La API respondió ${respuesta.status}`)
  }

  return (await respuesta.json()) as T
}

export const api = {
  sesion: {
    yo: () => pedir<{ nombre: string; rol: string; rol_legible: string }>('/auth/yo'),
    estudios: () => pedir<{ estudios: Array<{ slug: string; nombre: string }> }>('/auth/estudios'),
    salir: () => pedir<{ ok: boolean }>('/auth/salir', { method: 'POST' }),
  },

  tablero: {
    indice: () => pedir<{ fecha_referencia: string; advertencia: string; alertas: number }>('/tablero'),
    plazos: () =>
      pedir<{
        fecha_referencia: string
        plazos: Plazo[]
      }>('/plazos'),
  },

  expedientes: {
    lista: () => pedir<{ data: Expediente[] }>('/expedientes'),
    uno: (id: string) => pedir<Expediente>(`/expedientes/${id}`),
  },

  nombreCookie: NOMBRE_COOKIE,
  valorCookie: leerCookie,
}

export interface Plazo {
  id: string
  titulo: string
  fecha_vencimiento: string
  texto_respaldo: string
  estado: string
  nivel: 'sin_alerta' | 'vencido' | 'critico' | 'proximo' | 'en_tiempo'
  color: string
  resumen: string
  expediente: string
  abogado: string | null
}

export interface Expediente {
  folio: string
  numero_causa: string | null
  titulo: string
  materia?: string
  abogado?: string
  estado?: string
}