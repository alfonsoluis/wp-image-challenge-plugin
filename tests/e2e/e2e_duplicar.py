"""Duplicar retos, página de votantes y diseño por reto."""
import os
import html as H, http.cookiejar, json, re, subprocess, sys, urllib.error, urllib.parse, urllib.request
from html.parser import HTMLParser
BASE="http://localhost:8080"; MAILPIT="http://localhost:8025"; PROJECT = os.path.abspath(os.path.join(os.path.dirname(__file__), "..", ".."))
fallos=[]
def check(n,ok,d=""):
    print("OK   " if ok else "FALLO",n,d)
    if not ok: fallos.append(n)
def sh(*c): return subprocess.run(c,cwd=PROJECT,capture_output=True,text=True).stdout.strip()
def wp(*a): return sh("docker","compose","run","--rm","-q","wpcli",*a)

# Necesita el reto de ejemplo «Luz de otoño» (ID 8) publicado: lo publica y al terminar lo deja como estaba.
import atexit
_estado8 = wp("post", "get", "8", "--field=post_status")
if _estado8 and _estado8 != "publish":
    wp("db", "query", "UPDATE wp_posts SET post_status='publish' WHERE ID=8"); wp("cache", "flush")
    atexit.register(lambda: (wp("db", "query", f"UPDATE wp_posts SET post_status='{_estado8}' WHERE ID=8"), wp("cache", "flush")))
def session(): return urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
def fetch(op,u,d=None):
    try:
        r=op.open(urllib.request.Request(u,data=d)); return r.status,r.geturl(),r.read().decode(errors="replace")
    except urllib.error.HTTPError as e: return e.code,u,e.read().decode()
def login(user,pwd):
    op=session(); fetch(op,BASE+"/wp-login.php",urllib.parse.urlencode({"log":user,"pwd":pwd,"wp-submit":"Acceder","testcookie":"1"}).encode()); return op
def enlaces(to=None):
    op=urllib.request.build_opener()
    ms=json.loads(fetch(op,MAILPIT+"/api/v1/messages?limit=500")[2])["messages"]
    return [m for m in ms if m["Subject"].startswith("Ya puedes") and (to is None or m["To"][0]["Address"]==to)]
def rid(n): return wp("post","list","--post_type=concurso_reto","--post_status=any",f"--name={n}","--field=ID")
def meta(i,k): return wp("post","meta","get",i,k)
def lum(h):
    c=[int(h[i:i+2],16)/255 for i in (1,3,5)]; c=[x/12.92 if x<=.03928 else ((x+.055)/1.055)**2.4 for x in c]
    return .2126*c[0]+.7152*c[1]+.0722*c[2]
def ratio(a,b):
    x,y=sorted((lum(a),lum(b)),reverse=True); return (x+.05)/(y+.05)

print(sh("make","demo","FASE=finalizado").splitlines()[-1])
# --- Contraste ---
pal=json.loads(wp("eval","echo wp_json_encode(Concursos_Diseno::paletas());"))
check("10 paletas",len(pal)==10)
for n,p in pal.items():
    pares={"texto/fondo":(p["texto"],p["fondo"]),"texto/suave":(p["texto"],p["suave"]),"sobre/color":(p["sobre"],p["color"]),"sobre/acento":(p["sobre_acento"],p["acento"]),"color/fondo":(p["color"],p["fondo"]),"color/suave":(p["color"],p["suave"])}
    r={k:round(ratio(*v),2) for k,v in pares.items()}
    check(f"contraste AA {n}",min(r.values())>=4.5 and ratio(p["acento"],p["color"])>=3,f"mín {min(r.values())} {min(r,key=r.get)}")
check("3 tamaños y sin tipografías",wp("eval","echo count(Concursos_Diseno::tamanos()).(int)method_exists('Concursos_Diseno','tipografias');")=="30")

