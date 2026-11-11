"""Flujo de envío de fotos: enlace personal, límites, permisos y galería abierta/cerrada."""
import os
import base64
import http.cookiejar
import json
import re
import subprocess
import urllib.error
import urllib.parse
import urllib.request
import uuid

BASE = "http://localhost:8080"
MAILPIT = "http://localhost:8025"
PROJECT = os.path.abspath(os.path.join(os.path.dirname(__file__), "..", ".."))
PNG = base64.b64decode(
    "iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg=="
)
run_id = uuid.uuid4().hex[:6]


def session():
    return urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))


def fetch(op, url, data=None, headers=None):
    req = urllib.request.Request(url, data=data, headers=headers or {})
    try:
        r = op.open(req)
        return r.status, r.geturl(), r.read().decode()
    except urllib.error.HTTPError as e:
        return e.code, url, e.read().decode()


def multipart(fields, files):
    b = uuid.uuid4().hex
    parts = [f'--{b}\r\nContent-Disposition: form-data; name="{k}"\r\n\r\n{v}\r\n'.encode() for k, v in fields.items()]
    for k, (name, content, ctype) in files.items():
        parts.append(f'--{b}\r\nContent-Disposition: form-data; name="{k}"; filename="{name}"\r\nContent-Type: {ctype}\r\n\r\n'.encode() + content + b"\r\n")
    parts.append(f"--{b}--\r\n".encode())
    return b"".join(parts), {"Content-Type": f"multipart/form-data; boundary={b}"}


def wp(*args):
    out = subprocess.run(["docker", "compose", "run", "--rm", "-q", "wpcli", *args], cwd=PROJECT, capture_output=True, text=True)
    return out.stdout.strip()

# Necesita el reto de ejemplo «Luz de otoño» (ID 8) publicado: lo publica y al terminar lo deja como estaba.
import atexit
_estado8 = wp("post", "get", "8", "--field=post_status")
if _estado8 and _estado8 != "publish":
    wp("db", "query", "UPDATE wp_posts SET post_status='publish' WHERE ID=8"); wp("cache", "flush")
    atexit.register(lambda: (wp("db", "query", f"UPDATE wp_posts SET post_status='{_estado8}' WHERE ID=8"), wp("cache", "flush")))


def inscribir(nombre, email):
    op = session()
    url = BASE + "/inscripcion-retos/"
    page = fetch(op, url)[2]
    nonce = re.search(r'name="_wpnonce" value="([^"]+)"', page).group(1)
    reto = re.search(r'<option value="(\d+)"[^>]*>Reto Luz de oto', page).group(1)
    body, h = multipart({"action": "concursos_inscribir", "_wpnonce": nonce, "reto": reto, "nombre": nombre, "email": email,
                         "instagram": "", "facebook": "", "privacidad": "1"}, {"foto": ("", b"", "application/octet-stream")})
    h["Referer"] = url
    final = fetch(op, BASE + "/wp-admin/admin-post.php", body, h)[1]
    return reto, urllib.parse.parse_qs(urllib.parse.urlparse(final).query)["concursos_inscripcion"][0]


def link_para(email):
    op = urllib.request.build_opener()
    msgs = json.loads(fetch(op, MAILPIT + "/api/v1/messages")[2])["messages"]
    msg = next(m for m in msgs if m["Subject"].startswith("Ya puedes") and m["To"][0]["Address"] == email)
    text = json.loads(fetch(op, f"{MAILPIT}/api/v1/message/{msg['ID']}")[2])["Text"]
    return re.search(r"(http://localhost:8080/\?concursos_envio=[a-f0-9]{32})", text).group(1)


def resultado(url):
    return urllib.parse.parse_qs(urllib.parse.urlparse(url).query).get("resultado", ["?"])[0]


def page_nonce(link):
    return re.search(r'name="_wpnonce" value="([^"]+)"', fetch(session(), link)[2]).group(1)


def subir(link, nombre="foto.png", contenido=PNG, ctype="image/png", titulo="Atardecer"):
    body, h = multipart({"_wpnonce": page_nonce(link), "titulo": titulo}, {"foto": (nombre, contenido, ctype)})
    return resultado(fetch(session(), link, body, h)[1])


def eliminar(link, nonce, entrada):
    data = urllib.parse.urlencode({"_wpnonce": nonce, "eliminar": entrada}).encode()
    return resultado(fetch(session(), link, data)[1])


email_a, email_b = f"a{run_id}@example.com", f"b{run_id}@example.com"
reto, r1 = inscribir(f"Autora {run_id}", email_a)
_, r2 = inscribir(f"Autor {run_id}", email_b)
print("inscripciones:", r1, r2, "reto", reto)

