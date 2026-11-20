"""Retos importados: tools/importar_retos.php y los puntos importados de cada foto."""
import os
import http.cookiejar, json, re, subprocess, sys, time, urllib.parse, urllib.request, uuid
BASE="http://localhost:8080"; MAILPIT="http://localhost:8025"; PROJECT = os.path.abspath(os.path.join(os.path.dirname(__file__), "..", ".."))
P="wp-content/plugins/foncholuis-art-challenge-manager"
fallos=[]; run=uuid.uuid4().hex[:6]
def check(n,ok,d=""):
    print("OK   " if ok else "FALLO",n,d)
    if not ok: fallos.append(n)
def wp(*a): return subprocess.run(["docker","compose","run","--rm","-q","wpcli",*a],cwd=PROJECT,capture_output=True,text=True).stdout.strip()
def correos(): return json.loads(urllib.request.urlopen(MAILPIT+"/api/v1/messages?limit=1").read())["total"]

# Fotos de prueba en la biblioteca de medios: una imagen generada por WordPress.
img=os.path.join(PROJECT,f".e2e-{run}.png")
subprocess.run(["docker","compose","run","--rm","-q","wpcli","eval",f"$i=imagecreatetruecolor(40,30);imagepng($i,'{P}/.e2e-{run}.png');"],cwd=PROJECT)
adjuntos=[int(wp("media","import",f"{P}/.e2e-{run}.png",f"--title=e2e-{run}-{i}","--porcelain")) for i in range(5)]
os.remove(img)

datos={"personas":{"Uno":{"instagram":"uno_ig"},"Dos":{},"Tres":{},"Cuatro":{}},
 "retos":[{"clave":f"e2e-{run}","titulo":f"Importado {run}","mes":"2025-10","imagen":adjuntos[0],"max_fotos":2,"descripcion":"<p>x</p>",
  "fotos":[{"persona":"Uno","adjunto":adjuntos[0],"puntos":3},{"persona":"Dos","adjunto":adjuntos[1],"puntos":2},
           {"persona":"Tres","adjunto":adjuntos[2],"puntos":1},{"persona":"Uno","adjunto":adjuntos[3],"puntos":0},{"persona":"Cuatro","adjunto":adjuntos[4],"puntos":0}]}]}
