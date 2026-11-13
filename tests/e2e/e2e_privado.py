"""Retos privados para participantes y votantes."""
import os
import http.cookiejar,json,re,subprocess,sys,uuid,urllib.error,urllib.parse,urllib.request,datetime as dt,html as H
from html.parser import HTMLParser
BASE="http://localhost:8080";MAILPIT="http://localhost:8025";PROJECT = os.path.abspath(os.path.join(os.path.dirname(__file__), "..", ".."))
MSG="Este concurso es privado, comunícate con los organizadores para más información."
fallos=[];run=uuid.uuid4().hex[:6]
def check(n,ok,d=""):
    print("OK   " if ok else "FALLO",n,d)
    if not ok: fallos.append(n)
def sh(*c): return subprocess.run(c,cwd=PROJECT,capture_output=True,text=True).stdout.strip()
def wp(*a): return sh("docker","compose","run","--rm","-q","wpcli",*a)
def session():
    return urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
def fetch(op,u,d=None,h=None):
    try:
        r=op.open(urllib.request.Request(u,data=d,headers=h or {})); return r.status,r.geturl(),r.read().decode(errors="replace")
    except urllib.error.HTTPError as e: return e.code,u,e.read().decode(errors="replace")
class F(HTMLParser):
    def __init__(s): super().__init__(); s.i=False; s.f={}; s.cb={}
    def handle_starttag(s,t,a):
        a=dict(a)
        if t=="form" and a.get("id")=="post": s.i=True
        elif s.i and t=="input" and a.get("type")=="hidden" and a.get("name"): s.f[a["name"]]=a.get("value","")
        elif s.i and t=="input" and a.get("type")=="checkbox" and (a.get("name") or "").startswith("concursos[privado"): s.cb[a["name"]]="checked" in a
    def handle_endtag(s,t):
        if t=="form": s.i=False
admin=session(); fetch(admin,BASE+"/wp-login.php",urllib.parse.urlencode({"log":"admin","pwd":"admin","wp-submit":"Acceder","testcookie":"1"}).encode())
ahora=int(wp("eval","echo time();"))
def d(s): return dt.datetime.fromtimestamp(ahora+s+3600,dt.timezone.utc).strftime("%Y-%m-%dT%H:%M")
_,_,pg=fetch(admin,BASE+"/wp-admin/post-new.php?post_type=concurso_reto")
p=F(); p.feed(pg)
check("nuevo: participantes privado por defecto",p.cb.get("concursos[privado_participantes]") is True,str(p.cb))
check("nuevo: votantes no privado por defecto",p.cb.get("concursos[privado_votantes]") is False)
check("campos de emails","concursos[emails_participantes]" in pg and "concursos[emails_votantes]" in pg)
x=dict(p.f)
for k in ("aa","mm","jj","hh","mn"): x[k]=x.get("hidden_"+k,"")
tit=f"Privado {run}"
a1,a2=f"uno{run}@example.com",f"dos{run}@example.com"
x.update({"post_title":tit,"content":"x","publish":"Publicar","concursos[modo]":"abierto","concursos[lugares]":"1","concursos[max_fotos]":"1","concursos[inicio]":d(-60),"concursos[fin_envios]":d(86400),"concursos[fin_votacion]":d(172800),"concursos[cierre]":d(259200),
 "concursos[privado_participantes]":"1","concursos[emails_participantes]":f"{a1.upper()}, {a2};malo@@x"})
_,_,h=fetch(admin,BASE+"/wp-admin/post.php",urllib.parse.urlencode(x).encode())
R=x["post_ID"]
check("avisa de emails inválidos","no son válidas" in h and "malo@@x" in h)
check("lista normalizada",wp("post","meta","get",R,"_concursos_emails_participantes")==f"{a1}, {a2}",wp("post","meta","get",R,"_concursos_emails_participantes"))
def inscribir(mail):
    op=session();u=BASE+"/inscripcion-retos/";page=fetch(op,u)[2]
    n=re.search(r'name="_wpnonce" value="([^"]+)"',page).group(1)
    b=uuid.uuid4().hex
    fields={"action":"concursos_inscribir","_wpnonce":n,"reto":R,"nombre":"Persona "+mail[:6],"email":mail,"instagram":"","facebook":"","privacidad":"1"}
    body=b"".join(f'--{b}\r\nContent-Disposition: form-data; name="{k}"\r\n\r\n{v}\r\n'.encode() for k,v in fields.items())+f'--{b}\r\nContent-Disposition: form-data; name="foto"; filename=""\r\nContent-Type: application/octet-stream\r\n\r\n\r\n--{b}--\r\n'.encode()
    _,f,html=fetch(op,BASE+"/wp-admin/admin-post.php",body,{"Content-Type":f"multipart/form-data; boundary={b}","Referer":u})
    return urllib.parse.parse_qs(urllib.parse.urlparse(f).query).get("concursos_inscripcion",["?"])[0],html
