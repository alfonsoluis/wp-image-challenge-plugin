"""Pantalla «Stats»: acceso, cifras, clasificación igual a la pública, CSV sin emails y caché."""
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
PROJECT = os.path.abspath(os.path.join(os.path.dirname(__file__), "..", ".."))
PAGINA = BASE + "/wp-admin/edit.php?post_type=concurso_reto&page=concursos-stats"
run = uuid.uuid4().hex[:6]
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
    return urllib.request.build_opener(urllib.request.HTTPCookieProcessor(jar))


def fetch(op, url, data=None):
    try:
        r = op.open(urllib.request.Request(url, data=data))
        return r.status, r.headers, r.read().decode("utf-8", "replace"), r.geturl()
    except urllib.error.HTTPError as e:
        return e.code, e.headers, e.read().decode("utf-8", "replace"), url


def login(usuario, clave):
    op = session()
    fetch(op, BASE + "/wp-login.php", urllib.parse.urlencode({"log": usuario, "pwd": clave, "wp-submit": "Acceder", "testcookie": "1"}).encode())
    return op


def sql(q):
    return wp("db", "query", q, "--skip-column-names")


print(sh("make", "demo", "FASE=votacion").splitlines()[-1])
reto = wp("post", "list", "--post_type=concurso_reto", "--name=reto-de-prueba-luz-y-sombra", "--field=ID")
fotos = wp("eval", f"echo implode( ',', wp_list_pluck( Concursos_Entrada::find( array( 'reto' => {reto} ) ), 'ID' ) );").split(",")

# Tres votantes reparten puestos: 1.º = 3 puntos, 2.º = 2, 3.º = 1.
emails = [f"zeta{run}{i}@example.com" for i in range(3)]
for e in emails:
    sql(f"INSERT INTO wp_concursos_votantes (email, estado, creado) VALUES ('{e}', 'confirmado', UNIX_TIMESTAMP())")
ids = [sql(f"SELECT id FROM wp_concursos_votantes WHERE email='{e}'") for e in emails]
reparto = [(ids[0], fotos[0], 1), (ids[0], fotos[1], 2), (ids[0], fotos[2], 3), (ids[1], fotos[1], 1), (ids[1], fotos[0], 2), (ids[2], fotos[2], 1)]
for v, f, p in reparto:
    sql(f"INSERT INTO wp_concursos_votos (votante_id, reto_id, entrada_id, puesto, creado) VALUES ({v}, {reto}, {f}, {p}, UNIX_TIMESTAMP())")
puntos = {}
for _, f, p in reparto:
    puntos[f] = puntos.get(f, 0) + {1: 3, 2: 2, 3: 1}[p]
wp("transient", "delete", "--all")

admin = login("admin", "admin")
st, _, h, _ = fetch(admin, f"{PAGINA}&reto={reto}")
check("el admin ve la página", st == 200 and "<h1" in h and ">Stats</h1>" in h, str(st))
check("cuatro tarjetas del periodo", len(re.findall(r'data-metrica="', h)) == 4)
check("aviso de próximos cambios de etapa", "notice-info concursos-stats-aviso" in h and "cierra la votación" in h)
check("el reto elegido lleva aria-current", re.search(r'<a href="[^"]*reto=' + reto + r'[^"]*" aria-current="page"', h) is not None)
check("gráficas con role=img y valores", 'role="img" aria-label="Participantes nuevos por día:' in h)

