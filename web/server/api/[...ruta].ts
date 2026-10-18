// Proxy hacia la API.
//
// TODO el tráfico pasa por acá en el servidor. Dos motivos:
//
// 1. La cookie de sesión nunca se expone al JavaScript del navegador. El
//    navegador habla con este servidor, y este servidor habla con la API.
//
// 2. La API nunca queda accesible desde el navegador. Un `fetch` directo del
//    cliente a la API expondría el contrato entero y permitiría saltarse
//    cualquier control que se ponga en el proxy.

export default defineEventHandler(async (event) => {
  const ruta = event.path.replace(/^\/api\/?/, '')

  if (ruta.length === 0) {
    throw createError({ statusCode: 404, statusMessage: 'Ruta no encontrada.' })
  }

  const base = process.env.NUXT_API_BASE || 'http://localhost:8000'

  const metodo = event.method

  const cuerpo = ['GET', 'HEAD'].includes(metodo)
    ? undefined
    : await readRawBody(event, 'utf8')

  const respuesta = await fetch(`${base}/api/v1/${ruta}`, {
    method: metodo,
    headers: {
      Accept: 'application/json',
      'Content-Type': 'application/json',
      'X-Requested-With': 'XMLHttpRequest',
      // Se reenvían las cookies del navegador para que la API resuelva la
      // sesión. En el sentido contrario, la API devuelve `Set-Cookie` para el
      // inicio de sesión.
      cookie: event.node.req.headers.cookie ?? '',
    },
    body: cuerpo && cuerpo.length > 0 ? cuerpo : undefined,
  })

  const texto = await respuesta.text()

  for (const nombre of ['set-cookie']) {
    const valor = respuesta.headers.get(nombre)
    if (valor) {
      setResponseHeader(event, nombre, valor)
    }
  }

  setResponseStatus(event, respuesta.status)

  return texto
})