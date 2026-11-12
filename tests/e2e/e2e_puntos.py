"""Votación por puestos (3, 2 y 1 puntos), votación anónima, perfiles activables y nombres de archivo aleatorios."""
import base64
import html as H
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
    print(("OK   " if ok else "FALLO"), nombre, detalle)
    if not ok:
        fallos.append(nombre)


def session():
    return urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))


def fetch(op, url, data=None, headers=None):
    try:
        r = op.open(urllib.request.Request(url, data=data, headers=headers or {}))
        return r.status, r.geturl(), r.read().decode()
    except urllib.error.HTTPError as e:
        return e.code, url, e.read().decode()


def sh(*c):
    return subprocess.run(c, cwd=PROJECT, capture_output=True, text=True).stdout.strip()


def wp(*a):
    return sh("docker", "compose", "run", "--rm", "-q", "wpcli", *a)


def aviso(url):
    return urllib.parse.parse_qs(urllib.parse.urlparse(url).query).get("concursos_voto", ["?"])[0]


def mails(prefijo, to):
    msgs = json.loads(fetch(urllib.request.build_opener(), MAILPIT + "/api/v1/messages?limit=300")[2])["messages"]
    return [m for m in msgs if m["Subject"].startswith(prefijo) and m["To"][0]["Address"] == to]


def texto(m):
    return json.loads(fetch(urllib.request.build_opener(), f"{MAILPIT}/api/v1/message/{m['ID']}")[2])["Text"]


def votante():
    """Registra y confirma un votante nuevo; devuelve su sesión."""
    email = f"pt{uuid.uuid4().hex[:6]}@example.com"
    v = session()
    page = fetch(v, RETO_URL)[2]
    nonce = re.search(r'name="_wpnonce" value="([^"]+)"', page).group(1)
    fetch(v, BASE + "/wp-admin/admin-post.php", urllib.parse.urlencode({"action": "concursos_votante_registro", "reto": RETO, "email": email, "_wpnonce": nonce}).encode())
    enlace = re.search(r"http://localhost:8080/\?\S+", texto(mails("Confirma", email)[0])).group(0).replace("&#038;", "&")
    fetch(v, enlace)
    return v, email


def votar(v, foto, puesto=None, op="votar"):
    page = fetch(v, RETO_URL)[2]
    nonce = re.search(r'name="action" value="concursos_votar"><input type="hidden" name="_wpnonce" value="([^"]+)"', page).group(1)
    data = {"action": "concursos_votar", "_wpnonce": nonce, "reto": RETO, "entrada": foto, "op": op}
    if puesto is not None:
        data["puesto"] = puesto
    return aviso(fetch(v, BASE + "/wp-admin/admin-post.php", urllib.parse.urlencode(data).encode())[1])


def puntos():
    return json.loads(wp("eval", f"echo wp_json_encode( Concursos_Votos::counts( {RETO} ) );") or "{}")


def mis_puestos(email):
    filas = wp("db", "query", f"SELECT v.entrada_id, v.puesto FROM wp_concursos_votos v JOIN wp_concursos_votantes t ON t.id = v.votante_id WHERE t.email = '{email}' ORDER BY v.puesto", "--skip-column-names")
    return {int(e): int(p) for e, p in (l.split("\t") for l in filas.splitlines() if l.strip())}


def fase(nombre):
    d = {"envios": (-86400, 86400, 2 * 86400, 3 * 86400), "votacion": (-2 * 86400, -3600, 2 * 86400, 3 * 86400),
         "evaluacion": (-3 * 86400, -2 * 86400, -3600, 86400), "finalizado": (-4 * 86400, -3 * 86400, -2 * 86400, -3600)}[nombre]
    wp("eval", f"$t=time(); foreach(array('inicio'=>{d[0]},'fin_envios'=>{d[1]},'fin_votacion'=>{d[2]},'cierre'=>{d[3]}) as $k=>$v) update_post_meta({RETO},'_concursos_'.$k,$t+$v);")


print(sh("make", "demo", "FASE=votacion").splitlines()[-1])
RETO = wp("post", "list", "--post_type=concurso_reto", "--name=reto-de-prueba-luz-y-sombra", "--field=ID")
fotos = wp("post", "list", "--post_type=concurso_entrada", "--meta_key=_concursos_reto", f"--meta_value={RETO}", "--field=ID", "--orderby=ID", "--order=ASC").split()
f = [int(x) for x in fotos]
wp("db", "query", f"DELETE FROM wp_concursos_votos WHERE reto_id={RETO}")

