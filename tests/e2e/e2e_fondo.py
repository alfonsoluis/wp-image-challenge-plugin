"""Casillas del fondo de iconos: guardado real desde el formulario y efecto en las tres páginas."""
import os
import http.cookiejar, html.parser, re, subprocess, sys, urllib.parse, urllib.request
BASE="http://localhost:8080"; PROJECT = os.path.abspath(os.path.join(os.path.dirname(__file__), "..", "..")); fallos=[]
def check(n,ok,d=""):
    print(("OK   " if ok else "FALLO"),n,d)
    if not ok: fallos.append(n)
def sh(*c): return subprocess.run(c,cwd=PROJECT,capture_output=True,text=True).stdout.strip()
def wp(*a): return sh("docker","compose","run","--rm","-q","wpcli",*a)

# El reto de ejemplo «Reto semanal» (ID 9) no debe pasar por delante del reto de prueba:
# mientras dura la prueba queda como próximo y al terminar recupera sus fechas.
import atexit as _atexit, json as _json
_fechas9 = _json.loads(wp("eval", "echo wp_json_encode(array_map(function($k){return get_post_meta(9,'_concursos_'.$k,true);}, array('inicio','fin_envios','fin_votacion','cierre')));") or "[]")
if len(_fechas9) == 4:
    wp("eval", "$t=time(); foreach(array('inicio'=>5,'fin_envios'=>6,'fin_votacion'=>7,'cierre'=>8) as $k=>$d) update_post_meta(9,'_concursos_'.$k,$t+$d*86400);")
    _atexit.register(lambda: wp("eval", "foreach(array_combine(array('inicio','fin_envios','fin_votacion','cierre'), json_decode('%s')) as $k=>$v) update_post_meta(9,'_concursos_'.$k,$v);" % _json.dumps(_fechas9)))
op=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
def get(u,data=None):
    return op.open(urllib.request.Request(u,data=data)).read().decode()
class F(html.parser.HTMLParser):
    def __init__(s): super().__init__(); s.f=[]; s.dentro=0; s.ta=None; s.sel=None; s.ops=[]
    def handle_starttag(s,t,a):
        a=dict(a)
        if t=="form" and ("metabox-" in (a.get("class") or "") or a.get("id")=="post"): s.dentro=1
        if not s.dentro: return
        n=a.get("name")
        if t=="input" and n and a.get("type") not in ("file","submit","button"):
            if a.get("type") in ("checkbox","radio") and "checked" not in a: return
            s.f.append((n,a.get("value") or "on" if a.get("type") in ("checkbox","radio") else a.get("value") or ""))
        elif t=="textarea" and n: s.ta=n; s.buf=""
        elif t=="select" and n: s.sel=n; s.prim=None; s.val=None
        elif t=="option" and s.sel:
            if s.prim is None: s.prim=a.get("value","")
            if "selected" in a: s.val=a.get("value","")
    def handle_data(s,d):
        if s.ta: s.buf+=d
    def handle_endtag(s,t):
        if t=="form": s.dentro=0
        if t=="textarea" and s.ta: s.f.append((s.ta,s.buf)); s.ta=None
        if t=="select" and s.sel: s.f.append((s.sel,s.val if s.val is not None else s.prim)); s.sel=None
get(BASE+"/wp-login.php",urllib.parse.urlencode({"log":"admin","pwd":"admin","wp-submit":"Acceder","testcookie":"1"}).encode())
print(sh("make","demo","FASE=envios").splitlines()[-1])
ID=wp("post","list","--post_type=concurso_reto","--name=reto-de-prueba-luz-y-sombra","--field=ID")
slug="reto-de-prueba-luz-y-sombra"
def guardar(paginas):
    h=get(f"{BASE}/wp-admin/post.php?post={ID}&action=edit")
    p=F(); p.feed(h)
    campos=[x for x in p.f if x[0]!="concursos[fondo_iconos][]"]+[("concursos[fondo_iconos][]",v) for v in paginas]
    m=re.search(r'meta-box-loader-nonce=([0-9a-f]+)|"metaBoxLoaderNonce"\s*:\s*"([0-9a-f]+)"',h)
    print("    editor:", "bloques" if m else "clasico", "campos", len(campos), "tiene _wpnonce", any(n=="_wpnonce" for n,_ in campos))
    url=f"{BASE}/wp-admin/post.php"+(f"?post={ID}&action=edit&meta-box-loader=1&meta-box-loader-nonce={m.group(1)}" if m else "")
    get(url,urllib.parse.urlencode(campos).encode())
    return h
def estado():
    return dict(
      portada=' concursos-con-fondo' in re.search(r'<main class="([^"]*)',get(BASE+"/retos/")).group(0) or 'concursos-con-fondo' in re.search(r'<main class="([^"]*)',get(BASE+"/retos/")).group(1),
      detalle='concursos-reto concursos-disenado concursos-con-fondo' in get(f"{BASE}/retos/{slug}/"),
      inscripcion='concursos-fondo-pagina' in re.search(r'<body class="([^"]*)',get(BASE+"/inscripcion-retos/")).group(1))
h=get(f"{BASE}/wp-admin/post.php?post={ID}&action=edit")
check("el reto sin ajuste muestra las 3 casillas marcadas", len(re.findall(r'name="concursos\[fondo_iconos\]\[\]"[^>]*checked',h))==3)
check("sin ajuste: fondo en las 3 páginas", all(estado().values()), str(estado()))
for marcadas in (["detalle"],["portada","inscripcion"],[],["portada","detalle","inscripcion"]):
    guardar(marcadas)
    e=estado(); esperado={k:(k in marcadas) for k in ("portada","detalle","inscripcion")}
    check(f"guardar {marcadas}", e==esperado, str(e))
    h=get(f"{BASE}/wp-admin/post.php?post={ID}&action=edit")
    if not marcadas:
        check("con otro reto en el enlace sigue a ese reto", 'concursos-fondo-pagina' in re.search(r'<body class="([^"]*)',get(BASE+"/inscripcion-retos/?reto=9")).group(1))
    check(f"casillas persisten {marcadas}", len(re.findall(r'name="concursos\[fondo_iconos\]\[\]"[^>]*checked',h))==len(marcadas))
t=get(f"{BASE}/retos/{slug}/")
check("el reto sigue funcionando tras guardar","concursos-reto" in t and "Reto de prueba" in t)
print(sh("make","demo","FASE=votacion").splitlines()[-1])
print("\nFALLOS:",fallos if fallos else "ninguno"); sys.exit(1 if fallos else 0)
