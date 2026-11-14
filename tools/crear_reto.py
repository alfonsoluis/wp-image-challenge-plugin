#!/usr/bin/env python3
"""Crea (o actualiza) un reto por la REST API a partir de un archivo JSON.

Uso:
    export WP_URL="https://tu-sitio"
    export WP_USER="usuario"
    export WP_APP_PASSWORD="xxxx xxxx xxxx xxxx xxxx xxxx"
    python3 tools/crear_reto.py retos/colores-del-otono.json \\
        [--imagen fondo.jpg] [--emails socios.txt] [--publicar] [--dry-run]

- Por defecto el reto queda en borrador. Solo con --publicar se publica, y
  publicarlo envía el aviso por email a los suscritos (salvo "avisar": false).
- Si el JSON trae "slug" y ya existe un reto con ese slug, se actualiza ese
  reto en lugar de crear otro. Al actualizar no se cambia su estado, salvo
  para publicarlo con --publicar.
- --imagen sube la imagen a la biblioteca de medios y la pone como imagen
  destacada (el fondo del reto). Cada ejecución con --imagen la vuelve a subir.
- --emails lee un email por línea (se ignoran las líneas vacías y las que
  empiezan por #) y los pone en emails_participantes y emails_votantes.
- --dry-run muestra lo que se enviaría sin conectarse al sitio.

Solo usa la biblioteca estándar de Python 3. Las credenciales van en
variables de entorno: nunca en el repositorio.
"""

import argparse
import base64
import json
import mimetypes
import os
import re
import sys
import unicodedata
import urllib.error
import urllib.parse
import urllib.request

AGENTE = "crear-reto/1.0"
FECHAS = ("inicio", "fin_envios", "fin_votacion", "cierre")
CAMPOS = {
    "modo", "inicio", "fin_envios", "fin_votacion", "cierre", "votos_por_votante", "lugares",
    "max_fotos", "anonimo", "premios", "reglas", "privado_participantes", "emails_participantes",
    "privado_votantes", "emails_votantes", "avisar", "estilo", "acento", "tamano",
}
CLAVES_RETO = {"slug", "title", "content", "content_file", "concursos"}
FASES = {
    "proximo": "Próximo",
    "envios": "Recibiendo fotos",
    "votacion": "En votación",
    "evaluacion": "En evaluación",
    "finalizado": "Finalizado",
}
ETIQUETAS = {
    "inicio": "Inicio",
    "fin_envios": "Fin de envíos",
    "fin_votacion": "Fin de la votación",
    "cierre": "Resultados",
}


class ErrorApi(Exception):
    """Respuesta de error de la REST API."""

    def __init__(self, estado, codigo, mensaje):
        super().__init__(mensaje)
        self.estado = estado
        self.codigo = codigo
        self.mensaje = mensaje


def salir(mensaje):
    print(mensaje, file=sys.stderr)
    sys.exit(1)


def avisar(mensaje):
    print("Aviso: " + mensaje)


# --- Lectura del reto ---------------------------------------------------------

def leer_reto(ruta):
    try:
        with open(ruta, encoding="utf-8") as f:
            reto = json.load(f)
    except OSError as e:
        salir(f"No se puede leer {ruta}: {e.strerror}")
    except json.JSONDecodeError as e:
        salir(f"{ruta} no es un JSON válido (línea {e.lineno}, columna {e.colno}): {e.msg}")
    if not isinstance(reto, dict):
        salir(f"{ruta} debe contener un objeto JSON.")
    if not str(reto.get("title", "")).strip():
        salir(f"{ruta} no tiene «title».")

    for clave in sorted(set(reto) - CLAVES_RETO):
        avisar(f"«{clave}» no es un campo del reto y no se envía.")

    contenido = reto.get("content", "")
    if reto.get("content_file"):
        if "content" in reto:
            avisar("el JSON trae «content» y «content_file»: se usa «content_file».")
        archivo = os.path.join(os.path.dirname(os.path.abspath(ruta)), reto["content_file"])
        try:
            with open(archivo, encoding="utf-8") as f:
                contenido = f.read()
        except OSError as e:
            salir(f"No se puede leer el contenido {archivo}: {e.strerror}")

    concursos = reto.get("concursos", {})
    if not isinstance(concursos, dict):
        salir("«concursos» debe ser un objeto.")
    concursos = dict(concursos)
    if "fase" in concursos:
        avisar("«fase» es de solo lectura: se calcula a partir de las fechas y no se envía.")
        del concursos["fase"]
    for clave in sorted(set(concursos) - CAMPOS):
        avisar(f"concursos.{clave} no es un ajuste del plugin: la API lo ignora.")

    datos = {"title": reto["title"], "content": contenido}
    if reto.get("slug"):
        datos["slug"] = reto["slug"]
    return datos, concursos