# --- Votación por puestos (el reto de ejemplo permite 3 votos)
v, email = votante()
h = fetch(v, RETO_URL)[2]
check("panel explica los puntos", "Elige tu 1.º, 2.º y 3.º: valen 3, 2 y 1 puntos." in h)
check("cada foto ofrece 1.º, 2.º y 3.º", len(re.findall(r'name="puesto" value="[123]"', h)) == 27, str(len(re.findall(r'name="puesto"', h))))
check("1.º a la foto A", votar(v, f[0], 1) == "votado")
check("2.º a la foto B", votar(v, f[1], 2) == "votado")
check("3.º a la foto C", votar(v, f[2], 3) == "votado")
p = puntos()
check("puntos 3, 2 y 1", (p.get(str(f[0])), p.get(str(f[1])), p.get(str(f[2]))) == (3, 2, 1), str(p))
h = fetch(v, RETO_URL)[2]
check("la foto A muestra «Tu 1.º · 3 puntos»", "Tu 1.º · 3 puntos" in h and "Tu 3.º · 1 punto" in h)
check("quedan 0 de 3 y los puestos usados se marcan", "Te quedan 0 de 3" in h and h.count("concursos-voto--usado") == 18, str(h.count("concursos-voto--usado")))
check("mover el 1.º a la foto D", votar(v, f[3], 1) == "votado" and mis_puestos(email) == {f[3]: 1, f[1]: 2, f[2]: 3}, str(mis_puestos(email)))
check("cambiar la foto D al 2.º libera su 1.º y quita el 2.º a B", votar(v, f[3], 2) == "votado" and mis_puestos(email) == {f[3]: 2, f[2]: 3}, str(mis_puestos(email)))
check("puesto 4 no vale", votar(v, f[4], 4) == "error" and len(mis_puestos(email)) == 2)
check("puesto 0 no vale", votar(v, f[4], 0) == "error")
check("sin puesto no vale", votar(v, f[4]) == "error")
check("quitar el voto de C", votar(v, f[2], op="quitar") == "quitado" and mis_puestos(email) == {f[3]: 2})
p = puntos()
check("solo quedan 2 puntos de D", p == {str(f[3]): 2}, str(p))

# --- Un solo voto: botón «Votar», 1 punto
wp("post", "meta", "update", RETO, "_concursos_votos_por_votante", "1")
wp("db", "query", f"DELETE FROM wp_concursos_votos WHERE reto_id={RETO}")
h = fetch(v, RETO_URL)[2]
check("con un voto: botón «Votar» sin puestos", 'name="puesto"' not in h and ">Votar</button>" in h and "Elige tu" not in h)
check("con un voto: vota sin puesto", votar(v, f[0]) == "votado" and mis_puestos(email) == {f[0]: 0})
check("con un voto: vale 1 punto", puntos() == {str(f[0]): 1}, str(puntos()))
check("con un voto: no admite otro", votar(v, f[1]) == "limite")
wp("post", "meta", "update", RETO, "_concursos_votos_por_votante", "3")
wp("db", "query", f"DELETE FROM wp_concursos_votos WHERE reto_id={RETO}")

# --- Resultados en puntos
print(sh("make", "demo", "FASE=finalizado").splitlines()[-1])
RETO = wp("post", "list", "--post_type=concurso_reto", "--name=reto-de-prueba-luz-y-sombra", "--field=ID")
h = fetch(session(), RETO_URL)[2]
check("resultados en puntos", "6 puntos" in h and "0 puntos" in h and "6 votos" not in h)
check("resumen con columna Puntos", re.search(r'concursos-resumen.*?<th scope="col" class="[^"]*concursos-tabla__num">Puntos</th>', h, re.S) is not None)
check("nota de la clasificación explica los puntos", "un 1.º vale 3 puntos" in h)

# --- Votación anónima
print(sh("make", "demo", "FASE=votacion").splitlines()[-1])
RETO = wp("post", "list", "--post_type=concurso_reto", "--name=reto-de-prueba-luz-y-sombra", "--field=ID")
marta = wp("post", "list", "--post_type=concurso_inscrito", "--title=Marta López", "--field=ID", "--meta_key=_concursos_reto", f"--meta_value={RETO}")
perfil = wp("eval", f"echo Concursos_Perfil::url( {marta} );")
titulos = ["Callejón al atardecer", "Reflejo en el charco"]
h = fetch(session(), RETO_URL)[2]
check("sin anonimato: se ven títulos y autores", all(t in h for t in titulos) and "Marta López" in h)
wp("post", "meta", "update", RETO, "_concursos_anonimo", "1")
h = fetch(session(), RETO_URL)[2]
galeria = h[h.find('id="concursos-votacion"'):]
check("anónima: aviso de votación anónima", "Votación anónima: el título y el autor" in h)
check("anónima: ni títulos ni autores en la galería", not any(t in galeria for t in titulos) and "Marta López" not in galeria and "/participante/" not in galeria)
check("anónima: la ventana ampliada no lleva datos", 'data-titulo=""' in galeria and 'data-autor=""' in galeria and 'data-autor="Marta' not in galeria)
check("anónima: siguen las 9 fotos", galeria.count('class="concursos-foto"') == 9)
st, _, hp = fetch(session(), perfil)
seccion = next((x for x in hp.split('concursos-perfil__reto"') if "reto-de-prueba-luz-y-sombra" in x), "")
check("anónima: el perfil no enseña las fotos de este reto", st == 200 and seccion != "" and "concursos-foto" not in seccion, str(st))
fase("envios")
h = fetch(session(), RETO_URL)[2]
check("anónima: también en la fase de envíos", not any(t in h[h.find('id="concursos-votacion"'):] for t in titulos))
fase("evaluacion")
h = fetch(session(), RETO_URL)[2]
check("anónima: al terminar la votación vuelven título y autor", all(t in h for t in titulos))
fase("finalizado")
h = fetch(session(), RETO_URL)[2]
check("anónima: en resultados se ve todo", all(t in h for t in titulos) and "Marta López" in h)
wp("post", "meta", "update", RETO, "_concursos_anonimo", "0")