json.dump(datos,open(os.path.join(PROJECT,f".e2e-{run}.json"),"w"))
open(os.path.join(PROJECT,f".e2e-{run}.emails.txt"),"w").write(f"Uno | uno{run}@example.com\n")
reto=None
try:
    antes=correos()
    out=wp("eval-file",f"{P}/tools/importar_retos.php",f"{P}/.e2e-{run}.json",f"{P}/.e2e-{run}.emails.txt","publicar")
    check("importa el reto publicado","publish" in out and "4 participantes, 5 fotos" in out,out)
    check("podio según los puntos importados","1.º Uno · 2.º Dos · 3.º Tres" in out,out)
    reto=int(re.search(r"#(\d+)",out).group(1))
    out2=wp("eval-file",f"{P}/tools/importar_retos.php",f"{P}/.e2e-{run}.json")
    check("dos veces: no lo duplica","ya existe" in out2,out2)
    time.sleep(1.5)
    check("no envía ningún email",correos()==antes,f"{correos()-antes} nuevos")

    info=json.loads(wp("eval",f"""$s=Concursos_Reto::get_settings({reto});
$ps=get_posts(array('post_type'=>'concurso_inscrito','post_status'=>'any','numberposts'=>-1,'meta_key'=>'_concursos_reto','meta_value'=>{reto}));
$em=array(); foreach($ps as $p){{ $em[$p->post_title]=get_post_meta($p->ID,'_concursos_email',true); }}
echo wp_json_encode(array('fase'=>Concursos_Reto::status($s),'avisar'=>(int)$s['avisar'],'avisado'=>(bool)get_post_meta({reto},'_concursos_avisado',true),'emails'=>$em,'activos'=>Concursos_Participante::count_for_reto({reto},'activo')));"""))
    check("reto finalizado, sin aviso y marcado como avisado",info["fase"]=="finalizado" and info["avisar"]==0 and info["avisado"],str(info))
    check("emails: el de la lista y uno ficticio para el resto",info["emails"].get("Uno")==f"uno{run}@example.com" and info["emails"].get("Dos")=="dos@sin-email.invalid",str(info["emails"]))
    check("participantes aprobados",info["activos"]==4,str(info["activos"]))

    # Sin votos no se muestra un 0: esas fotos y personas van debajo de las tablas.
    h=urllib.request.urlopen(BASE+f"/?p={reto}").read().decode()
    tabla=h.split('class="pc-tabla"')[1].split("</table>")[0] if 'class="pc-tabla"' in h else ""
    rejilla=h.split('class="pc-participaron__fotos"')[1].split("</ul>")[0] if "pc-participaron__fotos" in h else ""
    check("tabla del reto: solo las fotos con puntos",tabla.count("<tr>")==5 and "Cuatro" not in tabla,str(tabla.count("<tr>")))
    check("rejilla del reto: las fotos sin puntos, solo con el nombre",rejilla.count("<li")==2 and "Cuatro" in rejilla and "Uno" in rejilla and "pc-foto__titulo" not in rejilla,str(rejilla.count("<li")))
    personas=h.split('class="pc-participaron__personas"')[1].split("</ul>")[0] if "pc-participaron__personas" in h else ""
    general=h.split("pc-clasificacion__datos")[1].split("</table>")[0] if "pc-clasificacion__datos" in h else ""
    check("clasificación general: sin puntos va debajo",'"Cuatro"' not in general and "Cuatro</" not in general and "Cuatro" in personas and "Uno" not in personas)
    check("título de la rejilla traducido","También participaron" in h)

    # La caja de la foto: cambiar los puntos cambia el podio.
    fotos=json.loads(wp("eval",f"echo wp_json_encode(wp_list_pluck(Concursos_Entrada::find(array('reto'=>{reto})),'ID'));"))
    tres=[f for f in fotos if wp("post","meta","get",str(f),"_concursos_puntos_importados")=="1"][0]
    op=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
    op.open(BASE+"/wp-login.php",urllib.parse.urlencode({"log":"admin","pwd":"admin","wp-submit":"x","testcookie":"1"}).encode())
    h=op.open(BASE+f"/wp-admin/post.php?post={tres}&action=edit").read().decode()
    check("caja de puntos importados en la foto",'name="concursos_puntos_importados"' in h and 'value="1"' in h)
    campos=dict(re.findall(r'<input type="hidden" id="[^"]*" name="([^"]+)" value="([^"]*)"',h))
    campos.update(dict(re.findall(r'name="(_wpnonce|concursos_importados_nonce)" value="([^"]+)"',h)))
    campos.update({"action":"editpost","post_ID":str(tres),"post_type":"concurso_entrada","post_title":"Foto de Tres","concursos_puntos_importados":"5","save":"Actualizar"})
    op.open(BASE+"/wp-admin/post.php",urllib.parse.urlencode(campos).encode())
    ganadora=wp("eval",f"$g=Concursos_Vista::ganadora({reto},Concursos_Reto::get_settings({reto})); echo $g?$g->ID:0;")
    check("con 5 puntos pasa a ganar",ganadora==str(tres),f"{ganadora} vs {tres}")
    check("la columna de puntos los suma",wp("eval",f"echo Concursos_Votos::count_entrada({tres});")=="5")

    # Las fotos son copias: borrar el reto importado no toca los adjuntos originales.
    copias=json.loads(wp("eval",f"echo wp_json_encode(array_map('get_post_thumbnail_id',wp_list_pluck(Concursos_Entrada::find(array('reto'=>{reto})),'ID')));"))
    check("usa copias de las fotos",len(copias)==5 and not set(copias)&set(adjuntos),str(copias))
    for i in wp("post","list","--post_type=concurso_entrada,concurso_inscrito","--post_status=any","--meta_key=_concursos_reto",f"--meta_value={reto}","--field=ID").split():
        wp("post","delete",i,"--force")
    portada=wp("eval",f"echo get_post_thumbnail_id({reto});")
    wp("post","delete",str(reto),"--force"); reto=None
    if portada and portada!="0" and int(portada) not in adjuntos: wp("post","delete",portada,"--force")
    quedan=wp("post","list","--post_type=attachment","--post__in="+",".join(map(str,adjuntos)),"--format=count")
    check("al borrarlo, los originales siguen ahí",quedan=="5",quedan)
    for c in copias: wp("post","delete",str(c),"--force")
finally:
    if reto:
        for i in wp("post","list","--post_type=concurso_entrada,concurso_inscrito","--post_status=any","--meta_key=_concursos_reto",f"--meta_value={reto}","--field=ID").split():
            wp("post","delete",i,"--force")
        wp("post","delete",str(reto),"--force")
    for a in adjuntos: wp("post","delete",str(a),"--force")
    for f in (f".e2e-{run}.json",f".e2e-{run}.emails.txt"):
        try: os.remove(os.path.join(PROJECT,f))
        except FileNotFoundError: pass

print("\nFALLOS:",fallos if fallos else "ninguno");sys.exit(1 if fallos else 0)
