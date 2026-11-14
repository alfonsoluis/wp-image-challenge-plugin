"""Aviso de reto nuevo: solo a las listas de un reto privado y nada con «avisar» desactivado."""
import os
import atexit,base64,http.cookiejar,json,re,subprocess,sys,time,urllib.error,urllib.parse,urllib.request,uuid
from html.parser import HTMLParser
BASE="http://localhost:8080";MAILPIT="http://localhost:8025";PROJECT = os.path.abspath(os.path.join(os.path.dirname(__file__), "..", ".."))
fallos=[];run=uuid.uuid4().hex[:6]
def check(n,ok,d=""):
    print("OK   " if ok else "FALLO",n,d)
    if not ok: fallos.append(n)
def wp(*a): return subprocess.run(["docker","compose","run","--rm","-q","wpcli",*a],cwd=PROJECT,capture_output=True,text=True).stdout.strip()
def sql(q): return wp("db","query",q,"--skip-column-names")

# Contraseña de aplicación de usar y tirar para la REST API.
nombre=f"e2e-avisos-{run}"
clave=wp("user","application-password","create","admin",nombre,"--porcelain")
atexit.register(lambda: wp("user","application-password","delete","admin",wp("user","application-password","list","admin",f"--name={nombre}","--field=uuid")))
AUTH="Basic "+base64.b64encode(f"admin:{clave}".encode()).decode()
def rest(metodo,ruta,datos=None):
    r=urllib.request.Request(BASE+"/wp-json/wp/v2/"+ruta,data=None if datos is None else json.dumps(datos).encode(),method=metodo,headers={"Authorization":AUTH,"Content-Type":"application/json"})
    try:
        with urllib.request.urlopen(r) as x: return x.status,json.loads(x.read())
    except urllib.error.HTTPError as e: return e.code,json.loads(e.read() or b"null")

# Suscritos: uno en cada lista, uno fuera, y dos de las listas que no deben recibir nada.
P,V,F,N,Q=(f"{x}{run}@example.com" for x in ("part","vota","fuera","nosus","pend"))
ahora=int(wp("eval","echo time();"))
sql("INSERT INTO wp_concursos_votantes (email,estado,suscrito,creado) VALUES "+",".join(f"('{m}','{e}',{s},{ahora})" for m,e,s in ((P,"confirmado",1),(V,"confirmado",1),(F,"confirmado",1),(N,"confirmado",0),(Q,"pendiente",1))))
atexit.register(lambda: sql(f"DELETE FROM wp_concursos_votantes WHERE email LIKE '%{run}@example.com'"))
fechas=dict(zip(("inicio","fin_envios","fin_votacion","cierre"),json.loads(wp("eval","echo wp_json_encode(array_map(function($s){return wp_date('Y-m-d\\TH:i',time()+$s);},array(-3600,86400,172800,259200)));"))))

retos=[]
atexit.register(lambda: [wp("post","delete",str(i),"--force") for i in retos])
def crear(letra,ajustes,status="draft"):
    st,r=rest("POST","concurso_reto",{"title":f"Aviso {letra} {run}","status":status,"concursos":{**fechas,**ajustes}})
    if st==201: retos.append(r["id"])
    return st,r
def publicar(i): return rest("POST",f"concurso_reto/{i}",{"status":"publish"})[0]
def destinatarios(letra):
    q=urllib.parse.quote(f'subject:"Nuevo reto: Aviso {letra} {run}"')
    ms=json.loads(urllib.request.urlopen(f"{MAILPIT}/api/v1/search?query={q}&limit=1000").read())["messages"]
    return sorted(t["Address"].lower() for m in ms if m["Subject"]==f"Nuevo reto: Aviso {letra} {run}" for t in m["To"])

