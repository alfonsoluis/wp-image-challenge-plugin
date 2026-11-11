"""Filtros y acciones en bloque de la página de votantes, con votantes propios."""
import os
import http.cookiejar,re,subprocess,sys,uuid,urllib.error,urllib.parse,urllib.request,html as H,calendar,datetime as dt
BASE="http://localhost:8080/wp-admin/edit.php";PROJECT = os.path.abspath(os.path.join(os.path.dirname(__file__), "..", ".."))
fallos=[];run=uuid.uuid4().hex[:6]
def check(n,ok,d=""):
    print("OK   " if ok else "FALLO",n,d)
    if not ok: fallos.append(n)
def wp(*a): return subprocess.run(["docker","compose","run","--rm","-q","wpcli",*a],cwd=PROJECT,capture_output=True,text=True).stdout.strip()
def sql(q): return wp("db","query",q,"--skip-column-names")
op=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
op.open("http://localhost:8080/wp-login.php",urllib.parse.urlencode({"log":"admin","pwd":"admin","wp-submit":"x","testcookie":"1"}).encode())
def get(**kw):
    q={"post_type":"concurso_reto","page":"concursos-votantes",**kw}
    r=op.open(BASE+"?"+urllib.parse.urlencode(q,doseq=True)); return r.geturl(),r.read().decode()
def ts(y,m,d): return calendar.timegm((y,m,d,12,0,0))
A,B,C=[f"{x}{run}@test.example.com" for x in "abc"]
reto=wp("post","list","--post_type=concurso_reto","--name=reto-anterior-reflejos","--field=ID")
for mail,t,sus in ((A,ts(2025,1,15),1),(B,ts(2025,6,15),1),(C,ts(2026,3,15),0)):
    sql(f"INSERT INTO wp_concursos_votantes (email,estado,suscrito,creado) VALUES ('{mail}','confirmado',{sus},{t})")
ids={m:sql(f"SELECT id FROM wp_concursos_votantes WHERE email='{m}'") for m in (A,B,C)}
foto=wp("post","list","--post_type=concurso_entrada","--meta_key=_concursos_reto",f"--meta_value={reto}","--field=ID").split()[0]
sql(f"INSERT INTO wp_concursos_votos (votante_id,reto_id,entrada_id,creado) VALUES ({ids[A]},{reto},{foto},1)")
s=f"{run}@test"
def emails(h): return sorted(re.findall(r"<strong>([a-c]"+run+r"@test[^<]+)",h))
_,h=get(s=s)
check("los tres aparecen",emails(h)==sorted([A,B,C]),str(emails(h)))
check("casillas y seleccionar todo",f'name="votante[]" value="{ids[A]}"' in h and 'id="cb-select-all-1"' in h)
check("acciones en bloque","Dar de baja de los avisos" in h and 'value="concursos_eliminar"' in h)
check("filtros presentes",'name="concursos_reto"' in h and 'name="desde"' in h and 'name="hasta"' in h and "Filtrar" in h)
check("columna Avisos",">Avisos<" in h or "Avisos</" in h)
_,h=get(s=s,concursos_reto=reto,filter_action="Filtrar"); check("filtro por reto",emails(h)==[A],str(emails(h)))
_,h=get(s=s,desde="2025-03-01",hasta="2025-12-31",filter_action="Filtrar"); check("rango de fechas",emails(h)==[B],str(emails(h)))
_,h=get(s=s,desde="2026-01-01",filter_action="Filtrar"); check("solo desde",emails(h)==[C],str(emails(h)))
_,h=get(s=s,hasta="2025-01-15",filter_action="Filtrar"); check("hasta incluye ese día",emails(h)==[A],str(emails(h)))
_,h=get(s=s,desde="x'; DROP",filter_action="Filtrar"); check("fecha inválida se ignora",len(emails(h))==3)
_,h=get(s=s,desde="2025-03-01",concursos_reto=reto,filter_action="Filtrar"); check("filtros combinados: sin resultados",emails(h)==[])
_,h=get(s=s,desde="2025-03-01"); check("paginación/valor conservado",'value="2025-03-01"' in h)
_,h=get(s=s); n=re.search(r'name="_wpnonce" value="([^"]+)"',h).group(1)
u,h2=get(s=s,action="concursos_baja",_wpnonce=n,**{"votante[]":[ids[A],ids[B]]})
check("baja: aviso",'2 votantes ya no recibirán avisos' in H.unescape(h2),u[-60:])
check("baja aplicada",sql(f"SELECT SUM(suscrito) FROM wp_concursos_votantes WHERE id IN ({ids[A]},{ids[B]})")=="0")
check("baja conserva filtros",f"s={urllib.parse.quote(s)}" in u or "s=" in u)
try:
    get(s=s,action="concursos_eliminar",_wpnonce="malo",**{"votante[]":[ids[C]]}); ok=False
except urllib.error.HTTPError as e: ok=e.code==403
check("nonce falso: 403",ok and sql(f"SELECT COUNT(*) FROM wp_concursos_votantes WHERE id={ids[C]}")=="1")
u,h3=get(s=s,action="concursos_eliminar",action2="concursos_eliminar",_wpnonce=n,**{"votante[]":[ids[A],ids[C]]})
check("eliminar: aviso",'2 votantes y sus votos eliminados' in H.unescape(h3))
check("eliminados y sus votos",sql(f"SELECT COUNT(*) FROM wp_concursos_votantes WHERE id IN ({ids[A]},{ids[C]})")=="0" and sql(f"SELECT COUNT(*) FROM wp_concursos_votos WHERE votante_id={ids[A]}")=="0")
check("el otro sigue",sql(f"SELECT COUNT(*) FROM wp_concursos_votantes WHERE id={ids[B]}")=="1")
_,h4=get(action="concursos_baja",_wpnonce=n); check("sin selección no falla","0 votantes" in H.unescape(h4) or "Votantes" in h4)
sql(f"DELETE FROM wp_concursos_votantes WHERE email LIKE '%{run}@test.example.com'")
print("restantes de prueba:",sql(f"SELECT COUNT(*) FROM wp_concursos_votantes WHERE email LIKE '%{run}@test.example.com'"))
print("\nFALLOS:",fallos if fallos else "ninguno");sys.exit(1 if fallos else 0)