# --- Votantes ---
admin=login("admin","admin")
VP=BASE+"/wp-admin/edit.php?post_type=concurso_reto&page=concursos-votantes"
st,_,h=fetch(admin,VP)
check("página de votantes",st==200 and "Fecha de registro" in h and "Retos en los que votó" in h,str(st))
check("demo listados","votante-demo-1@example.com" in h)
filas=re.findall(r'<tr>.*?</tr>',h[h.find("<tbody"):],re.S)
f=next(x for x in filas if "votante-demo-1@example.com" in x)
votos_db=wp("db","query","SELECT COUNT(*) FROM wp_concursos_votos v JOIN wp_concursos_votantes t ON t.id=v.votante_id WHERE t.email='votante-demo-1@example.com'","--skip-column-names")
col=re.search(r'column-votos[^>]*>(\d+)',f)
check("votos coinciden con la base de datos",col and col.group(1)==votos_db,f"{col and col.group(1)} vs {votos_db}")
check("muestra en qué retos votó","Reto de prueba: luz y sombra" in H.unescape(f) and "Reto anterior: reflejos" in f,re.sub(r"\s+"," ",f[-260:]))
check("fecha visible",re.search(r'column-creado[^>]*>[^<]*\d{4}',f) is not None)
_,_,h2=fetch(admin,VP+"&s=votante-demo-3")
check("búsqueda","votante-demo-3@example.com" in h2 and "votante-demo-1@example.com" not in h2)
_,_,h3=fetch(admin,VP+"&orderby=votos&order=asc")
n=[int(x) for x in re.findall(r'column-votos[^>]*>(\d+)',h3)]
check("ordenar por votos",n==sorted(n) and len(n)>5,str(n[:6]))
_,_,h4=fetch(admin,VP+"&orderby=SLEEP(1)&order=x")
check("orden inválido ignorado","votante-demo" in h4)
wp("user","create","autor1","autor1@example.com","--role=author","--user_pass=autor1")
st,_,_=fetch(login("autor1","autor1"),VP); check("un autor no ve los emails",st==403,str(st))

# --- Duplicar ---
print(sh("make","demo","FASE=votacion").splitlines()[-1])
R=rid("reto-de-prueba-luz-y-sombra")
_,_,lista=fetch(admin,BASE+"/wp-admin/edit.php?post_type=concurso_reto")
check("acción Duplicar en la lista",f"page=concursos-duplicar&#038;reto={R}" in lista or f"page=concursos-duplicar&amp;reto={R}" in lista)
_,_,panel=fetch(admin,BASE+"/wp-admin/edit.php?post_type=concurso_reto&page=concursos-panel")
check("Duplicar en el panel y sin columna Tipo","concursos-duplicar" in panel and ">Tipo<" not in panel)
DU=BASE+f"/wp-admin/options.php?page=concursos-duplicar&reto={R}"
st,_,p=fetch(admin,DU)
check("pantalla de duplicar",st==200 and "Copiar también sus 6 participantes" in p,str(st))
nonce=re.search(r'name="_wpnonce" value="([^"]+)"',p).group(1)
st,_,_=fetch(session(),BASE+"/wp-admin/admin-post.php",urllib.parse.urlencode({"action":"concursos_duplicar","reto":R,"_wpnonce":nonce}).encode())
check("sin sesión no duplica",rid("reto-de-prueba-luz-y-sombra-copia")=="",str(st))
antes=len(enlaces())
def duplicar(extra):
    d={"action":"concursos_duplicar","reto":R,"_wpnonce":nonce,**extra}
    return fetch(admin,BASE+"/wp-admin/admin-post.php",urllib.parse.urlencode(d).encode())
st,final,h=duplicar({"participantes":"1"})
C=urllib.parse.parse_qs(urllib.parse.urlparse(final).query)["post"][0]
check("abre la edición con aviso",st==200 and "post.php" in final and "Copia creada con 6 participantes" in h,final)
check("borrador y título (copia)",wp("post","get",C,"--field=post_status")=="draft" and wp("post","get",C,"--field=post_title").endswith("(copia)"))
check("copia la descripción",wp("post","get",C,"--field=post_content")==wp("post","get",R,"--field=post_content"))
check("fechas vacías",all(meta(C,"_concursos_"+k)=="0" for k in ("inicio","fin_envios","fin_votacion","cierre")))
check("copia configuración y diseño",meta(C,"_concursos_paleta")=="playa" and meta(C,"_concursos_votos_por_votante")=="3" and "Un cuadro" in meta(C,"_concursos_premios") and "Foto propia" in meta(C,"_concursos_reglas"))
check("sin fotos",wp("post","list","--post_type=concurso_entrada","--meta_key=_concursos_reto",f"--meta_value={C}","--format=count")=="0")
check("sin votos",wp("db","query",f"SELECT COUNT(*) FROM wp_concursos_votos WHERE reto_id={C}","--skip-column-names")=="0")
cop=wp("post","list","--post_type=concurso_inscrito","--meta_key=_concursos_reto",f"--meta_value={C}","--field=ID").split()
check("6 participantes activos",len(cop)==6 and all(meta(i,"_concursos_estado")=="activo" for i in cop),str(len(cop)))
check("no se envió ningún email",len(enlaces())==antes)
ahora=int(wp("eval","echo time();"))
for k,d in (("inicio",-60),("fin_envios",86400),("fin_votacion",172800),("cierre",259200)): wp("post","meta","update",C,"_concursos_"+k,str(ahora+d))
wp("post","update",C,"--post_status=publish")
check("al publicar, 6 emails",len(enlaces())==antes+6,str(len(enlaces())-antes))
wp("post","update",C,"--post_status=draft"); wp("post","update",C,"--post_status=publish")
check("republicar no reenvía",len(enlaces())==antes+6,str(len(enlaces())-antes))
m=enlaces("participante1@example.com")[0]
txt=json.loads(fetch(urllib.request.build_opener(),f"{MAILPIT}/api/v1/message/{m['ID']}")[2])["Text"]
link=re.search(r"http://localhost:8080/\?concursos_envio=[a-f0-9]{32}",txt).group(0)
st,_,h=fetch(session(),link)
check("el enlace abre el reto nuevo con su diseño",st==200 and "(copia)" in h and "--c-color:#264653" in H.unescape(h) and 'name="foto"' in h,str(st))
url=wp("eval",f"echo Concursos_Perfil::url({cop[0]});")
_,_,h=fetch(session(),url); check("el perfil suma el reto nuevo","4 retos" in h,str(re.findall(r'cifras">\s*([^<\t]+)',h)))
st,final,h=duplicar({})
C2=urllib.parse.parse_qs(urllib.parse.urlparse(final).query)["post"][0]
check("sin copiar participantes",wp("post","list","--post_type=concurso_inscrito","--meta_key=_concursos_reto",f"--meta_value={C2}","--format=count")=="0" and "Copia creada." in h)

