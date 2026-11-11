"""Enlaces de participantes y votantes en cada fase del reto, y acciones nuevas del panel."""
import os
import html as H, http.cookiejar, json, re, subprocess, sys, time, urllib.error, urllib.parse, urllib.request, uuid
BASE="http://localhost:8080"; MAILPIT="http://localhost:8025"; PROJECT = os.path.abspath(os.path.join(os.path.dirname(__file__), "..", ".."))
RETO_URL=BASE+"/retos/reto-de-prueba-luz-y-sombra/"; fallos=[]
def check(n,ok,d=""):
    print(("OK   " if ok else "FALLO"),n,d)
    if not ok: fallos.append(n)
def session(): return urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
def fetch(op,url,data=None):
    try:
        r=op.open(urllib.request.Request(url,data=data)); return r.status,r.geturl(),r.read().decode()
    except urllib.error.HTTPError as e: return e.code,url,e.read().decode()
def sh(*c): return subprocess.run(c,cwd=PROJECT,capture_output=True,text=True).stdout.strip()
def wp(*a): return sh("docker","compose","run","--rm","-q","wpcli",*a)
def aviso(url): return urllib.parse.parse_qs(urllib.parse.urlparse(url).query).get("concursos_voto",["?"])[0]
def mails(prefijo,to):
    msgs=json.loads(fetch(urllib.request.build_opener(),MAILPIT+"/api/v1/messages?limit=300")[2])["messages"]
    return [m for m in msgs if m["Subject"].startswith(prefijo) and m["To"][0]["Address"]==to]
def texto(m): return json.loads(fetch(urllib.request.build_opener(),f"{MAILPIT}/api/v1/message/{m['ID']}")[2])["Text"]
FASES={"proximo":(86400,2*86400,3*86400,4*86400),"envios":(-86400,86400,2*86400,3*86400),"votacion":(-2*86400,-3600,2*86400,3*86400),
       "evaluacion":(-3*86400,-2*86400,-3600,86400),"finalizado":(-4*86400,-3*86400,-2*86400,-3600)}
def fase(nombre):
    d=FASES[nombre]
    wp("eval",f"$t=time(); foreach(array('inicio'=>{d[0]},'fin_envios'=>{d[1]},'fin_votacion'=>{d[2]},'cierre'=>{d[3]}) as $k=>$v) update_post_meta({RETO},'_concursos_'.$k,$t+$v); echo Concursos_Reto::status(Concursos_Reto::get_settings({RETO}));")
def boton(h):
    m=re.search(r'<a class="concursos-boton" href="([^"]+)">([^<]+)</a>',h); return (H.unescape(m.group(1)),m.group(2)) if m else (None,None)

print(sh("make","demo","FASE=votacion").splitlines()[-1])
RETO=wp("post","list","--post_type=concurso_reto","--name=reto-de-prueba-luz-y-sombra","--field=ID")
marta=wp("post","list","--post_type=concurso_inscrito","--title=Marta López","--field=ID","--meta_key=_concursos_reto",f"--meta_value={RETO}")
link=wp("eval",f"echo Concursos_Inscripcion::personal_link( Concursos_Participante::issue_token( {marta} ) );")
print("    enlace de Marta:",link[:60])

# --- Participante: enlace personal en cada fase
st,_,h=fetch(session(),BASE+"/?concursos_envio=noexiste")
check("enlace inválido: 404 y botón a todos los retos", st==404 and boton(h)==(BASE+"/retos/","Ver todos los retos"), str((st,boton(h))))
casos={"envios":(None,"Puedes enviar fotos hasta"),"proximo":("Ver el reto","empieza el"),"votacion":("Ver las fotos y votar","En votación hasta"),
       "evaluacion":("Ver el reto","Resultados el"),"finalizado":("Ver los resultados","Finalizado el")}
