"""Subida de fotos: 5 MB, WebP de hasta 2048 px y sin original."""
import os
import base64,json,re,subprocess,sys,uuid,urllib.error,urllib.parse,urllib.request
BASE="http://localhost:8080";PROJECT = os.path.abspath(os.path.join(os.path.dirname(__file__), "..", ".."))
fallos=[]
def check(n,ok,d=""):
    print("OK   " if ok else "FALLO",n,d)
    if not ok: fallos.append(n)
def sh(*c): return subprocess.run(c,cwd=PROJECT,capture_output=True,text=True).stdout.strip()
def wp(*a): return sh("docker","compose","run","--rm","-q","wpcli",*a)
def fetch(u,d=None,h=None):
    try:
        r=urllib.request.urlopen(urllib.request.Request(u,data=d,headers=h or {})); return r.status,r.geturl(),r.read().decode(errors="replace")
    except urllib.error.HTTPError as e: return e.code,u,e.read().decode(errors="replace")
def generar(nombre,objetivo):
    """Foto real recomprimida hasta acercarse al peso objetivo, o ruido si hace falta superarlo."""
    php=f'''$r=wp_remote_get("https://picsum.photos/id/1018/5000/3333",array("timeout"=>90));$im=imagecreatefromstring(wp_remote_retrieve_body($r));
$p="/var/www/html/wp-content/uploads/{nombre}";$obj={objetivo};
foreach(array(100,98,96,94,92,90,86,82) as $q){{imagejpeg($im,$p,$q);clearstatcache();if(filesize($p)<=$obj)break;}}
if(filesize($p)<$obj*0.55){{$w=imagesx($im);$h=imagesy($im);for($i=0;$i<900000;$i++){{imagesetpixel($im,mt_rand(0,$w-1),mt_rand(0,$h-1),mt_rand(0,16777215));}}foreach(array(100,98,96,94,92,90,86,82,76,70) as $q){{imagejpeg($im,$p,$q);clearstatcache();if(filesize($p)<=$obj)break;}}}}
echo filesize($p);'''
    n=int(wp("eval",php)); data=subprocess.run(["docker","compose","exec","-T","wordpress","cat",f"wp-content/uploads/{nombre}"],cwd=PROJECT,capture_output=True).stdout
    subprocess.run(["docker","compose","exec","-T","wordpress","rm",f"wp-content/uploads/{nombre}"],cwd=PROJECT)
    return data
print(sh("make","demo").splitlines()[-1])
R=wp("post","list","--post_type=concurso_reto","--name=reto-de-prueba-luz-y-sombra","--field=ID")
wp("post","meta","update",R,"_concursos_max_fotos","6")
pid=wp("post","list","--post_type=concurso_inscrito","--title=Javier Ruiz","--meta_key=_concursos_reto",f"--meta_value={R}","--field=ID")
token=wp("eval",f"echo Concursos_Participante::issue_token({pid});")
link=f"{BASE}/?concursos_envio={token}"
def subir(data,nombre="foto.jpg",tipo="image/jpeg"):
    page=fetch(link)[2]; n=re.search(r'name="_wpnonce" value="([^"]+)"',page).group(1)
    b=uuid.uuid4().hex
    body=f'--{b}\r\nContent-Disposition: form-data; name="_wpnonce"\r\n\r\n{n}\r\n--{b}\r\nContent-Disposition: form-data; name="titulo"\r\n\r\nPrueba\r\n--{b}\r\nContent-Disposition: form-data; name="foto"; filename="{nombre}"\r\nContent-Type: {tipo}\r\n\r\n'.encode()+data+f'\r\n--{b}--\r\n'.encode()
    _,f,_x=fetch(link,body,{"Content-Type":f"multipart/form-data; boundary={b}"})
    return urllib.parse.parse_qs(urllib.parse.urlparse(f).query).get("resultado",["?"])[0]
