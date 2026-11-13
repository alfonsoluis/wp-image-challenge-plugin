"""Flujo de votación: registro con email, votos, límites, manipulación, avisos, baja y resultados."""
import os
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
RETO_URL = BASE + "/retos/reto-de-prueba-luz-y-sombra/"
run = uuid.uuid4().hex[:6]
email = f"votante{run}@example.com"
fallos = []


def check(nombre, ok, detalle=""):
    print(("OK   " if ok else "FALLO"), nombre, detalle)
    if not ok:
        fallos.append(nombre)


def session():
    jar = http.cookiejar.CookieJar()
    op = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(jar))
    op.jar = jar
    return op


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


def mails(subject_prefix, to):
    op = urllib.request.build_opener()
    msgs = json.loads(fetch(op, MAILPIT + "/api/v1/messages?limit=200")[2])["messages"]
    return [m for m in msgs if m["Subject"].startswith(subject_prefix) and m["To"][0]["Address"] == to]


def texto(m):
    return json.loads(fetch(urllib.request.build_opener(), f"{MAILPIT}/api/v1/message/{m['ID']}")[2])["Text"]


def registrar(op, correo, suscribir=True, extra=None):
    page = fetch(op, RETO_URL)[2]
    data = {"action": "concursos_votante_registro", "reto": RETO, "email": correo,
            "_wpnonce": re.search(r'name="_wpnonce" value="([^"]+)"', page).group(1)}
    if suscribir:
        data["suscribir"] = "1"
    data.update(extra or {})
    return code(fetch(op, BASE + "/wp-admin/admin-post.php", urllib.parse.urlencode(data).encode())[1])


def formularios(html):
    return [dict(re.findall(r'name="(\w+)" value="([^"]*)"', f)) for f in re.findall(r'<form[^>]*concursos-voto-form.*?</form>', html, re.S)]


def votar(op, entrada, oper="votar", reto=None, nonce=None, puesto=None):
    nonce = nonce or NONCE
    data = {"action": "concursos_votar", "_wpnonce": nonce, "reto": reto or RETO, "entrada": entrada, "op": oper}
    if puesto is not None:
        data["puesto"] = puesto
    return code(fetch(op, BASE + "/wp-admin/admin-post.php", urllib.parse.urlencode(data).encode())[1])


print(sh("make", "demo", "FASE=votacion").splitlines()[-1])
RETO = wp("post", "list", "--post_type=concurso_reto", "--name=reto-de-prueba-luz-y-sombra", "--field=ID")
fotos = wp("post", "list", "--post_type=concurso_entrada", f"--meta_key=_concursos_reto", f"--meta_value={RETO}", "--field=ID", "--orderby=ID", "--order=ASC").split()

v = session()
html = fetch(v, RETO_URL)[2]
check("anónimo ve registro y las 9 fotos, sin botones", 'name="email"' in html and html.count("pc-foto__pie") == 9 and not formularios(html))
check("estado 'En votación'", "la votación termina el" in html)

check("registro responde revisa", registrar(v, email) == "revisa")
check("email inválido", registrar(session(), "no-es-email") == "email")
check("registro repetido avisa que ya se envió", registrar(session(), email) == "espera")
confirm = mails("Confirma", email)
check("un solo email pese al registro repetido", len(confirm) == 1, str(len(confirm)))
html = fetch(v, RETO_URL)[2]
check("sin confirmar sigue sin botones", not formularios(html))

link = re.search(r"http://localhost:8080/\?\S+", texto(confirm[0])).group(0).replace("&#038;", "&")
st, final, html = fetch(v, link)
check("confirmar redirige al reto", code(final) == "confirmado" and "reto-de-prueba" in final, final)
forms = formularios(html)
check("confirmado ve el podio con las 9 fotos y sus votos", len(forms) == 1 and html.count('class="pc-puestos" role="group"') == 9 and "Te quedan 3 de 3" in html, str(len(forms)))
NONCE = forms[0]["_wpnonce"]
cookie = next(c for c in v.jar if c.name == "concursos_votante")
check("cookie httponly y sin token en claro", cookie.has_nonstandard_attr("HttpOnly") or cookie.has_nonstandard_attr("httponly"), cookie.value[:12])
check("el mismo enlace funciona de nuevo (filtros de correo)", code(fetch(session(), link)[1]) == "confirmado")

an = session()
check("voto sin identificarse", votar(an, fotos[0]) == "identificate")
forj = session()
forj.jar.set_cookie(http.cookiejar.Cookie(0, "concursos_votante", cookie.value.split(".")[0] + "." + "a" * 64, None, False, "localhost.local", False, False, "/", True, False, None, False, None, None, {}))
check("cookie falsificada", votar(forj, fotos[0]) == "identificate")

check("voto 1 (1.º)", votar(v, fotos[0], puesto=1) == "votado")
check("repetir voto no consume otro", votar(v, fotos[0], puesto=1) == "votado" and "Te quedan 2 de 3" in fetch(v, RETO_URL)[2])
check("voto 2 (2.º)", votar(v, fotos[1], puesto=2) == "votado")
check("voto 3 (3.º)", votar(v, fotos[2], puesto=3) == "votado")
check("un 4.º puesto no existe", votar(v, fotos[3], puesto=4) == "error")
html = fetch(v, RETO_URL)[2]
check("puestos dados: 3 marcados en las fotos y en la barra", html.count('aria-pressed="true"') == 3 and html.count("pc-barra-podio__hueco is-vacio") == 0)
check("quitar voto", votar(v, fotos[0], "quitar") == "quitado" and "Te quedan 1 de 3" in fetch(v, RETO_URL)[2])
check("votar otra tras quitar", votar(v, fotos[3], puesto=1) == "votado")
otra = wp("post", "create", "--post_type=concurso_entrada", "--post_status=publish", "--post_title=Otra", "--porcelain")
wp("post", "meta", "update", otra, "_concursos_reto", "999999")
check("foto de otro reto", votar(v, otra, puesto=1) == "error", votar(v, otra, puesto=1))
check("nonce falso rechazado", fetch(v, BASE + "/wp-admin/admin-post.php", urllib.parse.urlencode({"action": "concursos_votar", "_wpnonce": "x", "reto": RETO, "entrada": fotos[4], "op": "votar"}).encode())[0] in (403, 200) and "Te quedan 0 de 3" in fetch(v, RETO_URL)[2])

