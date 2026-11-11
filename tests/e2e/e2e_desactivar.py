"""Desactivar un participante: sus fotos se ocultan y sus votos se borran."""
import os
import http.cookiejar
import json
import re
import subprocess
import sys
import urllib.error
import urllib.parse
import urllib.request
import uuid

BASE = "http://localhost:8080"
MAILPIT = "http://localhost:8025"
PROJECT = os.path.abspath(os.path.join(os.path.dirname(__file__), "..", ".."))
RETO_URL = BASE + "/retos/reto-de-prueba-luz-y-sombra/"
fallos = []


def check(nombre, ok, detalle=""):
    print(("OK   " if ok else "FALLO"), nombre, detalle)
    if not ok:
        fallos.append(nombre)


def session():
    return urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))


def fetch(op, url, data=None):
    try:
        r = op.open(urllib.request.Request(url, data=data))
        return r.status, r.geturl(), r.read().decode()
    except urllib.error.HTTPError as e:
        return e.code, url, e.read().decode()


def sh(*cmd):
    return subprocess.run(cmd, cwd=PROJECT, capture_output=True, text=True).stdout.strip()


def wp(*args):
    return sh("docker", "compose", "run", "--rm", "-q", "wpcli", *args)


def code(url):
    return urllib.parse.parse_qs(urllib.parse.urlparse(url).query).get("concursos_voto", ["?"])[0]


def votar(op, foto, nonce):
    data = {"action": "concursos_votar", "_wpnonce": nonce, "reto": RETO, "entrada": foto, "op": "votar", "puesto": 1}
    return code(fetch(op, BASE + "/wp-admin/admin-post.php", urllib.parse.urlencode(data).encode())[1])


def fotos_visibles():
    return fetch(session(), RETO_URL)[2].count("<figcaption>")


def votos_de(ids):
    lista = ",".join(ids)
    return int(wp("db", "query", f"SELECT COUNT(*) FROM wp_concursos_votos WHERE entrada_id IN ({lista})", "--skip-column-names"))


def bulk(accion, pid):
    lista = fetch(admin, BASE + "/wp-admin/edit.php?post_type=concurso_inscrito")[2]
    nonce = re.search(r'id="_wpnonce" name="_wpnonce" value="([^"]+)"', lista).group(1)
    q = [("post_type", "concurso_inscrito"), ("action", accion), ("_wpnonce", nonce),
         ("_wp_http_referer", "/wp-admin/edit.php?post_type=concurso_inscrito"), ("post[]", pid)]
    html = fetch(admin, BASE + "/wp-admin/edit.php?" + urllib.parse.urlencode(q))[2]
    m = re.search(r'notice-success is-dismissible"><p>([^<]+)', html)
    return m.group(1) if m else "(sin aviso)"


print(sh("make", "demo", "FASE=votacion").splitlines()[-1])
RETO = wp("post", "list", "--post_type=concurso_reto", "--name=reto-de-prueba-luz-y-sombra", "--field=ID")
pid = wp("post", "list", "--post_type=concurso_inscrito", "--title=Marta López", "--field=ID", "--meta_key=_concursos_reto", f"--meta_value={RETO}")
marta = wp("post", "list", "--post_type=concurso_entrada", "--meta_key=_concursos_participante", f"--meta_value={pid}", "--field=ID").split()
check("Marta tiene 2 fotos", len(marta) == 2, str(marta))
check("se ven 9 fotos", fotos_visibles() == 9)

admin = session()
fetch(admin, BASE + "/wp-login.php", urllib.parse.urlencode({"log": "admin", "pwd": "admin", "wp-submit": "Acceder", "testcookie": "1"}).encode())

