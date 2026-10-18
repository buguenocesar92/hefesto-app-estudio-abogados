import { api } from '~/tipos/api'

// Estado de la sesión y del usuario.
//
// La interfaz NO decide permisos. Guardar un booleano `esAdministrador` acá
// sería una invitations a usarlo, y cualquier cambio en el rol se vería solo al
// recargar. Por eso se guarda el rol que dijo la API y cada pantalla vuelve a
// preguntar.

export const useSesion = () => {
  const usuario = useState<{ nombre: string; rol: string; rol_legible: string } | null>(
    'usuario',
    () => null,
  )

  const cargando = useState<boolean>('sesion-cargando', () => false)

  async function cargar(): Promise<void> {
    if (usuario.value !== null) {
      return
    }

    cargando.value = true

    try {
      usuario.value = await api.sesion.yo()
    } catch {
      // Sin sesión válida. La interfaz no adivina: vuelve a la pantalla de
      // ingreso.
      usuario.value = null
    } finally {
      cargando.value = false
    }
  }

  async function salir(): Promise<void> {
    await api.sesion.salir()
    usuario.value = null
    await navigateTo('/entrar')
  }

  return { usuario, cargando, cargar, salir }
}