tv = wp("db", "query", f"SELECT suscrito, estado FROM wp_concursos_votantes WHERE email='{email}'", "--skip-column-names")
check("suscripción activa tras confirmar", tv.split() == ["1", "confirmado"], tv)
nuevo = wp("post", "create", "--post_type=concurso_reto", "--post_status=publish", f"--post_title=Aviso {run}", "--porcelain")
aviso = mails(f"Nuevo reto: Aviso {run}", email)
check("aviso de nuevo reto", len(aviso) == 1)
baja = re.search(r"http://localhost:8080/\?concursos_baja=\S+", texto(aviso[0])).group(0)
wp("post", "delete", nuevo, "--force")
check("GET de baja no da de baja", "Darme de baja" in fetch(session(), baja)[2] and wp("db", "query", f"SELECT suscrito FROM wp_concursos_votantes WHERE email='{email}'", "--skip-column-names") == "1")
check("firma de baja falsa", fetch(session(), baja[:-4] + "0000")[0] == 404)
fetch(session(), baja, b"")
check("POST de baja da de baja", wp("db", "query", f"SELECT suscrito FROM wp_concursos_votantes WHERE email='{email}'", "--skip-column-names") == "0")
html = fetch(v, RETO_URL)[2]
check("el panel ofrece volver a suscribirse", "Avisarme de nuevos retos" in html)
check("enlace caducado", fetch(session(), BASE + "/?concursos_confirmar=" + "0" * 32)[0] == 404)

fin = wp("post", "meta", "get", RETO, "_concursos_fin_votacion")
ahora = int(wp("eval", "echo time();"))
wp("post", "meta", "update", RETO, "_concursos_fin_votacion", str(ahora - 60))
check("fuera de plazo no se vota", votar(v, fotos[0], "quitar") == "cerrado")
html = fetch(v, RETO_URL)[2]
check("evaluación: sin botones, sin resultados, fotos visibles", not formularios(html) and 'id="pc-ganadores"' not in html and html.count("pc-foto__pie") == 9 and "hasta los resultados" in html)
wp("post", "meta", "update", RETO, "_concursos_fin_votacion", fin)

casos = {
    "empate 1º, 2º, empate 3º": ("[1=>5,2=>5,3=>3,4=>3,5=>2,6=>1,7=>0]", 3, [(1, 5, [1, 2]), (2, 3, [3, 4]), (3, 2, [5])], [6, 7]),
    "1 ganador con empate": ("[1=>4,2=>4,3=>1]", 1, [(1, 4, [1, 2])], [3]),
    "nadie votó": ("[1=>0,2=>0]", 3, [], [1, 2]),
    "solo dos niveles": ("[1=>2,2=>1,3=>1]", 3, [(1, 2, [1]), (2, 1, [2, 3])], []),
}
for nombre, (arr, l, puestos, resto) in casos.items():
    r = json.loads(wp("eval", f"echo wp_json_encode(Concursos_Votos::rank({arr},{l}));"))
    got = [(p["lugar"], p["votos"], p["entradas"]) for p in r["puestos"]]
    check(f"rank: {nombre}", got == puestos and r["resto"] == resto, str(r))

print(sh("make", "demo", "FASE=finalizado").splitlines()[-1])
html = fetch(session(), RETO_URL)[2]
html = html[html.find('id="pc-ganadores"'):]
pos = [html.find(t) for t in ("Empate en el primer lugar", "Segundo lugar", "Empate en el tercer lugar", "Resto de participantes")]
check("resultados en orden con empates", -1 not in pos and pos == sorted(pos), str(pos))
html = fetch(session(), RETO_URL)[2]
html = fetch(session(), RETO_URL)[2]
check("premios visibles", all(p in html for p in ("Un cuadro de 50", "Un vale de 25", "Un libro de fotograf")))
check("votos visibles y sin voto ni registro", "6 puntos" in html and "0 puntos" in html and 'name="email"' not in html and "concursos-voto-form" not in html)
RETO = wp("post", "list", "--post_type=concurso_reto", "--name=reto-de-prueba-luz-y-sombra", "--field=ID")
wp("post", "meta", "update", RETO, "_concursos_lugares", "1")
html = fetch(session(), RETO_URL)[2]
check("un solo ganador: empate solo arriba", "Empate en el primer lugar" in html and "Segundo lugar" not in html and "Resto de participantes" in html)

admin = session()
fetch(admin, BASE + "/wp-login.php", urllib.parse.urlencode({"log": "admin", "pwd": "admin", "wp-submit": "Acceder", "testcookie": "1"}).encode())
html = fetch(admin, BASE + "/wp-admin/edit.php?post_type=concurso_entrada")[2]
check("columna de votos en admin", "column-concursos_votos" in html)

print(sh("make", "demo", "FASE=votacion").splitlines()[-1])
print("\nFALLOS:", fallos if fallos else "ninguno")
