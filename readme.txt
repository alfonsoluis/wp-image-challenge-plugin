=== Concursos ===
Contributors: alfonsoluis
Tags: concursos, retos, fotografía, votación, galería
Requires at least: 7.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.4.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Retos y concursos de fotografía, dibujo, diseño o cualquier arte: participantes, envíos, votación y resultados.

== Description ==

Concursos permite a asociaciones, escuelas y organizaciones crear retos artísticos en su sitio WordPress:

* Retos únicos o recurrentes (semanales, mensuales o anuales) con calendario de envíos, votación y resultados.
* Retos abiertos (las fotos se ven al enviarse) o cerrados (se ven al terminar los envíos).
* Uno o tres ganadores, con premio opcional para cada lugar.
* Inscripción pública de participantes con el shortcode `[concursos_inscripcion]`, con foto de perfil y redes sociales.
* Aprobación de participantes en bloque desde el panel; cada participante aprobado recibe por email un enlace personal para enviar sus fotos.
* Galería de fotos en la página de cada reto.
* Votación con email confirmado y resultados con empates.
* Avisos por email de nuevos retos, con baja en un clic.

El plugin está en desarrollo: la portada pública de retos, el perfil de participante y la recurrencia automática llegarán en próximas versiones.

== Installation ==

1. Descarga el ZIP del repositorio y súbelo en Plugins → Añadir nuevo → Subir plugin, o copia la carpeta en `wp-content/plugins/`.
2. Activa el plugin.
3. Crea un reto en Retos → Nuevo reto.
4. Crea una página con el shortcode `[concursos_inscripcion]` para que la gente se inscriba.

== Changelog ==

= 0.4.0 =
* Votación: registro de votantes con email confirmado, de 1 a 3 votos por reto y posibilidad de retirar un voto.
* Resultados en la página del reto: puestos con empates en igualdad de prominencia, premios y resto de fotos.
* Suscripción a avisos de nuevos retos, con baja desde el email o desde el reto.
* Casilla obligatoria de consentimiento en la inscripción de participantes.

= 0.3.0 =
* Envío de fotos desde el enlace personal: subir y borrar fotos durante el periodo de envíos, hasta el máximo del reto.
* Galería en la página de cada reto: en los retos abiertos se ve al momento; en los cerrados, al terminar los envíos.
* Lista de fotos enviadas en la administración y contadores de participantes y fotos en el panel.

= 0.2.0 =
* Inscripción pública de participantes, lista con filtros y acciones en bloque, emails de confirmación y aprobación.

= 0.1.0 =
* Retos: tipo de contenido, formulario de configuración y panel con estadísticas.