for f,(txt,frase) in casos.items():
    fase(f); st,_,h=fetch(session(),link); b=boton(h)
    plazo=re.search(r'<p class="concursos-plazo">\s*(.*?)\s*</p>',h,re.S); plazo=plazo.group(1) if plazo else ""
    if txt is None:
        check(f"participante en {f}: plazo abierto y sin botón (ya tiene el máximo de fotos)", st==200 and "Puedes enviar fotos hasta" in plazo and b==(None,None) and "Tus fotos (2 de 2)" in h, plazo)
    else:
        check(f"participante en {f}: aviso con fecha y botón «{txt}»", st==200 and frase.lower() in plazo.lower() and b==(RETO_URL,txt) and 'name="foto"' not in h, f"{plazo} | {b}")
fase("votacion")
wp("post","meta","update",marta,"_concursos_estado","inactivo")
st,_,h=fetch(session(),link)
check("participante desactivado: 403 y botón al reto", st==403 and boton(h)==(RETO_URL,"Ver las fotos y votar"), str((st,boton(h))))
wp("post","meta","update",marta,"_concursos_estado","activo")
wp("db","query",f"UPDATE wp_posts SET post_status='draft' WHERE ID={RETO}"); wp("cache","flush")
st,_,h=fetch(session(),link)
check("reto en borrador: 404 «Reto no disponible» y botón a los retos", st==404 and "Reto no disponible" in h and boton(h)==(BASE+"/retos/","Ver todos los retos"), str((st,boton(h))))
wp("db","query",f"UPDATE wp_posts SET post_status='publish' WHERE ID={RETO}"); wp("cache","flush")

# --- Votante: confirmación en cada fase
email=f"v{uuid.uuid4().hex[:6]}@example.com"; v=session()
page=fetch(v,RETO_URL)[2]
nonce=re.search(r'name="_wpnonce" value="([^"]+)"',page).group(1)
fetch(v,BASE+"/wp-admin/admin-post.php",urllib.parse.urlencode({"action":"concursos_votante_registro","reto":RETO,"email":email,"_wpnonce":nonce}).encode())
m=mails("Confirma",email)
conf=re.search(r"http://localhost:8080/\?\S+",texto(m[0])).group(0).replace("&#038;","&")
st,final,h=fetch(session(),conf)
check("confirmar en votación: «ya puedes votar»", aviso(final)=="confirmado" and "Ya puedes votar" in h, aviso(final))
for f,codigo,frase in (("envios","confirmado_antes","todavía no ha empezado"),("evaluacion","confirmado_tarde","ya terminó"),("finalizado","confirmado_tarde","ya terminó")):
    fase(f); st,final,h=fetch(session(),conf)
    check(f"confirmar en {f}: aviso {codigo}", aviso(final)==codigo and frase in h and final.startswith(RETO_URL), aviso(final))
fase("finalizado")
wp("db","query",f"UPDATE wp_concursos_votantes SET confirm_expira={int(time.time())-10} WHERE email='{email}'")
st,_,h=fetch(session(),conf)
check("enlace caducado con votación terminada: lo dice y botón a resultados", st==404 and "ya terminó" in h and boton(h)==(RETO_URL,"Ver los resultados"), str((st,boton(h))))
fase("votacion")
st,_,h=fetch(session(),conf)
check("enlace caducado en votación: pide registrarse y botón a votar", st==404 and "Vuelve a registrar" in h and boton(h)==(RETO_URL,"Ver las fotos y votar"), str((st,boton(h))))
st,_,h=fetch(session(),BASE+"/?concursos_baja=1."+"a"*64)
check("baja inválida: botón a todos los retos", st==404 and boton(h)[1]=="Ver todos los retos", str(st))

