"""El registro de votantes con un email repetido."""
import os
import http.cookiejar, json, re, subprocess, sys, time, urllib.error, urllib.parse, urllib.request, uuid
BASE = "http://localhost:8080"; MAILPIT = "http://localhost:8025"
PROJECT = os.path.abspath(os.path.join(os.path.dirname(__file__), "..", ".."))
RETO_URL = BASE + "/retos/reto-de-prueba-luz-y-sombra/"
fallos = []
def check(n, ok, d=""):
    print("OK   " if ok else "FALLO", n, d)
    if not ok: fallos.append(n)
def wp(*a): return subprocess.run(["docker","compose","run","--rm","-q","wpcli",*a],cwd=PROJECT,capture_output=True,text=True).stdout.strip()
def session(): return urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
def fetch(op,u,d=None):
    try:
        r=op.open(urllib.request.Request(u,data=d)); return r.status,r.geturl(),r.read().decode()
    except urllib.error.HTTPError as e: return e.code,u,e.read().decode()
def registrar(correo):
    op=session(); page=fetch(op,RETO_URL)[2]
    data={"action":"concursos_votante_registro","reto":RETO,"email":correo,"_wpnonce":re.search(r'name="_wpnonce" value="([^"]+)"',page).group(1)}
    st,final,html=fetch(op,BASE+"/wp-admin/admin-post.php",urllib.parse.urlencode(data).encode())
    return urllib.parse.parse_qs(urllib.parse.urlparse(final).query).get("concursos_voto",["?"])[0], html
def correos(to):
    op=urllib.request.build_opener()
    ms=json.loads(fetch(op,MAILPIT+"/api/v1/messages?limit=500")[2])["messages"]
    return [m for m in ms if m["To"][0]["Address"]==to and m["Subject"].startswith("Confirma")]
def enlace(m):
    t=json.loads(fetch(urllib.request.build_opener(),f"{MAILPIT}/api/v1/message/{m['ID']}")[2])["Text"]
    return re.search(r"http://localhost:8080/\?\S+",t).group(0)
RETO=wp("post","list","--post_type=concurso_reto","--name=reto-de-prueba-luz-y-sombra","--field=ID")
mail=f"rep{uuid.uuid4().hex[:6]}@example.com"
def filas(): return wp("db","query",f"SELECT COUNT(*) FROM wp_concursos_votantes WHERE email='{mail}'","--skip-column-names")
c,_=registrar(mail); check("primer registro: revisa",c=="revisa",c)
check("un correo",len(correos(mail))==1)
c,html=registrar(mail); check("repetido enseguida: espera",c=="espera",c)
check("el mensaje lo dice","hace unos minutos" in html)
check("no llega otro correo",len(correos(mail))==1)
c,_=registrar(mail.upper()); check("en mayúsculas cuenta como el mismo",c=="espera" and filas()=="1",c)
viejo=enlace(correos(mail)[0])
ahora=int(wp("eval","echo time();"))
wp("db","query",f"UPDATE wp_concursos_votantes SET confirm_expira={ahora+7*86400-360} WHERE email='{mail}'")
c,_=registrar(mail); check("pasados 5 min se puede reenviar",c=="revisa",c)
ms=correos(mail); check("segundo correo",len(ms)==2,str(len(ms))); check("sigue una sola fila",filas()=="1",filas())
nuevo=enlace(ms[0])
check("enlace anterior invalidado",fetch(session(),viejo)[0]==404)
check("enlace nuevo funciona","concursos_voto=confirmado" in fetch(session(),nuevo)[1])
wp("db","query",f"UPDATE wp_concursos_votantes SET confirm_expira={ahora+7*86400-360} WHERE email='{mail}'")
c,_=registrar(mail); check("ya confirmado, otro navegador: recibe enlace",c=="revisa" and len(correos(mail))==3,c)
check("sigue confirmado",wp("db","query",f"SELECT estado FROM wp_concursos_votantes WHERE email='{mail}'","--skip-column-names")=="confirmado")
print("\nFALLOS:",fallos if fallos else "ninguno"); sys.exit(1 if fallos else 0)
