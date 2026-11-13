"""Quien participa queda registrado como votante al abrir su enlace personal, y puede votar desde ese navegador."""
import http.cookiejar
import json
import os
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
    print("OK   " if ok else "FALLO", nombre, detalle)
    if not ok:
        fallos.append(nombre)


def sh(*cmd):
    return subprocess.run(cmd, cwd=PROJECT, capture_output=True, text=True).stdout.strip()


def wp(*args):
    return sh("docker", "compose", "run", "--rm", "-q", "wpcli", *args)


def session():
    jar = http.cookiejar.CookieJar()
    op = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(jar))
    op.jar = jar
    return op


def fetch(op, url):
    try:
        r = op.open(url)
        return r.status, r.read().decode()
    except urllib.error.HTTPError as e:
        return e.code, e.read().decode()


def votar(op, nonce, entrada, puesto):
    data = urllib.parse.urlencode({"action": "concursos_votar", "_wpnonce": nonce, "reto": reto, "entrada": entrada, "op": "votar", "puesto": puesto}).encode()
    r = op.open(urllib.request.Request(BASE + "/wp-admin/admin-post.php", data=data))
    return urllib.parse.parse_qs(urllib.parse.urlparse(r.geturl()).query).get("concursos_voto", ["?"])[0]


def votante(email):
    return wp("db", "query", f"SELECT estado, suscrito FROM wp_concursos_votantes WHERE email='{email}'", "--skip-column-names").split()


print(sh("make", "demo", "FASE=votacion").splitlines()[-1])
reto = wp("post", "list", "--post_type=concurso_reto", "--name=reto-de-prueba-luz-y-sombra", "--field=ID")
# Un participante con fotos en el reto: el dueño de la primera.
propias = wp("eval", f"$f = Concursos_Entrada::find( array( 'reto' => {reto} ) ); echo Concursos_Entrada::owner( $f[0]->ID ) . ' ' . implode( ',', wp_list_pluck( Concursos_Entrada::find( array( 'reto' => {reto}, 'participante' => Concursos_Entrada::owner( $f[0]->ID ) ) ), 'ID' ) );").split()
pid, propias = propias[0], propias[1].split(",")
otra = wp("eval", f"foreach ( Concursos_Entrada::find( array( 'reto' => {reto} ) ) as $e ) {{ if ( ! in_array( (string) $e->ID, explode( ',', '{','.join(propias)}' ), true ) ) {{ echo $e->ID; break; }} }}")

# Un email nuevo para el participante, y otro que ya era votante pendiente.
email = f"p{uuid.uuid4().hex[:6]}@example.com"
wp("post", "meta", "update", pid, "_concursos_email", email)
check("antes no es votante", votante(email) == [])

link = BASE + "/?concursos_envio=" + wp("eval", f"echo Concursos_Participante::issue_token({pid});")
op = session()
st, h = fetch(op, link)
check("el enlace personal abre", st == 200, str(st))
check("avisa de que también puede votar", "también puedes votar en este reto" in h)
check("queda como votante confirmado y sin avisos", votante(email) == ["confirmado", "0"], str(votante(email)))
check("deja la cookie de votante", any(c.name == "concursos_votante" for c in op.jar))
_, h = fetch(op, RETO_URL)
check("en el reto ve los botones de votar", "Te quedan 3 de 3" in h and "concursos-voto-form" in h)
check("en otro navegador no", "concursos-voto-form" not in fetch(session(), RETO_URL)[1])

# No puede votar su propia foto: sin botones en ella, y el voto directo se rechaza.
check("en sus fotos pone «Tu foto» y no hay botones", h.count("concursos-propia") == len(propias) and all(f'value="{e}:1"' not in h for e in propias), str(propias))
nonce = re.search(r'value="concursos_votar"><input type="hidden" name="_wpnonce" value="([^"]+)"', h).group(1)
check("votar su propia foto se rechaza", votar(op, nonce, propias[0], 1) == "propia")
check("votar otra foto sí", votar(op, nonce, otra, 1) == "votado")
data = urllib.parse.urlencode({"action": "concursos_votar", "_wpnonce": nonce, "reto": reto, "op": "podio", "puestos[1]": otra, "puestos[2]": propias[0]}).encode()
r = op.open(urllib.request.Request(BASE + "/wp-admin/admin-post.php", data=data))
check("en el podio tampoco puede ir su foto", urllib.parse.parse_qs(urllib.parse.urlparse(r.geturl()).query).get("concursos_voto", ["?"])[0] == "propia")

# Abrir otra vez el enlace no duplica el votante.
fetch(session(), link)
check("sin votantes duplicados", wp("db", "query", f"SELECT COUNT(*) FROM wp_concursos_votantes WHERE email='{email}'", "--skip-column-names") == "1")

# Un votante que se registró pero no confirmó queda confirmado al abrir su enlace, sin perder su suscripción.
email2 = f"q{uuid.uuid4().hex[:6]}@example.com"
wp("db", "query", f"INSERT INTO wp_concursos_votantes (email, estado, suscrito, creado) VALUES ('{email2}', 'pendiente', 0, UNIX_TIMESTAMP())")
wp("post", "meta", "update", pid, "_concursos_email", email2)
fetch(session(), BASE + "/?concursos_envio=" + wp("eval", f"echo Concursos_Participante::issue_token({pid});"))
check("un votante pendiente queda confirmado", votante(email2) == ["confirmado", "0"], str(votante(email2)))

# Con la votación privada y el email fuera de la lista, no se le anuncia que puede votar.
wp("post", "meta", "update", reto, "_concursos_privado_votantes", "1")
wp("post", "meta", "update", reto, "_concursos_emails_votantes", "otra@example.com")
_, h = fetch(session(), BASE + "/?concursos_envio=" + wp("eval", f"echo Concursos_Participante::issue_token({pid});"))
check("votación privada: sin el aviso", "también puedes votar" not in h)
wp("post", "meta", "update", reto, "_concursos_privado_votantes", "0")

# El email de aprobación lo menciona.
wp("eval", f"Concursos_Inscripcion::email_aprobacion({pid});")
msgs = json.loads(fetch(urllib.request.build_opener(), MAILPIT + "/api/v1/messages")[1])["messages"]
m = next((x for x in msgs if x["To"][0]["Address"] == email2 and x["Subject"].startswith("Ya puedes")), None)
texto = json.loads(fetch(urllib.request.build_opener(), f"{MAILPIT}/api/v1/message/{m['ID']}")[1])["Text"] if m else ""
check("el email de aprobación dice que también puede votar", "también podrás votar" in texto)

print(sh("make", "demo", "FASE=votacion").splitlines()[-1])
print("\nFALLOS:", fallos if fallos else "ninguno")
sys.exit(1 if fallos else 0)