# --- Formulario y diseño ---
class F(HTMLParser):
    def __init__(s): super().__init__(); s.i=False; s.f={}
    def handle_starttag(s,t,a):
        a=dict(a)
        if t=="form" and a.get("id")=="post": s.i=True
        elif s.i and t=="input" and a.get("type")=="hidden" and a.get("name"): s.f[a["name"]]=a.get("value","")
    def handle_endtag(s,t):
        if t=="form": s.i=False
_,_,pg=fetch(admin,BASE+f"/wp-admin/post.php?post={C2}&action=edit")
check("formulario con Diseño y sin Tipo","concursos[paleta]" in pg and "concursos[tipografia]" not in pg and "concursos[tamano]" in pg and "concursos[tipo]" not in pg and "concursos[frecuencia]" not in pg)
check("admin sin fuentes propias","Space Grotesk" not in pg and "Playfair" not in pg)
pa=F(); pa.feed(pg); d=dict(pa.f)
for x in ("aa","mm","jj","hh","mn"): d[x]=d.get("hidden_"+x,"")
d.update({"post_title":"Guardado de diseño","content":"x","save":"Guardar","concursos[paleta]":"baya","concursos[tamano]":"grande","concursos[modo]":"abierto","concursos[lugares]":"1"})
fetch(admin,BASE+"/wp-admin/post.php",urllib.parse.urlencode(d).encode())
check("guarda el diseño elegido",(meta(C2,"_concursos_paleta"),meta(C2,"_concursos_tamano"))==("baya","grande"))
d.update({"concursos[paleta]":"<script>","concursos[tamano]":"enorme"})
fetch(admin,BASE+"/wp-admin/post.php",urllib.parse.urlencode(d).encode())
check("valores inválidos vuelven al tema",(meta(C2,"_concursos_paleta"),meta(C2,"_concursos_tamano"))==("tema","normal"))

st,_,h=fetch(session(),BASE+"/retos/reto-de-prueba-luz-y-sombra/")
u=H.unescape(h)
check("reto con su paleta y la fuente del tema","--c-color:#264653" in u and "--c-fuente" not in u and "Playfair" not in u)
check("reglas, premios y fase","Foto propia" in h and "Un cuadro" in h and "En votación hasta" in h)
check("una sola h1 y galería",h.count("<h1")==1 and h.count("<figcaption>")==9)
check("sin fuentes externas ni propias","fonts.googleapis" not in h and "plugins/concursos/assets/fonts" not in h)
_,_,g=fetch(session(),BASE+"/retos/reto-anterior-reflejos/"); check("tamaño grande",'--c-escala:1.125' in g)
_,_,g=fetch(session(),BASE+"/retos/reto-anterior-calles-vacias/"); check("tamaño pequeño",'--c-escala:0.9' in g )
_,_,g=fetch(session(),BASE+"/retos/reto-luz-de-otono/")
est=re.search(r'class="concursos-reto concursos-disenado(?: concursos-con-fondo)?" style="([^"]*)"',g)
check("con el tema no fija nada",est and est.group(1)=="",str(est and est.group(1)))
_,_,p=fetch(session(),BASE+"/retos/"); check("portada con diseño del destacado","--c-cabecera:#264653" in H.unescape(p))

# Limpieza
for c in (C,C2):
    for i in wp("post","list","--post_type=concurso_inscrito","--meta_key=_concursos_reto",f"--meta_value={c}","--field=ID").split(): wp("post","delete",i,"--force")
    wp("post","delete",c,"--force")
wp("user","delete","autor1","--yes")
print("\nFALLOS:",fallos if fallos else "ninguno"); sys.exit(1 if fallos else 0)