def leer_emails(ruta):
    try:
        with open(ruta, encoding="utf-8") as f:
            lineas = [l.strip() for l in f]
    except OSError as e:
        salir(f"No se puede leer {ruta}: {e.strerror}")
    emails, vistos, malos = [], set(), []
    for linea in lineas:
        if not linea or linea.startswith("#"):
            continue
        if not re.fullmatch(r"[^@\s,;]+@[^@\s,;]+\.[^@\s,;]+", linea):
            malos.append(linea)
            continue
        if linea.lower() not in vistos:
            vistos.add(linea.lower())
            emails.append(linea)
    if malos:
        salir(f"{ruta} tiene {len(malos)} línea(s) que no son un email: " + ", ".join(malos))
    if not emails:
        salir(f"{ruta} no tiene ningún email.")
    return emails


def comprobar(concursos):
    """Avisos previos. Quien decide es la API: estos solo ayudan a verlo antes."""
    for clave in FECHAS:
        valor = concursos.get(clave)
        if valor is not None and not re.fullmatch(r"\d{4}-\d{2}-\d{2}T\d{2}:\d{2}", str(valor)):
            avisar(f"concursos.{clave} = {valor!r} no tiene el formato AAAA-MM-DDTHH:MM.")
    fechas = [(c, concursos.get(c)) for c in FECHAS if concursos.get(c)]
    for (c1, f1), (c2, f2) in zip(fechas, fechas[1:]):
        if str(f2) < str(f1):
            avisar(f"«{ETIQUETAS[c2]}» ({f2}) es anterior a «{ETIQUETAS[c1]}» ({f1}).")
    for grupo in ("participantes", "votantes"):
        if concursos.get(f"privado_{grupo}") and not str(concursos.get(f"emails_{grupo}", "")).strip():
            avisar(f"privado_{grupo} está activo pero emails_{grupo} está vacío: la API lo rechazará (usa --emails).")


# --- REST API -----------------------------------------------------------------

class Api:
    def __init__(self, url, usuario, clave):
        self.base = url.rstrip("/") + "/wp-json/wp/v2/"
        self.auth = "Basic " + base64.b64encode(f"{usuario}:{clave}".encode()).decode()

    def pedir(self, metodo, ruta, datos=None, cuerpo=None, cabeceras=None):
        h = {"Authorization": self.auth, "User-Agent": AGENTE, "Accept": "application/json"}
        if datos is not None:
            cuerpo = json.dumps(datos).encode()
            h["Content-Type"] = "application/json"
        h.update(cabeceras or {})
        peticion = urllib.request.Request(self.base + ruta, data=cuerpo, method=metodo, headers=h)
        try:
            with urllib.request.urlopen(peticion, timeout=60) as r:
                return json.loads(r.read() or b"null")
        except urllib.error.HTTPError as e:
            texto = e.read().decode("utf-8", "replace")
            try:
                error = json.loads(texto)
            except json.JSONDecodeError:
                error = {}
            if not isinstance(error, dict):
                error = {}
            raise ErrorApi(e.code, error.get("code", ""), error.get("message") or texto[:300] or e.reason)
        except urllib.error.URLError as e:
            salir(f"No se puede conectar con {self.base}: {e.reason}")

    def buscar(self, slug):
        """Reto con ese slug, incluidos borradores y archivados."""
        for estado in ("any", "concurso_archivado"):
            consulta = urllib.parse.urlencode({"slug": slug, "status": estado, "context": "edit", "_fields": "id,status"})
            try:
                retos = self.pedir("GET", "concurso_reto?" + consulta)
            except ErrorApi as e:
                if estado == "any":
                    raise
                retos = []  # El sitio no tiene el estado «archivado».
            if retos:
                return retos[0]
        return None

    def subir_imagen(self, ruta):
        nombre = os.path.basename(ruta)
        tipo = mimetypes.guess_type(nombre)[0] or "application/octet-stream"
        with open(ruta, "rb") as f:
            cuerpo = f.read()
        # WordPress solo entiende filename="…": el nombre va sin acentos ni espacios.
        ascii_ = unicodedata.normalize("NFKD", nombre).encode("ascii", "ignore").decode()
        seguro = re.sub(r"[^A-Za-z0-9._-]+", "-", ascii_).strip("-") or "imagen"
        media = self.pedir("POST", "media", cuerpo=cuerpo, cabeceras={
            "Content-Type": tipo,
            "Content-Disposition": f'attachment; filename="{seguro}"',
        })
        return media["id"]


