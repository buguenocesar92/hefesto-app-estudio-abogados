// Middleware de sesión.
//
// Lo único que hace es preguntar a la API quién es la persona. NO decide
// permisos: la API ya filtró lo que esta persona puede ver, y volver a filtrar
// acá sería una segunda fuente de verdad que se desincroniza.

export default defineNuxtRouteMiddleware(async () => {
  const { usuario, cargar } = useSesion()

  await cargar()

  if (usuario.value === null) {
    return navigateTo('/entrar')
  }
})