# A: las dos listas privadas, creado como borrador y publicado después.
st,a=crear("A",{"privado_participantes":True,"emails_participantes":f"{P}, {N}","privado_votantes":True,"emails_votantes":f"{V.upper()}; {Q}"})
check("A: creado como borrador",st==201 and a["status"]=="draft",str(st))
check("A: la API devuelve avisar=true por defecto",a.get("concursos",{}).get("avisar") is True,str(a.get("concursos",{}).get("avisar")))
check("A: publicado",publicar(a["id"])==200)
# B: solo la votación es privada, publicado en una sola petición.
st,b=crear("B",{"privado_votantes":True,"emails_votantes":V},"publish")
check("B: creado ya publicado",st==201 and b["status"]=="publish",str(st))
# C: la lista solo tiene a alguien que no está suscrito.
st,c=crear("C",{"privado_participantes":True,"emails_participantes":N})
publicar(c["id"])
# D: abierto, con el aviso desactivado.
st,d=crear("D",{"avisar":False})
check("D: la API guarda avisar=false",st==201 and d["concursos"]["avisar"] is False,str(st))
publicar(d["id"])
# E: abierto, como siempre.
st,e=crear("E",{})
publicar(e["id"])
# Lista privada vacía: la API lo rechaza con el mensaje del panel.
st,r=rest("POST",f"concurso_reto/{c['id']}",{"concursos":{"privado_votantes":True,"emails_votantes":""}})
check("lista privada vacía: 400 concursos_ajustes_invalidos",st==400 and r.get("code")=="concursos_ajustes_invalidos" and "nadie podrá votar" in r.get("message",""),f"{st} {r.get('code')}")

time.sleep(1.5)
check("A: solo los suscritos de las dos listas",destinatarios("A")==sorted([P,V]),str(destinatarios("A")))
check("B: solo la lista de votantes",destinatarios("B")==[V],str(destinatarios("B")))
check("C: nadie de la lista está suscrito: ningún aviso",destinatarios("C")==[],str(destinatarios("C")))
check("D: avisar=false: ningún aviso",destinatarios("D")==[],str(destinatarios("D")))
suscritos=int(sql("SELECT COUNT(*) FROM wp_concursos_votantes WHERE suscrito=1 AND estado='confirmado'"))
de=destinatarios("E")
check("E: reto abierto: todos los suscritos confirmados",len(de)==suscritos and {P,V,F}<=set(de) and N not in de and Q not in de,f"{len(de)} de {suscritos}")
marcas=json.loads(wp("eval",f"echo wp_json_encode(array_map(function($i){{return (bool) get_post_meta($i,'_concursos_avisado',true);}},array({c['id']},{d['id']})));"))
check("C y D quedan marcados como avisados",marcas==[True,True],str(marcas))

# D otra vez: activar el aviso, despublicar y publicar no avisa más tarde.
rest("POST",f"concurso_reto/{d['id']}",{"status":"draft","concursos":{"avisar":True}})
publicar(d["id"])
time.sleep(1)
check("D: republicar con avisar=true no envía nada",destinatarios("D")==[],str(destinatarios("D")))

# Metabox: la casilla sale marcada en un reto nuevo, y sin marcar no avisa.
class Form(HTMLParser):
    def __init__(s): super().__init__(); s.i=False; s.f={}
    def handle_starttag(s,t,a):
        a=dict(a)
        if t=="form" and a.get("id")=="post": s.i=True
        elif s.i and t=="input" and a.get("type")=="hidden" and a.get("name"): s.f[a["name"]]=a.get("value","")
    def handle_endtag(s,t):
        if t=="form": s.i=False
admin=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
admin.open(BASE+"/wp-login.php",urllib.parse.urlencode({"log":"admin","pwd":"admin","wp-submit":"Acceder","testcookie":"1"}).encode())
pg=admin.open(BASE+"/wp-admin/post-new.php?post_type=concurso_reto").read().decode()
check("metabox: casilla marcada por defecto",re.search(r'name="concursos\[avisar\]" value="1"\s+checked',pg) is not None and "Avisar por email a los suscritos al publicar" in pg)
f=Form(); f.feed(pg); x=dict(f.f)
for k in ("aa","mm","jj","hh","mn"): x[k]=x.get("hidden_"+k,"")
x.update({"post_title":f"Aviso M {run}","content":"x","publish":"Publicar","concursos[modo]":"abierto","concursos[lugares]":"1","concursos[max_fotos]":"1",**{f"concursos[{k}]":v for k,v in fechas.items()}})
retos.append(int(x["post_ID"]))
h=admin.open(BASE+"/wp-admin/post.php",urllib.parse.urlencode(x).encode()).read().decode()
time.sleep(1)
check("metabox: publicado sin la casilla",wp("post","get",x["post_ID"],"--field=post_status")=="publish")
check("metabox: sin la casilla no avisa",destinatarios("M")==[],str(destinatarios("M")))
check("metabox: indica que ya no se avisará","ya se publicó una vez" in h and not re.search(r'name="concursos\[avisar\]" value="1"\s+checked',h))

print("\nFALLOS:",fallos if fallos else "ninguno");sys.exit(1 if fallos else 0)