# La clasificación provisional coincide con Concursos_Votos::rank().
esperado = json.loads(wp("eval", f"$s = Concursos_Reto::get_settings( {reto} ); $r = Concursos_Votos::rank( Concursos_Votos::de_fotos( {reto}, Concursos_Entrada::find( array( 'reto' => {reto} ) ) ), (int) $s['lugares'] ); $o = array(); foreach ( $r['puestos'] as $p ) {{ foreach ( $p['entradas'] as $e ) {{ $o[] = array( $e, $p['lugar'] ); }} }} foreach ( $r['resto'] as $e ) {{ $o[] = array( $e, 0 ); }} echo wp_json_encode( array_slice( $o, 0, 5 ) );"))
ranking = re.search(r'<ol class="concursos-stats-ranking">(.*?)</ol>', h, re.S).group(1)
pantalla = [[int(a), int(b)] for a, b in re.findall(r'data-entrada="(\d+)" data-lugar="(\d+)"', ranking)]
check("clasificación provisional = rank()", pantalla == esperado, f"{pantalla} vs {esperado}")
check("empate en el 1.º con 5 puntos", pantalla[0][1] == 1 and pantalla[1][1] == 1 and "5 puntos" in ranking, str(puntos))
check("puntos 3/2/1 como en la web", all(f"{n} punto" in ranking for n in puntos.values()))
check("marcada solo para el admin", "Solo visible en el admin" in h)
check("últimos votos con el email enmascarado", f"z•••@example.com" in h and not any(e in h for e in emails))

# CSV: sin nonce no; con nonce, sin emails.
check("CSV sin nonce rechazado", fetch(admin, BASE + "/wp-admin/admin-post.php?action=concursos_stats_csv&tipo=panel")[0] == 403)
enlace = re.search(r'href="([^"]*action=concursos_stats_csv[^"]*tipo=panel[^"]*)"', h).group(1).replace("&#038;", "&").replace("&amp;", "&")
st, cab, csv, _ = fetch(admin, enlace)
check("CSV del periodo", st == 200 and "text/csv" in cab.get("Content-Type", "") and csv.count("\n") >= 8 and "@" not in csv, csv[:80])
fin = wp("post", "list", "--post_type=concurso_reto", "--name=reto-anterior-reflejos", "--field=ID")
_, _, hf, _ = fetch(admin, f"{PAGINA}&reto={fin}")
enlace = re.search(r'href="([^"]*action=concursos_stats_csv[^"]*tipo=resultados[^"]*)"', hf).group(1).replace("&#038;", "&").replace("&amp;", "&")
st, cab, csv, _ = fetch(admin, enlace)
check("CSV de resultados sin emails", st == 200 and "Lugar" in csv and "@" not in csv and "Duplicar reto" in hf, csv[:80])

# Sin la capacidad (autor), sin acceso.
wp("user", "create", f"autor{run}", f"autor{run}@example.com", "--role=author", f"--user_pass=clave{run}")
autor = login(f"autor{run}", f"clave{run}")
check("un autor no puede entrar", fetch(autor, PAGINA)[0] == 403)
check("ni exportar", fetch(autor, enlace)[0] == 403)
wp("user", "delete", f"autor{run}", "--yes")

# La caché se borra al votar.
wp("eval", "Concursos_Stats::panel( '7d' );")
check("la caché se guarda", wp("eval", "echo get_transient( 'concursos_stats_7d' ) ? 1 : 0;") == "1")
pid = wp("eval", f"echo Concursos_Entrada::owner( {fotos[0]} );")
votante = session()
fetch(votante, BASE + "/?concursos_envio=" + wp("eval", f"echo Concursos_Participante::issue_token( {pid} );"))
_, _, hr, _ = fetch(votante, BASE + "/retos/reto-de-prueba-luz-y-sombra/")
nonce = re.search(r'value="concursos_votar"><input type="hidden" name="_wpnonce" value="([^"]+)"', hr).group(1)
otra = next(f for f in fotos if f'value="{f}:1"' in hr)
fetch(votante, BASE + "/wp-admin/admin-post.php", urllib.parse.urlencode({"action": "concursos_votar", "_wpnonce": nonce, "reto": reto, "entrada": otra, "op": "votar", "puesto": 1}).encode())
check("al votar se borra la caché", wp("eval", "echo get_transient( 'concursos_stats_7d' ) ? 1 : 0;") == "0")

print(sh("make", "demo", "FASE=votacion").splitlines()[-1])
print("\nFALLOS:", fallos if fallos else "ninguno")
sys.exit(1 if fallos else 0)