def resumen_envio(datos, concursos, origen_emails):
    """El JSON que se envía, con el contenido acortado y sin las listas de emails."""
    copia = dict(datos)
    if len(copia.get("content", "")) > 160:
        copia["content"] = copia["content"][:160] + f"… ({len(datos['content'])} caracteres)"
    ajustes = dict(concursos)
    for grupo in ("participantes", "votantes"):
        clave = f"emails_{grupo}"
        if ajustes.get(clave):
            n = len([e for e in re.split(r"[\s,;]+", ajustes[clave]) if e])
            ajustes[clave] = f"<{n} emails{' de ' + origen_emails if origen_emails else ''}>"
    copia["concursos"] = ajustes
    return json.dumps(copia, ensure_ascii=False, indent=2)


def mostrar(reto, url):
    c = reto.get("concursos") or {}

    def linea(etiqueta, valor):
        print(f"  {etiqueta + ':':<20} {valor}")

    print()
    print(f"Reto #{reto['id']}: {reto['title']['raw']}")
    linea("Estado", reto["status"])
    linea("Fase", FASES.get(c.get("fase"), c.get("fase", "?")))
    for clave in FECHAS:
        linea(ETIQUETAS[clave], c.get(clave) or "(sin fecha)")
    linea("Votos", f"{c.get('votos_por_votante')} por votante, {c.get('lugares')} lugar(es), {c.get('max_fotos')} foto(s) por participante")
    for grupo in ("participantes", "votantes"):
        if c.get(f"privado_{grupo}"):
            n = len([e for e in re.split(r"[\s,;]+", c.get(f"emails_{grupo}", "")) if e])
            linea(grupo.capitalize(), f"privado, {n} emails")
        else:
            linea(grupo.capitalize(), "abierto")
    if "avisar" in c:
        linea("Aviso", "se avisa a los suscritos al publicar" if c["avisar"] else "sin aviso por email")
    linea("Imagen", f"#{reto['featured_media']}" if reto.get("featured_media") else "sin imagen de fondo")
    linea("Ver", reto["link"])
    linea("Editar", f"{url.rstrip('/')}/wp-admin/post.php?post={reto['id']}&action=edit")


