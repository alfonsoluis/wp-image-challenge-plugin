# Concursos – plugin de WordPress

Retos y concursos de fotografía, dibujo, diseño o cualquier arte para el sitio
WordPress de una asociación, escuela u organización: participantes, envíos,
votación y resultados.

> En desarrollo. Ya funciona: crear retos, inscribir y aprobar participantes,
> enviar fotos, votar, ver resultados, portada pública en `/retos/` y perfil de
> participante, duplicar retos y diseño por reto.

**Requisitos:** WordPress 7.0 o posterior, PHP 7.4 o posterior.

## Instalar en tu sitio

1. Descarga el ZIP: botón **Code → Download ZIP** de este repositorio.
2. En tu WordPress: **Plugins → Añadir nuevo → Subir plugin**, elige el ZIP y actívalo.
3. Crea un reto en **Retos → Nuevo reto**.
4. Crea una página con el shortcode `[concursos_inscripcion]` para que la gente se inscriba.

El ZIP solo contiene el plugin: los archivos del entorno de desarrollo quedan fuera
(ver `.gitattributes`).

## Desarrollo local

Requisitos: Docker y `make`.

```bash
cp .env.example .env   # opcional: puertos y credenciales
make install           # levanta todo, instala WP (es_ES), activa el plugin y crea la página de inscripción
make up                # levantar
make down              # detener (conserva datos)
make reset             # borrar todo (BD y WordPress)
make wp c="plugin list"   # cualquier comando de wp-cli
make debug-log            # seguir wp-content/debug.log
make demo                 # reto de prueba abierto con participantes y fotos
make demo FASE=votacion   # el mismo reto en votación (o FASE=finalizado, con votos y resultados)
```

| Servicio    | URL                                      | Credenciales  |
|-------------|------------------------------------------|---------------|
| WordPress   | http://localhost:8080                    |               |
| Admin       | http://localhost:8080/wp-admin           | admin / admin |
| Inscripción | http://localhost:8080/inscripcion-retos/ |               |
| phpMyAdmin  | http://localhost:8081                    | (automático)  |
| Mailpit     | http://localhost:8025                    |               |

La raíz del repositorio es el plugin y está montada en
`wp-content/plugins/concursos`: los cambios se ven al recargar el navegador.

**Emails:** en local, WordPress no envía emails reales. Todos llegan a Mailpit
(http://localhost:8025) gracias a `docker/mu-plugins/mailpit.php`, que solo
existe en este entorno y no forma parte del plugin.

## Licencia

GPL-2.0 o posterior. Ver [LICENSE](LICENSE).