admin = session()
fetch(admin, BASE + "/wp-login.php", urllib.parse.urlencode({"log": "admin", "pwd": "admin", "wp-submit": "Acceder", "testcookie": "1"}).encode())
lista = fetch(admin, BASE + "/wp-admin/edit.php?post_type=concurso_inscrito")[2]
filas = dict((n, i) for i, n in re.findall(r'<tr id="post-(\d+)".*?class="row-title"[^>]*>([^<]+)<', lista, re.S))
nonce = re.search(r'id="_wpnonce" name="_wpnonce" value="([^"]+)"', lista).group(1)
q = [("post_type", "concurso_inscrito"), ("action", "concursos_activar"), ("_wpnonce", nonce),
     ("_wp_http_referer", "/wp-admin/edit.php?post_type=concurso_inscrito"),
     ("post[]", filas[f"Autora {run_id}"]), ("post[]", filas[f"Autor {run_id}"])]
fetch(admin, BASE + "/wp-admin/edit.php?" + urllib.parse.urlencode(q))
link_a, link_b = link_para(email_a), link_para(email_b)

st, _, html = fetch(session(), link_a)
print("1. Página personal:", st, "| formulario:", 'name="foto"' in html, "| 0 de 2:", "(0 de 2)" in html)
print("2. Subir PNG:", subir(link_a))
print("3. Archivo PHP disfrazado:", subir(link_a, "x.php", b"<?php echo 1;", "image/png"))
print("4. Texto llamado .png:", subir(link_a, "x.png", b"no soy una imagen", "image/png"))
print("5. Segunda foto:", subir(link_a, titulo=""))
print("6. Tercera foto (máximo 2):", subir(link_a))

html = fetch(session(), link_a)[2]
ids = re.findall(r'name="eliminar" value="(\d+)"', html)
print("7. Página tras subir:", "(2 de 2)" in html, "| sin formulario:", 'name="foto"' not in html, "| título por defecto:", f"Foto de Autora {run_id}" in html, "| ids:", ids)

print("8. Otro participante borra foto ajena:", eliminar(link_b, page_nonce(link_b), ids[0]), "| sigue:", wp("post", "get", ids[0], "--field=post_status"))
print("9. Borrado con nonce falso:", eliminar(link_a, "abc123", ids[0]))
thumb = wp("post", "meta", "get", ids[1], "_thumbnail_id")
print("10. Borrar foto propia:", eliminar(link_a, page_nonce(link_a), ids[1]), "| adjunto borrado:", wp("post", "list", "--post_type=attachment", f"--post__in={thumb}", "--format=count"))

url_reto = wp("post", "url", reto)
print("11. Reto cerrado en envíos, galería oculta:", "concursos-galeria__rejilla" not in fetch(session(), url_reto)[2])
print("    Cuenta sin mostrar:", re.findall(r"Ya se ha[n]? enviado (\d+) foto", fetch(session(), url_reto)[2]))
wp("post", "meta", "update", reto, "_concursos_modo", "abierto")
html = fetch(session(), url_reto)[2]
print("12. Modo abierto, galería visible:", "concursos-galeria__rejilla" in html, "| autor:", f"Autora {run_id}" in html, "| sin email:", email_a not in html, "| sin token:", link_a.split("=")[1] not in html)
wp("post", "meta", "update", reto, "_concursos_modo", "cerrado")

fin = wp("post", "meta", "get", reto, "_concursos_fin_envios")
inicio = wp("post", "meta", "get", reto, "_concursos_inicio")
n_page = page_nonce(link_a)
wp("post", "meta", "update", reto, "_concursos_fin_envios", str(int(inicio) + 60))
print("13. Fuera de plazo, subir:", (lambda b_h: resultado(fetch(session(), link_a, b_h[0], b_h[1])[1]))(multipart({"_wpnonce": n_page, "titulo": "tarde"}, {"foto": ("f.png", PNG, "image/png")})))
html = fetch(session(), link_a)[2]
print("    Sin formulario ni borrar:", 'name="foto"' not in html and 'name="eliminar"' not in html, "| plazo cerrado:", "está cerrado" in html)
print("    Galería tras cerrar envíos:", "concursos-galeria__rejilla" in fetch(session(), url_reto)[2])
wp("post", "meta", "update", reto, "_concursos_fin_envios", fin)

panel = fetch(admin, BASE + "/wp-admin/edit.php?post_type=concurso_reto&page=concursos-panel")[2]
print("14. Admin fotos:", fetch(admin, BASE + "/wp-admin/edit.php?post_type=concurso_entrada")[0], "| fila título:", "Atardecer" in fetch(admin, BASE + "/wp-admin/edit.php?post_type=concurso_entrada")[2], "| panel enlaza fotos:", "post_type=concurso_entrada" in panel)
print("15. Enlace personal, cabeceras:", dict((k, v) for k, v in urllib.request.urlopen(link_a).headers.items() if k.lower() in ("referrer-policy", "x-robots-tag")))
