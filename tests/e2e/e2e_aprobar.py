"""Aprobar al confirmar el email: el participante recibe su enlace al inscribirse y abrirlo lo aprueba."""
import os
import http.cookiejar, json, re, subprocess, sys, time, urllib.error, urllib.parse, urllib.request, uuid
BASE="http://localhost:8080"; MAILPIT="http://localhost:8025"; PROJECT = os.path.abspath(os.path.join(os.path.dirname(__file__), "..", ".."))
fallos=[]; run=uuid.uuid4().hex[:6]
def check(n,ok,d=""):
    print("OK   " if ok else "FALLO",n,d)
    if not ok: fallos.append(n)
def wp(*a): return subprocess.run(["docker","compose","run","--rm","-q","wpcli",*a],cwd=PROJECT,capture_output=True,text=True).stdout.strip()
def session(): return urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
def fetch(op,u,d=None,h=None):
    try:
        r=op.open(urllib.request.Request(u,data=d,headers=h or {})); return r.status,r.geturl(),r.read().decode(errors="replace")
    except urllib.error.HTTPError as e: return e.code,u,e.read().decode(errors="replace")

dentro,otro,fuera=(f"{x}{run}@example.com" for x in ("dentro","otro","fuera"))
def crear(aprobar):
    return int(wp("eval",f"""$id=wp_insert_post(array('post_type'=>'concurso_reto','post_status'=>'publish','post_title'=>'Aprobar {run} {aprobar}'));
update_post_meta($id,'_concursos_avisado',time());
$s=Concursos_Reto::sanitize(array('modo'=>'abierto','inicio'=>wp_date('Y-m-d\\TH:i',time()-3600),'fin_envios'=>wp_date('Y-m-d\\TH:i',time()+86400),'fin_votacion'=>wp_date('Y-m-d\\TH:i',time()+172800),'cierre'=>wp_date('Y-m-d\\TH:i',time()+259200),
 'max_fotos'=>1,'privado_participantes'=>1,'emails_participantes'=>'{dentro}, {otro}','aprobar_al_confirmar'=>{aprobar}));
Concursos_Reto::save_settings($id,$s); echo $id;"""))
def inscribir(reto,mail):
    op=session(); u=BASE+"/inscripcion-retos/"; page=fetch(op,u)[2]
    n=re.search(r'name="_wpnonce" value="([^"]+)"',page).group(1); b=uuid.uuid4().hex
    fields={"action":"concursos_inscribir","_wpnonce":n,"reto":reto,"nombre":"Persona "+mail[:5],"email":mail,"instagram":"","facebook":"","privacidad":"1"}
    body=b"".join(f'--{b}\r\nContent-Disposition: form-data; name="{k}"\r\n\r\n{v}\r\n'.encode() for k,v in fields.items())+f'--{b}\r\nContent-Disposition: form-data; name="foto"; filename=""\r\nContent-Type: application/octet-stream\r\n\r\n\r\n--{b}--\r\n'.encode()
    _,f,_=fetch(op,BASE+"/wp-admin/admin-post.php",body,{"Content-Type":f"multipart/form-data; boundary={b}","Referer":u})
    return urllib.parse.parse_qs(urllib.parse.urlparse(f).query).get("concursos_inscripcion",["?"])[0]
def buzon(mail):
    time.sleep(1)
    ms=json.loads(fetch(urllib.request.build_opener(),MAILPIT+"/api/v1/messages?limit=500")[2])["messages"]
    return [m for m in ms if m["To"][0]["Address"].lower()==mail]
def texto(m): return json.loads(fetch(urllib.request.build_opener(),f"{MAILPIT}/api/v1/message/{m['ID']}")[2])["Text"]
def estado(reto,mail): return wp("eval",f"$p=get_posts(array('post_type'=>'concurso_inscrito','post_status'=>'any','meta_query'=>array(array('key'=>'_concursos_reto','value'=>{reto}),array('key'=>'_concursos_email','value'=>'{mail}'))));echo $p?get_post_meta($p[0]->ID,'_concursos_estado',true):'ninguno';")

