"""Vista de la pantalla Fotos: el cambio solo se guarda desde los botones (con nonce)."""
import os
import html as H,http.cookiejar,re,subprocess,sys,urllib.parse,urllib.request
BASE="http://localhost:8080";PROJECT = os.path.abspath(os.path.join(os.path.dirname(__file__), "..", ".."))
fallos=[]
def check(n,ok,d=""):
    print("OK   " if ok else "FALLO",n,d)
    if not ok: fallos.append(n)
def wp(*a): return subprocess.run(["docker","compose","run","--rm","-q","wpcli",*a],cwd=PROJECT,capture_output=True,text=True).stdout.strip()
op=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
op.open(BASE+"/wp-login.php",urllib.parse.urlencode({"log":"admin","pwd":"admin","wp-submit":"x","testcookie":"1"}).encode())
LISTA=BASE+"/wp-admin/edit.php?post_type=concurso_entrada"
uid=wp("user","get","admin","--field=ID")
def vista(): return wp("user","meta","get",uid,"concursos_vista_fotos")
antes=vista()
wp("user","meta","update",uid,"concursos_vista_fotos","tabla")

h=op.open(LISTA+"&vista=rejilla").read().decode()
check("sin nonce: no cambia la vista guardada",vista()=="tabla" and "concursos-vista-rejilla" not in h,vista())
h=op.open(LISTA+"&vista=rejilla&concursos_vista_nonce=malo").read().decode()
check("nonce falso: no la cambia",vista()=="tabla",vista())

h=op.open(LISTA).read().decode()
enlace=re.search(r'href="([^"]*vista=rejilla[^"]*)"',h)
check("los botones llevan nonce",enlace is not None and "concursos_vista_nonce=" in enlace.group(1))
h=op.open(urllib.parse.urljoin(LISTA,H.unescape(enlace.group(1)))).read().decode()
check("con el botón: cambia y se recuerda",vista()=="rejilla" and "concursos-vista-rejilla" in h,vista())
h=op.open(LISTA).read().decode()
check("se mantiene al volver sin parámetros","concursos-vista-rejilla" in h)

if antes: wp("user","meta","update",uid,"concursos_vista_fotos",antes)
else: wp("user","meta","delete",uid,"concursos_vista_fotos")
print("\nFALLOS:",fallos if fallos else "ninguno");sys.exit(1 if fallos else 0)