def main():
    sys.stdout.reconfigure(line_buffering=True)
    p = argparse.ArgumentParser(description="Crea o actualiza un reto por la REST API a partir de un JSON.")
    p.add_argument("json", help="archivo JSON del reto")
    p.add_argument("--imagen", help="imagen de fondo: se sube y se usa como imagen destacada")
    p.add_argument("--emails", help="archivo con un email por línea para las listas privadas")
    p.add_argument("--publicar", action="store_true", help="publica el reto (envía el aviso a los suscritos)")
    p.add_argument("--dry-run", action="store_true", help="muestra lo que se enviaría sin llamar a la API")
    args = p.parse_args()

    datos, concursos = leer_reto(args.json)
    origen_emails = None
    if args.emails:
        emails = leer_emails(args.emails)
        concursos["emails_participantes"] = ", ".join(emails)
        concursos["emails_votantes"] = ", ".join(emails)
        origen_emails = os.path.basename(args.emails)
        print(f"{len(emails)} emails de {origen_emails} para participantes y votantes.")
    if args.imagen and not os.path.isfile(args.imagen):
        salir(f"No existe la imagen {args.imagen}.")
    comprobar(concursos)

    if args.dry_run:
        print("\n--dry-run: no se llama a la API. Se haría esto:")
        pasos = []
        if "slug" in datos:
            pasos.append(f"Buscar un reto con el slug «{datos['slug']}» (GET /wp/v2/concurso_reto?slug=…&status=any).")
        else:
            pasos.append("Sin slug: se crea siempre un reto nuevo.")
        if args.imagen:
            pasos.append(f"Subir {args.imagen} ({os.path.getsize(args.imagen)} bytes) a /wp/v2/media y usarla como featured_media.")
        pasos.append("Si no existe, crearlo en borrador (POST /wp/v2/concurso_reto); si existe, actualizarlo sin cambiar su estado.\n"
                     "   Después guardar sus ajustes (POST /wp/v2/concurso_reto/ID con «concursos»):\n" + resumen_envio(datos, concursos, origen_emails))
        pasos.append("Publicarlo (POST {\"status\": \"publish\"}): " + ("envía el aviso a los suscritos." if concursos.get("avisar", True) else "sin aviso, porque «avisar» es false.") if args.publicar else "Dejarlo como está (borrador si es nuevo): sin --publicar no se publica.")
        for n, paso in enumerate(pasos, 1):
            print(f"{n}. {paso}")
        return

    url, usuario, clave = (os.environ.get(v, "").strip() for v in ("WP_URL", "WP_USER", "WP_APP_PASSWORD"))
    faltan = [v for v, x in (("WP_URL", url), ("WP_USER", usuario), ("WP_APP_PASSWORD", clave)) if not x]
    if faltan:
        salir("Faltan variables de entorno: " + ", ".join(faltan))
    host = urllib.parse.urlparse(url).hostname or ""
    if url.startswith("http://") and host not in ("localhost", "127.0.0.1"):
        avisar("WP_URL usa http: la contraseña de aplicación viaja sin cifrar.")
    api = Api(url, usuario, clave)

    try:
        existente = api.buscar(datos["slug"]) if "slug" in datos else None
        if args.imagen:
            datos["featured_media"] = api.subir_imagen(args.imagen)
            print(f"Imagen subida: #{datos['featured_media']}.")

        if existente:
            reto_id = existente["id"]
            api.pedir("POST", f"concurso_reto/{reto_id}", datos)
            print(f"Actualizado el reto #{reto_id} (slug «{datos['slug']}», estado {existente['status']}).")
            nuevo = False
        else:
            reto_id = api.pedir("POST", "concurso_reto", dict(datos, status="draft"))["id"]
            print(f"Creado el borrador #{reto_id}.")
            nuevo = True

        try:
            reto = api.pedir("POST", f"concurso_reto/{reto_id}?context=edit", {"concursos": concursos})
        except ErrorApi as e:
            if e.codigo == "concursos_ajustes_invalidos":
                print("\nLa API rechazó los ajustes del reto:", file=sys.stderr)
                print(e.mensaje, file=sys.stderr)
            else:
                print(f"\nError {e.estado} {e.codigo}: {e.mensaje}", file=sys.stderr)
            if nuevo:
                siguiente = "vuelve a ejecutar el script: actualizará ese mismo borrador." if "slug" in datos else "bórralo desde el admin antes de volver a ejecutar el script."
                print(f"El borrador #{reto_id} se creó con los ajustes por defecto. Corrige el JSON y {siguiente}", file=sys.stderr)
            sys.exit(1)

        if "avisar" not in (reto.get("concursos") or {}):
            avisar("el sitio no reconoce «avisar»: tiene una versión del plugin anterior a este cambio, que avisaría a TODOS los suscritos al publicar, aunque el reto sea privado.")
            if args.publicar:
                salir("No se publica. Actualiza el plugin en el sitio y vuelve a ejecutar el script con --publicar.")

        if args.publicar and reto["status"] != "publish":
            reto = api.pedir("POST", f"concurso_reto/{reto_id}?context=edit", {"status": "publish"})
            print("Publicado." + (" Se ha enviado el aviso a los suscritos que correspondan." if reto["concursos"].get("avisar") else " Sin aviso por email."))

        reto = api.pedir("GET", f"concurso_reto/{reto_id}?context=edit")
    except ErrorApi as e:
        if e.estado == 401:
            salir(f"Error 401 {e.codigo}: {e.mensaje}\nRevisa WP_USER y WP_APP_PASSWORD (contraseña de aplicación, no la normal).")
        if e.codigo == "concursos_ajustes_invalidos":
            salir("La API rechazó los ajustes del reto:\n" + e.mensaje)
        salir(f"Error {e.estado} {e.codigo}: {e.mensaje}")

    mostrar(reto, url)
    guardadas = reto.get("concursos") or {}
    for clave in FECHAS:
        if clave in concursos and concursos[clave] != guardadas.get(clave):
            avisar(f"{clave}: se envió {concursos[clave]} y se guardó {guardadas.get(clave) or 'vacío'}.")


if __name__ == "__main__":
    main()