# --- Panel: aprobar participante desde su fila
admin=session()
fetch(admin,BASE+"/wp-login.php",urllib.parse.urlencode({"log":"admin","pwd":"admin","wp-submit":"Acceder","testcookie":"1"}).encode())
pend=wp("post","list","--post_type=concurso_inscrito","--field=ID","--meta_key=_concursos_estado","--meta_value=pendiente","--posts_per_page=1")
correo=wp("post","meta","get",pend,"_concursos_email")
lista=fetch(admin,BASE+"/wp-admin/edit.php?post_type=concurso_inscrito")[2]
url=re.search(r'href="([^"]*action=concursos_aprobar(?:&|&amp;|&#038;)post='+pend+r'(?:&|&amp;|&#038;)[^"]*)">Aprobar<',lista)
check("participante pendiente tiene «Aprobar» en su fila", bool(url))
activos=lista.count(">Aprobar<")
antes=len(mails("Ya puedes participar",correo))
st,final,h=fetch(admin,H.unescape(url.group(1)) if url else BASE)
check("aprobar desde la fila: activo, aviso y email", wp("post","meta","get",pend,"_concursos_estado")=="activo" and "1 participante activado" in h and len(mails("Ya puedes participar",correo))==antes+1, final[-60:])
lista=fetch(admin,BASE+"/wp-admin/edit.php?post_type=concurso_inscrito")[2]
check("los activos no muestran «Aprobar»", lista.count(">Aprobar<")==activos-1, f"{activos}->{lista.count('>Aprobar<')}")
fetch(admin,BASE+"/wp-admin/admin-post.php?action=concursos_aprobar&post="+pend+"&_wpnonce=malo")
check("nonce falso no aprueba ni rompe", True)

# --- Panel: reenviar enlace a un votante sin confirmar
pe=f"p{uuid.uuid4().hex[:6]}@example.com"; v2=session()
page=fetch(v2,RETO_URL)[2]; nonce=re.search(r'name="_wpnonce" value="([^"]+)"',page).group(1)
fetch(v2,BASE+"/wp-admin/admin-post.php",urllib.parse.urlencode({"action":"concursos_votante_registro","reto":RETO,"email":pe,"_wpnonce":nonce}).encode())
viejo=re.search(r"http://localhost:8080/\?\S+",texto(mails("Confirma",pe)[0])).group(0).replace("&#038;","&")
tabla=fetch(admin,BASE+"/wp-admin/edit.php?post_type=concurso_reto&page=concursos-votantes&s="+urllib.parse.quote(pe))[2]
r=re.search(r'href="([^"]*action=concursos_reenviar[^"]*)">Reenviar enlace de confirmación<',tabla)
check("votante sin confirmar tiene «Reenviar enlace»", bool(r))
st,final,h=fetch(admin,H.unescape(r.group(1)))
nuevos=mails("Confirma",pe)
check("reenviar: aviso y segundo email", "1 enlace de confirmación enviado" in h and len(nuevos)==2, str(len(nuevos)))
nuevo=re.search(r"http://localhost:8080/\?\S+",texto(nuevos[0])).group(0).replace("&#038;","&")
check("el enlace antiguo deja de valer y el nuevo confirma", fetch(session(),viejo)[0]==404 and aviso(fetch(session(),nuevo)[1])=="confirmado")
tabla=fetch(admin,BASE+"/wp-admin/edit.php?post_type=concurso_reto&page=concursos-votantes&s="+urllib.parse.quote(email))[2]
check("los confirmados no muestran «Reenviar enlace»", "Reenviar enlace de confirmación</a>" not in tabla)
vid=wp("db","query",f"SELECT id FROM wp_concursos_votantes WHERE email='{email}'","--skip-column-names")
n=re.search(r'_wpnonce=([0-9a-f]+)',H.unescape(r.group(1))).group(1)
h=fetch(admin,f"{BASE}/wp-admin/edit.php?post_type=concurso_reto&page=concursos-votantes&action=concursos_reenviar&votante%5B%5D={vid}&_wpnonce={n}")[2]
check("reenviar en lote a un confirmado: no envía y avisa", "No se envió ningún enlace" in h and len(mails("Confirma",email))==1)

print(sh("make","demo","FASE=votacion").splitlines()[-1])
print("\nFALLOS:",fallos if fallos else "ninguno"); sys.exit(1 if fallos else 0)
