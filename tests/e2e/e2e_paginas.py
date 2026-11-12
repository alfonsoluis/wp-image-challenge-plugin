"""Página de inscripción automática: se crea si no hay ninguna, no se duplica y no se recrea si se borra."""
import atexit
import os
import re
import subprocess
import sys
import urllib.error
import urllib.request

BASE = "http://localhost:8080"
PROJECT = os.path.abspath(os.path.join(os.path.dirname(__file__), "..", ".."))
fallos = []


def check(nombre, ok, detalle=""):
    print(("OK   " if ok else "FALLO"), nombre, detalle)
    if not ok:
        fallos.append(nombre)


def wp(*a):
    return subprocess.run(["docker", "compose", "run", "--rm", "-q", "wpcli", *a], cwd=PROJECT, capture_output=True, text=True).stdout.strip()


def get(url):
    try:
        r = urllib.request.urlopen(url)
        return r.status, r.read().decode()
    except urllib.error.HTTPError as e:
        return e.code, e.read().decode()


def con_shortcode():
    return wp("post", "list", "--post_type=page", "--post_status=publish", "--s=concursos_inscripcion", "--field=ID").split()


print(subprocess.run(["make", "demo", "FASE=envios"], cwd=PROJECT, capture_output=True, text=True).stdout.strip().splitlines()[-1])

# Estado de partida: el entorno ya tiene su página de inscripción.
opcion_antes = wp("option", "get", "concursos_pagina_inscripcion")
propias = con_shortcode()
check("hay una página de inscripción de partida", len(propias) >= 1, str(propias))
get(BASE + "/")
check("con una página existente no se crea otra", con_shortcode() == propias, str(con_shortcode()))
check("la opción la registra como existente", wp("option", "get", "concursos_pagina_inscripcion") in ("existente", opcion_antes), wp("option", "get", "concursos_pagina_inscripcion"))


def restaurar():
    for i in propias:
        wp("post", "update", i, "--post_status=publish")
    if opcion_antes:
        wp("option", "update", "concursos_pagina_inscripcion", opcion_antes)
    else:
        wp("option", "delete", "concursos_pagina_inscripcion")


atexit.register(restaurar)

# Sitio sin página de inscripción: el plugin la crea.
for i in propias:
    wp("post", "update", i, "--post_status=draft")
wp("option", "delete", "concursos_pagina_inscripcion")
get(BASE + "/")
nueva = wp("option", "get", "concursos_pagina_inscripcion")
check("crea la página y guarda su ID", nueva.isdigit(), nueva)
if nueva.isdigit():
    check("la página nueva lleva el shortcode", "[concursos_inscripcion]" in wp("post", "get", nueva, "--field=post_content"))
    check("título traducido", wp("post", "get", nueva, "--field=post_title") == "Inscripción a retos")
    url = wp("eval", f"echo get_permalink({nueva});")
    st, h = get(url)
    check("la página muestra el formulario", st == 200 and 'name="nombre"' in h and 'value="concursos_inscribir"' in h, str(st))
    st, h = get(BASE + "/retos/")
    check("la portada muestra «Inscribirme en un reto» hacia ella", re.search(r'<a class="[^"]*concursos-boton" href="' + re.escape(url), h) is not None)
    st, h = get(BASE + "/retos/reto-de-prueba-luz-y-sombra/")
    check("el reto muestra «Inscribirme en este reto»", "Inscribirme en este reto" in h and url in h)
    get(BASE + "/")
    check("no se crea una segunda", len(con_shortcode()) == 1, str(con_shortcode()))
    # Si se borra, no se vuelve a crear.
    wp("post", "delete", nueva, "--force")
    get(BASE + "/")
    check("borrada, no se recrea", con_shortcode() == [], str(con_shortcode()))
    st, h = get(BASE + "/retos/")
    check("sin página, no hay botón de inscripción", "Inscribirme en un reto" not in h)

restaurar()
check("restaurado", con_shortcode() == propias and wp("option", "get", "concursos_pagina_inscripcion") == (opcion_antes or ""), str(con_shortcode()))
print("\nFALLOS:", fallos if fallos else "ninguno")
sys.exit(1 if fallos else 0)