foto=generar("t4.jpg",4900000); print(f"    foto de prueba: {len(foto)/1048576:.2f} MB")
check("la foto de prueba pesa más de 2 MB y menos de 5",2*1048576<len(foto)<=5242880,str(len(foto)))
check("sube",subir(foto)=="subida")
ent=wp("post","list","--post_type=concurso_entrada","--meta_key=_concursos_participante",f"--meta_value={pid}","--field=ID","--orderby=ID","--order=DESC","--posts_per_page=1")
aid=wp("post","meta","get",ent,"_thumbnail_id")
info=json.loads(wp("eval",f'''$f=get_attached_file({aid});$m=wp_get_attachment_metadata({aid});$d=dirname($f);$b=preg_replace('/-scaled$/','',pathinfo($f,PATHINFO_FILENAME));$fs=array_values(array_filter(scandir($d),function($x)use($b){{return strpos($x,$b)===0;}}));$t=0;foreach($fs as $x){{$t+=filesize("$d/$x");}}echo wp_json_encode(array("archivo"=>basename($f),"ancho"=>$m["width"],"alto"=>$m["height"],"mime"=>get_post_mime_type({aid}),"sizes"=>array_keys($m["sizes"]),"original"=>isset($m["original_image"]),"orig_path"=>wp_get_original_image_path({aid})===$f,"backup"=>(bool)get_post_meta({aid},"_wp_attachment_backup_sizes",true),"ficheros"=>$fs,"total"=>$t));'''))
print("   ",info)
check("WebP",info["mime"]=="image/webp" and info["archivo"].endswith(".webp"))
check("lado mayor 2048",max(info["ancho"],info["alto"])==2048)
check("solo 2 tamaños",sorted(info["sizes"])==["medium_large","thumbnail"],str(info["sizes"]))
check("original descartado",not info["original"] and info["orig_path"] and not info["backup"] and not any(x.endswith((".jpg",".jpeg")) for x in info["ficheros"]),str(info["ficheros"]))
check("3 archivos en disco",len(info["ficheros"])==3,str(len(info["ficheros"])))
check("ocupa menos de 1 MB",info["total"]<1048576,f'{info["total"]/1048576:.2f} MB')
h=fetch(BASE+"/retos/reto-de-prueba-luz-y-sombra/")[2]
g=re.findall(r'data-grande="([^"]+)"',h)
check("el modal usa la copia de 2048",any(info["archivo"] in x for x in g))
url=[x for x in g if info["archivo"] in x][0]; st,_,_=fetch(url); check("la copia se sirve",st==200,str(st))
relleno=b"".join(b"\xff\xfe\xff\xff"+b"x"*65533 for _ in range(20))
big=foto[:2]+relleno+foto[2:]; print(f"    foto grande: {len(big)/1048576:.2f} MB")
check("la foto grande supera 5 MB",len(big)>5242880,str(len(big)))
check("más de 5 MB se rechaza",subir(big)=="foto")
html=fetch(link+"&resultado=foto")[2]; check("mensaje 5 MB","5 MB" in html and "10 MB" not in html)
def inscribir(data):
    u=BASE+"/inscripcion-retos/";page=fetch(u)[2];n=re.search(r'name="_wpnonce" value="([^"]+)"',page).group(1)
    b=uuid.uuid4().hex;mail=f"foto{b[:6]}@example.com"
    campos={"action":"concursos_inscribir","_wpnonce":n,"reto":R,"nombre":"Con Foto "+b[:4],"email":mail,"instagram":"","facebook":"","privacidad":"1"}
    body=b"".join(f'--{b}\r\nContent-Disposition: form-data; name="{k}"\r\n\r\n{v}\r\n'.encode() for k,v in campos.items())+f'--{b}\r\nContent-Disposition: form-data; name="foto"; filename="avatar.jpg"\r\nContent-Type: image/jpeg\r\n\r\n'.encode()+data+f'\r\n--{b}--\r\n'.encode()
    _,f,_x=fetch(BASE+"/wp-admin/admin-post.php",body,{"Content-Type":f"multipart/form-data; boundary={b}","Referer":u})
    return urllib.parse.parse_qs(urllib.parse.urlparse(f).query).get("concursos_inscripcion",["?"])[0],"Con Foto "+b[:4]
c,nom=inscribir(foto); check("foto de perfil: sube",c=="ok",c)
ins=wp("post","list","--post_type=concurso_inscrito",f"--title={nom}","--field=ID"); av=wp("post","meta","get",ins,"_thumbnail_id")
inf=json.loads(wp("eval",f'$m=wp_get_attachment_metadata({av});echo wp_json_encode(array("mime"=>get_post_mime_type({av}),"orig"=>isset($m["original_image"]),"ok"=>file_exists(get_attached_file({av}))));'))
check("foto de perfil: WebP y sin original",inf=={"mime":"image/webp","orig":False,"ok":True},str(inf))
c,_=inscribir(big); check("foto de perfil de más de 5 MB se rechaza",c=="foto",c)
n=wp("eval","echo count(get_intermediate_image_sizes());"); check("otros medios sin filtros",int(n)>=6 and wp("eval","echo has_filter('wp_editor_set_quality') ? 1 : 0;")=="0",n)
print(sh("make","demo","FASE=votacion").splitlines()[-1])
print("\nFALLOS:",fallos if fallos else "ninguno");sys.exit(1 if fallos else 0)
