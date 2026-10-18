// Carga de datos de plazos para la interfaz.
//
// LA ADVERTENCIA QUE ESTE ARCHIVO RESPETA
// ---------------------------------------
// La interfaz no calcula nada. Pide a la API los plazos con su nivel ya
// evaluado, y lo muestra. Si la API dice `sin_alerta`, la pantalla dice que no
// hay alerta configurada, y no lo disfraza de verde.

import { api, type Plazo } from '~/tipos/api'

export const usePlazos = () => {
  const plazos = useState<Plazo[]>('plazos', () => [])
  const cargando = useState<boolean>('plazos-cargando', () => false)

  const conAviso = computed(() => plazos.value.filter((plazo) => plazo.nivel !== 'sin_alerta'))

  const vencidos = computed(() => plazos.value.filter((plazo) => plazo.nivel === 'vencido'))

  const sinAlerta = computed(() => plazos.value.filter((plazo) => plazo.nivel === 'sin_alerta'))

  async function cargar(): Promise<void> {
    cargando.value = true

    try {
      const respuesta = await api.sesion
        .tablero()
        .plazos()

      plazos.value = respuesta.plazos
    } catch {
      plazos.value = []
    } finally {
      cargando.value = false
    }
  }

  return { plazos, conAviso, vencidos, sinAlerta, cargando, cargar }
}