import os
import base64,html as H,re,subprocess,uuid,urllib.parse,urllib.request
BASE="http://localhost:8080";P=os.path.abspath(os.path.join(os.path.dirname(__file__), "..", ".."))
def wp(*a): return subprocess.run(["docker","compose","run","--rm","-q","wpcli",*a],cwd=P,capture_output=True,text=True).stdout.strip()
PNG=base64.b64decode("iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==")
R=wp("post","list","--post_type=concurso_reto","--name=reto-de-prueba-luz-y-sombra","--field=ID")
fin=wp("post","meta","get",R,"_concursos_fin_envios"); ahora=int(wp("eval","echo time();"))
pid=wp("post","list","--post_type=concurso_inscrito","--title=Diego Moreno","--meta_key=_concursos_reto",f"--meta_value={R}","--field=ID")
tok=wp("eval",f"echo Concursos_Participante::issue_token({pid});"); link=f"{BASE}/?concursos_envio={tok}"
wp("post","meta","update",R,"_concursos_fin_envios",str(ahora+600))
ent=[]
try:
    page=urllib.request.urlopen(link).read().decode()
    print("campo en el formulario:",'name="descripcion"' in page and 'maxlength="500"' in page and 'Descripción (opcional)' in page)
    def subir(d):
        pg=urllib.request.urlopen(link).read().decode(); n=re.search(r'name="_wpnonce" value="([^"]+)"',pg).group(1); b=uuid.uuid4().hex
        body=b"".join(f'--{b}\r\nContent-Disposition: form-data; name="{k}"\r\n\r\n{v}\r\n'.encode() for k,v in {"_wpnonce":n,"titulo":"Con texto","descripcion":d}.items())+f'--{b}\r\nContent-Disposition: form-data; name="foto"; filename="d.png"\r\nContent-Type: image/png\r\n\r\n'.encode()+PNG+f'\r\n--{b}--\r\n'.encode()
        r=urllib.request.urlopen(urllib.request.Request(link,data=body,headers={"Content-Type":f"multipart/form-data; boundary={b}"}))
        return urllib.parse.parse_qs(urllib.parse.urlparse(r.geturl()).query).get("resultado",["?"])[0]
    print("subir:",subir('Linea uno\nLinea "dos" <script>alert(1)</script> & fin '+'x'*600))
    e=wp("post","list","--post_type=concurso_entrada","--meta_key=_concursos_participante",f"--meta_value={pid}","--field=ID","--orderby=ID","--order=DESC","--posts_per_page=1"); ent.append(e)
    ex=wp("post","get",e,"--field=post_excerpt")
    print("guardada, sin etiquetas:", '<script>' not in ex, "| longitud:",len(ex),"| salto conservado:", '\n' in ex)
    h=urllib.request.urlopen(BASE+"/retos/reto-de-prueba-luz-y-sombra/").read().decode()
    m=re.search(r'data-reto-url="[^"]*" data-descripcion="([^"]*)"[^>]*>\s*<img[^>]*?',h[h.find('Con texto')-900:]) 
    a=[x for x in re.findall(r'data-titulo="([^"]*)"[^>]*data-descripcion="([^"]*)"',h) if x[0]=="Con texto"]
    print("atributo escapado:",bool(a) and '<' not in a[0][1] and H.unescape(a[0][1]).startswith('Linea uno'))
finally:
    wp("post","meta","update",R,"_concursos_fin_envios",fin)
    for e in ent: wp("post","delete",e,"--force")
print("restaurado:",wp("post","meta","get",R,"_concursos_fin_envios")==fin)