# --- Perfiles activables desde los ajustes
admin = session()
fetch(admin, BASE + "/wp-login.php", urllib.parse.urlencode({"log": "admin", "pwd": "admin", "wp-submit": "Acceder", "testcookie": "1"}).encode())


def guardar_perfiles(activos):
    page = fetch(admin, BASE + "/wp-admin/edit.php?post_type=concurso_reto&page=concursos-ajustes")[2]
    datos = dict(re.findall(r"<input type=['\"]hidden['\"][^>]*?name=['\"](_wpnonce|option_page|action|_wp_http_referer)['\"][^>]*?value=['\"]([^'\"]*)['\"]", page))
    campos = list(datos.items()) + [("concursos_perfiles", "0")] + ([("concursos_perfiles", "1")] if activos else [])
    fetch(admin, BASE + "/wp-admin/options.php", urllib.parse.urlencode(campos).encode())
    return wp("option", "get", "concursos_perfiles")


page = fetch(admin, BASE + "/wp-admin/edit.php?post_type=concurso_reto&page=concursos-ajustes")[2]
check("página de ajustes con la casilla marcada", "Perfiles de participantes" in page and re.search(r'name="concursos_perfiles" value="1"\s+checked', page) is not None)
check("desactivar perfiles desde los ajustes", guardar_perfiles(False) == "0")
h = fetch(session(), RETO_URL)[2]
check("sin perfiles: ningún enlace a perfiles en el reto", "/participante/" not in h and "Marta López" in h)
check("sin perfiles: la ventana ampliada no enlaza", 'data-autor-url=""' in h)
st, _, _ = fetch(session(), perfil)
check("sin perfiles: la página de perfil da 404", st == 404, str(st))
check("sin perfiles: la portada no enlaza perfiles", "/participante/" not in fetch(session(), BASE + "/retos/")[2])
lista = fetch(admin, BASE + "/wp-admin/edit.php?post_type=concurso_inscrito")[2]
check("sin perfiles: el panel no ofrece «Ver perfil»", "Ver perfil" not in lista)
check("activar perfiles de nuevo", guardar_perfiles(True) == "1")
check("con perfiles: vuelven los enlaces", "/participante/" in fetch(session(), RETO_URL)[2] and fetch(session(), perfil)[0] == 200)

# --- Nombre de archivo aleatorio al subir una foto
print(sh("make", "demo", "FASE=envios").splitlines()[-1])
RETO = wp("post", "list", "--post_type=concurso_reto", "--name=reto-de-prueba-luz-y-sombra", "--field=ID")
marta = wp("post", "list", "--post_type=concurso_inscrito", "--title=Marta López", "--field=ID", "--meta_key=_concursos_reto", f"--meta_value={RETO}")
wp("post", "meta", "update", RETO, "_concursos_max_fotos", "5")
enlace = wp("eval", f"echo Concursos_Inscripcion::personal_link( Concursos_Participante::issue_token( {marta} ) );")
jpeg = base64.b64decode(wp("eval", "$i=imagecreatetruecolor(900,600); imagefill($i,0,0,imagecolorallocate($i,40,90,120)); ob_start(); imagejpeg($i,null,85); echo base64_encode(ob_get_clean());"))
p = session()
page = fetch(p, enlace)[2]
nonce = re.search(r'name="_wpnonce" value="([^"]+)"', page).group(1)
b = uuid.uuid4().hex
cuerpo = (f'--{b}\r\nContent-Disposition: form-data; name="_wpnonce"\r\n\r\n{nonce}\r\n--{b}\r\nContent-Disposition: form-data; name="titulo"\r\n\r\nFaro\r\n'
          f'--{b}\r\nContent-Disposition: form-data; name="foto"; filename="Marta-Lopez-faro.jpg"\r\nContent-Type: image/jpeg\r\n\r\n').encode() + jpeg + f"\r\n--{b}--\r\n".encode()
_, final, _ = fetch(p, enlace, cuerpo, {"Content-Type": f"multipart/form-data; boundary={b}"})
check("sube la foto", "resultado=subida" in final, final[-30:])
nueva = wp("post", "list", "--post_type=concurso_entrada", "--title=Faro", "--field=ID")
url = wp("eval", f"echo wp_get_attachment_url( get_post_thumbnail_id( {nueva} ) );")
check("el archivo no conserva el nombre original", "Marta" not in url and "faro" not in url.lower() and re.search(r"/foto-[a-z0-9]{12}[^/]*\.webp$", url) is not None, url.rsplit("/", 1)[-1])

print(sh("make", "demo", "FASE=votacion").splitlines()[-1])
print("\nFALLOS:", fallos if fallos else "ninguno")
sys.exit(1 if fallos else 0)