retos=[]
try:
    A=crear(1); retos.append(A)
    check("inscripción: respuesta de siempre",inscribir(A,dentro)=="ok")
    ms=buzon(dentro)
    check("llega «Confirma tu inscripción» con el enlace",len(ms)==1 and ms[0]["Subject"].startswith("Confirma tu inscripción"),str([m["Subject"] for m in ms]))
    t=texto(ms[0]) if ms else ""
    enlace=re.search(r"http://localhost:8080/\?\S+",t)
    check("queda pendiente hasta confirmar",estado(A,dentro)=="pendiente")
    st,_,h=fetch(session(),enlace.group(0)) if enlace else (0,"","")
    check("abrir el enlace: página para enviar fotos",st==200 and 'type="file"' in h,str(st))
    check("abrir el enlace la aprueba",estado(A,dentro)=="activo")
    check("también queda como votante",wp("db","query",f"SELECT estado FROM wp_concursos_votantes WHERE email='{dentro}'","--skip-column-names")=="confirmado")

    # Fuera de la lista: no se inscribe y el email explica el motivo, como siempre.
    check("fuera de la lista: misma respuesta",inscribir(A,fuera)=="ok")
    check("fuera de la lista: no se crea",estado(A,fuera)=="ninguno")
    check("fuera de la lista: no recibe enlace",not any(m["Subject"].startswith("Confirma") for m in buzon(fuera)))

    # Rechazada por el admin: el enlace ya no la aprueba.
    inscribir(A,otro); ms=buzon(otro); e2=re.search(r"http://localhost:8080/\?\S+",texto(ms[0])).group(0)
    pid=wp("eval",f"$p=get_posts(array('post_type'=>'concurso_inscrito','post_status'=>'any','meta_key'=>'_concursos_email','meta_value'=>'{otro}'));echo $p[0]->ID;")
    wp("post","meta","update",pid,"_concursos_estado","inactivo")
    st,_,_=fetch(session(),e2)
    check("si el admin la rechazó, el enlace no la aprueba",st==403 and estado(A,otro)=="inactivo",str(st))

    # Aprobada a mano sin haber abierto el enlace: recibe el email de aprobación con un enlace nuevo.
    tarde=f"tarde{run}@example.com"
    wp("post","meta","update",str(A),"_concursos_emails_participantes",f"{dentro}, {otro}, {tarde}")
    inscribir(A,tarde)
    pid=wp("eval",f"$p=get_posts(array('post_type'=>'concurso_inscrito','post_status'=>'any','meta_key'=>'_concursos_email','meta_value'=>'{tarde}'));echo $p[0]->ID;")
    wp("eval",f"Concursos_Inscripcion::activar({pid});")
    ms=buzon(tarde)
    check("aprobada a mano: le llega el enlace de aprobación",any(m["Subject"].startswith("Ya puedes participar") for m in ms),str([m["Subject"] for m in ms]))
    wp("eval",f"Concursos_Inscripcion::activar({pid});")
    check("aprobarla otra vez no repite el email",len(buzon(tarde))==len(ms))

    # Reenviar el enlace desde la tabla de participantes.
    op=session(); fetch(op,BASE+"/wp-login.php",urllib.parse.urlencode({"log":"admin","pwd":"admin","wp-submit":"x","testcookie":"1"}).encode())
    lista=fetch(op,BASE+f"/wp-admin/edit.php?post_type=concurso_inscrito&concursos_reto={A}")[2]
    enlaces=re.findall(r'href="([^"]*action=concursos_reenviar(?:&|&amp;|&#038;)post=(\d+)[^"]*)"',lista)
    ids={i for _,i in enlaces}
    check("«Reenviar enlace» en activos y no en desactivados",pid in ids and wp("eval",f"$p=get_posts(array('post_type'=>'concurso_inscrito','post_status'=>'any','meta_key'=>'_concursos_email','meta_value'=>'{otro}'));echo $p[0]->ID;") not in ids,str(ids))
    antes=len(buzon(tarde))
    url=[u for u,i in enlaces if i==pid][0].replace("&amp;","&").replace("&#038;","&")
    st,final,h=fetch(op,url if url.startswith("http") else BASE+url)
    check("reenviar: aviso en el panel","Enlace personal reenviado a 1 participante" in h,str(st))
    ms=buzon(tarde)
    check("reenviar: llega un email nuevo con el enlace",len(ms)==antes+1 and ms[0]["Subject"].startswith("Ya puedes participar"))
    nuevo=re.search(r"http://localhost:8080/\?\S+",texto(ms[0])).group(0)
    viejo=re.search(r"http://localhost:8080/\?\S+",texto(ms[1])).group(0)
    check("el enlace nuevo funciona y el viejo no",fetch(session(),nuevo)[0]==200 and fetch(session(),viejo)[0]==404)

    # Sin la opción: como antes, la aprueba el admin.
    B=crear(0); retos.append(B)
    m2=f"b{dentro}"
    wp("post","meta","update",str(B),"_concursos_emails_participantes",m2)
    inscribir(B,m2); ms=buzon(m2)
    check("sin la opción: email de «recibimos» sin enlace",len(ms)==1 and ms[0]["Subject"].startswith("Recibimos") and "localhost:8080/?" not in texto(ms[0]),str([m["Subject"] for m in ms]))
    check("sin la opción: pendiente",estado(B,m2)=="pendiente")

    # El panel tiene la casilla.
    op=session(); fetch(op,BASE+"/wp-login.php",urllib.parse.urlencode({"log":"admin","pwd":"admin","wp-submit":"x","testcookie":"1"}).encode())
    h=fetch(op,BASE+f"/wp-admin/post.php?post={A}&action=edit")[2]
    check("casilla en el panel, marcada",re.search(r'name="concursos\[aprobar_al_confirmar\]" value="1"\s+checked',h) is not None)
finally:
    for r in retos:
        for i in wp("post","list","--post_type=concurso_inscrito","--post_status=any","--meta_key=_concursos_reto",f"--meta_value={r}","--field=ID").split():
            wp("post","delete",i,"--force")
        wp("post","delete",str(r),"--force")
    wp("db","query",f"DELETE FROM wp_concursos_votantes WHERE email LIKE '%{run}@example.com'")

print("\nFALLOS:",fallos if fallos else "ninguno");sys.exit(1 if fallos else 0)
