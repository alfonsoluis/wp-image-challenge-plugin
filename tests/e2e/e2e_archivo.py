"""Estado archivado y aviso único de nuevos retos."""
import os
import http.cookiejar,json,re,subprocess,sys,time,urllib.error,urllib.parse,urllib.request,uuid
from html.parser import HTMLParser
BASE="http://localhost:8080";MAILPIT="http://localhost:8025";PROJECT = os.path.abspath(os.path.join(os.path.dirname(__file__), "..", ".."))
fallos=[];run=uuid.uuid4().hex[:6];titulo=f"Archivo {run}";mail=f"sus{run}@example.com"
def check(n,ok,d=""):
    print("OK   " if ok else "FALLO",n,d)
    if not ok: fallos.append(n)
def wp(*a): return subprocess.run(["docker","compose","run","--rm","-q","wpcli",*a],cwd=PROJECT,capture_output=True,text=True).stdout.strip()
def session(): return urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
def fetch(op,u,d=None):
    try:
        r=op.open(urllib.request.Request(u,data=d)); return r.status,r.geturl(),r.read().decode(errors="replace")
    except urllib.error.HTTPError as e: return e.code,u,e.read().decode(errors="replace")
class F(HTMLParser):
    def __init__(s): super().__init__(); s.i=False; s.f={}
    def handle_starttag(s,t,a):
        a=dict(a)
        if t=="form" and a.get("id")=="post": s.i=True
        elif s.i and t=="input" and a.get("type")=="hidden" and a.get("name"): s.f[a["name"]]=a.get("value","")
    def handle_endtag(s,t):
        if t=="form": s.i=False
admin=session(); fetch(admin,BASE+"/wp-login.php",urllib.parse.urlencode({"log":"admin","pwd":"admin","wp-submit":"Acceder","testcookie":"1"}).encode())
ahora=int(wp("eval","echo time();"))
import datetime as dt
def d(s): return dt.datetime.utcfromtimestamp(ahora+s+3600).strftime("%Y-%m-%dT%H:%M")
base={"post_title":titulo,"content":"x","concursos[modo]":"abierto","concursos[lugares]":"1","concursos[inicio]":d(-60),"concursos[fin_envios]":d(86400),"concursos[fin_votacion]":d(172800),"concursos[cierre]":d(259200)}
def enviar(url,extra):
    _,_,pg=fetch(admin,url); p=F(); p.feed(pg); x=dict(p.f)
    for k in ("aa","mm","jj","hh","mn"): x[k]=x.get("hidden_"+k,"")
    x.update(base); x.update(extra)
    return x["post_ID"],pg,fetch(admin,BASE+"/wp-admin/post.php",urllib.parse.urlencode(x).encode())[2]
wp("db","query",f"INSERT INTO wp_concursos_votantes (email,estado,suscrito,creado) VALUES ('{mail}','confirmado',1,{ahora})")
def avisos():
    ms=json.loads(fetch(urllib.request.build_opener(),MAILPIT+"/api/v1/messages?limit=500")[2])["messages"]
    return len([m for m in ms if m["To"][0]["Address"]==mail and m["Subject"]==f"Nuevo reto: {titulo}"])
def st(i): return wp("post","get",i,"--field=post_status")
def vis(i): return fetch(session(),BASE+f"/?post_type=concurso_reto&p={i}")[0]
def portada(): return titulo in fetch(session(),BASE+"/retos/")[2]
i,pg,_=enviar(BASE+"/wp-admin/post-new.php?post_type=concurso_reto",{"concursos_archivar":"1","save":"Guardar"})
check("el formulario ofrece archivar","Archivar este reto" in pg)
check("creado como archivado",st(i)=="concurso_archivado",st(i))
check("archivado: no se ve",vis(i)==404,str(vis(i)))
check("archivado: no está en la portada",not portada())
check("archivado: sin aviso",avisos()==0)
_,_,lst=fetch(admin,BASE+"/wp-admin/edit.php?post_type=concurso_reto&post_status=concurso_archivado")
check("filtro Archivados en la lista",titulo in lst and "Archivado" in lst)
_,_,pn=fetch(admin,BASE+"/wp-admin/edit.php?post_type=concurso_reto&page=concursos-panel"); check("aparece en el panel",titulo in pn and "Archivado" in pn)
_,pg2,_=enviar(BASE+f"/wp-admin/post.php?post={i}&action=edit",{"concursos_archivar":"1","save":"Guardar"})
check("guardar lo mantiene archivado",st(i)=="concurso_archivado" and "no se muestra en el sitio" in pg2,st(i))
wp("post","update",i,"--post_status=draft"); check("borrador: no se ve",vis(i)==404,str(vis(i))); check("borrador: sin aviso",avisos()==0)
wp("post","update",i,"--post_status=concurso_archivado")
x,pg3,_=enviar(BASE+f"/wp-admin/post.php?post={i}&action=edit",{"concursos_archivar":"1","publish":"Publicar"})
check("Publicar lo publica",st(i)=="publish",st(i))
check("publicado: se ve y sale en portada",vis(i) in (200,301) and fetch(session(),BASE+"/retos/")[0]==200 and portada(),str(vis(i)))
check("publicado: un aviso",avisos()==1,str(avisos()))
_,pg4,_=enviar(BASE+f"/wp-admin/post.php?post={i}&action=edit",{"concursos_archivar":"1"})
check("publicado se puede archivar",st(i)=="concurso_archivado",st(i))
wp("post","update",i,"--post_status=publish"); check("republicar no reenvía el aviso",avisos()==1,str(avisos()))
wp("post","delete",i,"--force"); wp("db","query",f"DELETE FROM wp_concursos_votantes WHERE email='{mail}'")
print("\nFALLOS:",fallos if fallos else "ninguno")