c,h=inscribir(f"fuera{run}@example.com")
check("fuera de la lista: rechazado con el mensaje",c=="privado" and MSG in H.unescape(h),c)
check("no se guarda",wp("post","list","--post_type=concurso_inscrito","--meta_key=_concursos_reto",f"--meta_value={R}","--format=count")=="0")
c,_=inscribir(a1); check("en la lista: se inscribe",c=="ok",c)
c,_=inscribir(a2.upper()); check("sin distinguir mayúsculas",c=="ok",c)
pid=wp("post","list","--post_type=concurso_inscrito","--meta_key=_concursos_reto",f"--meta_value={R}","--field=ID").split()
wp("eval",f"foreach(array({','.join(pid)}) as $i) Concursos_Inscripcion::activar($i);")
ms=json.loads(fetch(urllib.request.build_opener(),MAILPIT+"/api/v1/messages?limit=500")[2])["messages"]
m=next(z for z in ms if z["To"][0]["Address"]==a1 and z["Subject"].startswith("Ya puedes"))
link=re.search(r"http://localhost:8080/\?concursos_envio=[a-f0-9]{32}",json.loads(fetch(urllib.request.build_opener(),f"{MAILPIT}/api/v1/message/{m['ID']}")[2])["Text"]).group(0)
check("enlace personal válido",fetch(session(),link)[0]==200)
wp("post","meta","update",R,"_concursos_emails_participantes",a2)
st,_,h=fetch(session(),link); check("sacado de la lista: enlace bloqueado",st==403 and MSG in H.unescape(h),str(st))
# Votantes
print(sh("make","demo","FASE=votacion").splitlines()[-1])
V=wp("post","list","--post_type=concurso_reto","--name=reto-de-prueba-luz-y-sombra","--field=ID")
URL=BASE+"/retos/reto-de-prueba-luz-y-sombra/"
ok=f"voto{run}@example.com";no=f"novoto{run}@example.com"
wp("post","meta","update",V,"_concursos_privado_votantes","1");wp("post","meta","update",V,"_concursos_emails_votantes",ok)
def reg(op,mail):
    page=fetch(op,URL)[2];n=re.search(r'name="_wpnonce" value="([^"]+)"',page).group(1)
    _,f,html=fetch(op,BASE+"/wp-admin/admin-post.php",urllib.parse.urlencode({"action":"concursos_votante_registro","reto":V,"email":mail,"_wpnonce":n}).encode())
    return urllib.parse.parse_qs(urllib.parse.urlparse(f).query).get("concursos_voto",["?"])[0],html
def correos(mail):
    ms=json.loads(fetch(urllib.request.build_opener(),MAILPIT+"/api/v1/messages?limit=500")[2])["messages"]
    return [z for z in ms if z["To"][0]["Address"]==mail and z["Subject"].startswith("Confirma")]
c,h=reg(session(),no); check("votante fuera de la lista: mensaje y sin email",c=="privado" and MSG in H.unescape(h) and not correos(no),c)
v=session(); c,_=reg(v,ok); check("votante en la lista: recibe enlace",c=="revisa",c)
l=re.search(r"http://localhost:8080/\?\S+",json.loads(fetch(urllib.request.build_opener(),f"{MAILPIT}/api/v1/message/{correos(ok)[0]['ID']}")[2])["Text"]).group(0)
h=fetch(v,l)[2]; check("en la lista: ve botones","concursos-voto-form" in h)
nonce=re.search(r'name="action" value="concursos_votar"><input type="hidden" name="_wpnonce" value="([^"]+)"',h).group(1)
fotos=re.findall(r'name="elegir" value="(\d+):1"',h)
def votar():
    _,f,_x=fetch(v,BASE+"/wp-admin/admin-post.php",urllib.parse.urlencode({"action":"concursos_votar","_wpnonce":nonce,"reto":V,"entrada":fotos[0],"op":"votar","puesto":1}).encode())
    return urllib.parse.parse_qs(urllib.parse.urlparse(f).query).get("concursos_voto",["?"])[0]
check("en la lista: vota",votar()=="votado")
wp("post","meta","update",V,"_concursos_emails_votantes","otra@example.com")
h=fetch(v,URL)[2]; check("sacado: sin botones y con mensaje","concursos-voto-form" not in h and MSG in H.unescape(h))
check("sacado: su voto nuevo se rechaza",votar()=="privado")
wp("post","meta","update",V,"_concursos_privado_votantes","0")
check("sin privacidad vuelve a poder",votar()=="votado")
_,_,pg=fetch(admin,BASE+f"/wp-admin/post.php?post={R}&action=edit");p=F();p.feed(pg);check("guardado conserva opciones",p.cb.get("concursos[privado_participantes]") is True and p.cb.get("concursos[privado_votantes]") is False)
print(wp("eval",f"$e=Concursos_Reto::validate(array_merge(Concursos_Reto::get_settings({R}),array('emails_participantes'=>'')));echo count($e);"),"aviso de lista vacía")
for i in pid: wp("post","delete",i,"--force")
wp("post","delete",R,"--force")
print(sh("make","demo","FASE=votacion").splitlines()[-1])
print("\nFALLOS:",fallos if fallos else "ninguno");sys.exit(1 if fallos else 0)