v = session()
email = f"d{uuid.uuid4().hex[:6]}@example.com"
page = fetch(v, RETO_URL)[2]
nonce_reg = re.search(r'name="_wpnonce" value="([^"]+)"', page).group(1)
fetch(v, BASE + "/wp-admin/admin-post.php", urllib.parse.urlencode({"action": "concursos_votante_registro", "reto": RETO, "email": email, "_wpnonce": nonce_reg}).encode())
op = urllib.request.build_opener()
msgs = json.loads(fetch(op, MAILPIT + "/api/v1/messages?limit=200")[2])["messages"]
m = next(x for x in msgs if x["Subject"].startswith("Confirma") and x["To"][0]["Address"] == email)
link = re.search(r"http://localhost:8080/\?\S+", json.loads(fetch(op, f"{MAILPIT}/api/v1/message/{m['ID']}")[2])["Text"]).group(0)
html = fetch(v, link)[2]
nonce = re.search(r'name="action" value="concursos_votar"><input type="hidden" name="_wpnonce" value="([^"]+)"', html).group(1)

check("votar una foto de Marta", votar(v, marta[0], nonce) == "votado")
check("gasta un voto", "Te quedan 2 de 3" in fetch(v, RETO_URL)[2])
check("hay 1 voto en la tabla", votos_de(marta) == 1)

print("   ", bulk("concursos_desactivar", pid))
check("tras desactivar se ven 7 fotos", fotos_visibles() == 7, str(fotos_visibles()))
titulos = re.findall(r"<figcaption><strong>[^<]*</strong><span>([^<]*)", fetch(session(), RETO_URL)[2])
check("Marta ya no aparece", "Marta López" not in titulos, str(sorted(set(titulos))))
check("sus votos se borraron", votos_de(marta) == 0)
check("el votante recupera su voto", "Te quedan 3 de 3" in fetch(v, RETO_URL)[2])
check("no se puede votar su foto oculta", votar(v, marta[0], nonce) == "error")
check("las fotos siguen guardadas", all(wp("post", "get", i, "--field=post_status") == "publish" for i in marta))
check("su archivo sigue en medios", wp("post", "meta", "get", marta[0], "_thumbnail_id") != "")

print("   ", bulk("concursos_activar", pid))
check("al reactivar vuelven las 9 fotos", fotos_visibles() == 9, str(fotos_visibles()))
check("los votos no vuelven", votos_de(marta) == 0)
check("se puede volver a votar", votar(v, marta[1], nonce) == "votado" and votos_de(marta) == 1)

wp("eval", f"wp_trash_post({pid});")
check("en la papelera: fotos ocultas", fotos_visibles() == 7, str(fotos_visibles()))
check("en la papelera: votos borrados", votos_de(marta) == 0)
wp("eval", f"wp_untrash_post({pid});")
check("restaurado: fotos de vuelta", fotos_visibles() == 9, str(fotos_visibles()))

pending = wp("post", "list", "--post_type=concurso_inscrito", "--title=Javier Ruiz", "--field=ID", "--meta_key=_concursos_reto", f"--meta_value={RETO}")
print("   ", bulk("concursos_desactivar", pending))
print(sh("make", "demo", "FASE=finalizado").splitlines()[-1])
RETO = wp("post", "list", "--post_type=concurso_reto", "--name=reto-de-prueba-luz-y-sombra", "--field=ID")
pid2 = wp("post", "list", "--post_type=concurso_inscrito", "--title=Marta López", "--field=ID", "--meta_key=_concursos_reto", f"--meta_value={RETO}")
antes = fetch(session(), RETO_URL)[2]
check("resultados incluyen a Marta", "Marta López" in antes and "Empate en el primer lugar" in antes)
print("   ", bulk("concursos_desactivar", pid2))
despues = fetch(session(), RETO_URL)[2]
despues = despues[:despues.find("concursos-clasificacion")] if "concursos-clasificacion" in despues else despues
check("resultados sin Marta", "Marta López" not in despues and despues.count("<figcaption>") == 7, str(despues.count("<figcaption>")))
check("el puesto se recalcula", "Primer lugar" in despues or "Empate en el primer lugar" in despues)

print(sh("make", "demo", "FASE=votacion").splitlines()[-1])
print("\nFALLOS:", fallos if fallos else "ninguno")
sys.exit(1 if fallos else 0)
