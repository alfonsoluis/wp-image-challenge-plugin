"""Portada pública de retos y perfil de participante."""
import os
import html as H, re, subprocess, sys, urllib.error, urllib.request
BASE="http://localhost:8080"; PROJECT = os.path.abspath(os.path.join(os.path.dirname(__file__), "..", ".."))
fallos=[]
def check(n,ok,d=""):
    print("OK   " if ok else "FALLO",n,d)
    if not ok: fallos.append(n)
def sh(*c): return subprocess.run(c,cwd=PROJECT,capture_output=True,text=True).stdout.strip()
def wp(*a): return sh("docker","compose","run","--rm","-q","wpcli",*a)

# El reto de ejemplo «Reto semanal» (ID 9) no debe pasar por delante del reto de prueba:
# mientras dura la prueba queda como próximo y al terminar recupera sus fechas.
import atexit as _atexit, json as _json
_fechas9 = _json.loads(wp("eval", "echo wp_json_encode(array_map(function($k){return get_post_meta(9,'_concursos_'.$k,true);}, array('inicio','fin_envios','fin_votacion','cierre')));") or "[]")
if len(_fechas9) == 4:
    wp("eval", "$t=time(); foreach(array('inicio'=>5,'fin_envios'=>6,'fin_votacion'=>7,'cierre'=>8) as $k=>$d) update_post_meta(9,'_concursos_'.$k,$t+$d*86400);")
    _atexit.register(lambda: wp("eval", "foreach(array_combine(array('inicio','fin_envios','fin_votacion','cierre'), json_decode('%s')) as $k=>$v) update_post_meta(9,'_concursos_'.$k,$v);" % _json.dumps(_fechas9)))

# Necesita el reto de ejemplo «Luz de otoño» (ID 8) publicado: lo publica y al terminar lo deja como estaba.
import atexit
_estado8 = wp("post", "get", "8", "--field=post_status")
if _estado8 and _estado8 != "publish":
    wp("db", "query", "UPDATE wp_posts SET post_status='publish' WHERE ID=8"); wp("cache", "flush")
    atexit.register(lambda: (wp("db", "query", f"UPDATE wp_posts SET post_status='{_estado8}' WHERE ID=8"), wp("cache", "flush")))
def get(u):
    try:
        r=urllib.request.urlopen(u); return r.status,r.read().decode()
    except urllib.error.HTTPError as e: return e.code,e.read().decode()
def rid(name): return wp("post","list","--post_type=concurso_reto",f"--name={name}","--field=ID")
print(sh("make","demo","FASE=votacion").splitlines()[-1])
st,p=get(BASE+"/retos/")
check("portada 200",st==200,str(st))
check("usa cabecera y pie del tema","wp-site-blocks" in p and p.count("wp-block-template-part")>=2 and "Concursos Dev" in p)
check("estilos cargados","assets/css/public.css" in p)
check("título del documento",re.search(r"<title>Retos · Concursos Dev</title>",H.unescape(p)) is not None,str(re.findall(r"<title>.*?</title>",p)[:1]))
d=p[p.find("concursos-destacado"):p.find("concursos-destacado__datos")]
check("destacado: el reto en curso más reciente","Reto de prueba: luz y sombra" in d)
check("destacado: fase y acción","En votación" in d and "Ver las fotos y votar" in p)
pos=[p.find(t) for t in ("concursos-destacado","Otros retos en curso","Retos anteriores")]
check("orden de secciones",-1 not in pos and pos==sorted(pos),str(pos))
ant=p[p.find("Retos anteriores"):]
check("anteriores listados y finalizados","Reto anterior: reflejos" in ant and "calles vacías" in ant and "Finalizado el" in ant)
check("anteriores por fecha",ant.find("reflejos")<ant.find("calles vacías"))
check("sin votar ni enviar en portada",'name="email"' not in p and "concursos-voto-form" not in p and 'type="file"' not in p)
check("otros incluye Luz de otoño","Reto Luz de oto" in p[pos[1]:pos[2]])
st,x=get(BASE+"/retos/feed/"); check("el feed sigue siendo feed",st==200 and "<rss" in x)
st,r=get(BASE+"/retos/reto-anterior-reflejos/")
check("reto pasado: finalizado sin opciones",st==200 and "Finalizado el" in r and 'name="email"' not in r and "concursos-voto-form" not in r and "Resultados</h2>" in r)
RET=rid("reto-de-prueba-luz-y-sombra")
_,g=get(BASE+"/retos/reto-de-prueba-luz-y-sombra/")
links=dict((H.unescape(n),u) for u,n in re.findall(r'<a href="([^"]*/participante/[^"]+)">([^<]+)</a>',g))
check("galería enlaza perfiles",len(links)==6,str(len(links)))
url=links["Marta López"]; print("    ",url)
st,m=get(url)
check("perfil 200",st==200,str(st))
check("usa el tema","wp-site-blocks" in m)
check("nombre y cifras","Marta López" in m and "3 retos" in m and "4 fotos" in m and "1 puesto" in m, str(re.findall(r'cifras">\s*([^<]+)',m)))
check("enlace a Instagram","https://www.instagram.com/martalopez.foto/" in m)
check("sin email","participante1@example.com" not in m and "example.com" not in m)
sec=dict((H.unescape(t),b) for t,b in re.findall(r'perfil__reto">\s*<h2><a[^>]*>([^<]+)</a></h2>(.*?)</section>',m,re.S))
check("tres retos",len(sec)==3,str(list(sec)))
a=sec.get("Reto anterior: reflejos","")
check("primer lugar y premio","Primer lugar" in a and "Una suscripción de fotografía" in a and a.count("<figure>")==1)
b=sec.get("Reto anterior: calles vacías","")
check("sin puesto donde no ganó","lugar" not in b.replace("concursos-etiqueta","") and "Premio" not in b and b.count("<figure>")==1)
c=sec.get("Reto de prueba: luz y sombra","")
check("reto en curso: fotos sin puesto",c.count("<figure>")==2 and "lugar" not in c)
_,j=get(links["Javier Ruiz"]); check("empate en perfil","Empate en el segundo lugar" in j)
_,cm=get(links["Carmen Díaz"]); check("ganadora con un solo puesto","Primer lugar" in cm and "mochila" in cm)
st,_=get(BASE+"/participante/marta-lopez-0000000000/"); check("perfil inexistente 404",st==404,str(st))
st,_=get(BASE+"/participante/cualquiera/"); check("sin resumen 404",st==404,str(st))
luz=wp("post","list","--post_type=concurso_inscrito","--meta_key=_concursos_reto","--meta_value=8","--meta_compare==","--field=ID","--posts_per_page=50").split()
autora=next(i for i in luz if wp("post","get",i,"--field=post_title").startswith("Autora") and wp("post","meta","get",i,"_concursos_estado")=="activo")
u2=wp("eval",f"echo Concursos_Perfil::url({autora});")
st,ca=get(u2)
check("reto cerrado: no filtra fotos",st==200 and "todavía no se pueden ver" in ca and "<figure>" not in ca and "Atardecer" not in ca,str(st))
pend=wp("post","list","--post_type=concurso_inscrito","--meta_key=_concursos_estado","--meta_value=inactivo","--field=ID","--posts_per_page=1")
if pend:
    st,_=get(wp("eval",f"echo Concursos_Perfil::url({pend});")); check("solo inactivo: 404",st==404,str(st))
ids=wp("post","list","--post_type=concurso_inscrito","--s=Marta","--field=ID","--posts_per_page=10").split()
ids=[i for i in ids if wp("post","get",i,"--field=post_title")=="Marta López"]
wp("eval",f'Concursos_Participante::set_estado({ids[0]},"inactivo");')
_,m2=get(url); check("desactivada en un reto: ese reto desaparece","2 retos" in m2,str(re.findall(r'cifras">\s*([^<]+)',m2)))
for i in ids[1:]: wp("eval",f'Concursos_Participante::set_estado({i},"inactivo");')
st,_=get(url); check("desactivada en todos: 404",st==404,str(st))
# Enlaces simples
wp("rewrite","structure",""); wp("rewrite","flush")
u3=wp("eval",f"echo Concursos_Perfil::url({autora});")
st,_=get(u3); check("sin enlaces permanentes",st==200 and "?concursos_perfil=" in u3,u3)
wp("rewrite","structure","/%postname%/"); wp("rewrite","flush")
st,_=get(u2); check("reglas restauradas",st==200,str(st))
# Tema clásico
o=subprocess.run(["docker","compose","run","--rm","-q","wpcli","theme","install","twentytwentyone","--activate"],cwd=PROJECT,capture_output=True,text=True)
if "twentytwentyone" in wp("theme","list","--status=active","--field=name"):
    st,cl=get(BASE+"/retos/"); check("tema clásico: portada",st==200 and "wp-site-blocks" not in cl and 'id="masthead"' in cl and "concursos-destacado" in cl and "site-footer" in cl.lower(),str(st))
    st,cl=get(BASE+"/retos/reto-anterior-reflejos/"); check("tema clásico: reto",st==200 and 'id="masthead"' in cl and "concursos-cabecera" in cl and "Resultados</h2>" in cl,str(st))
    st,cl=get(u2); check("tema clásico: perfil",st==200 and 'id="masthead"' in cl and "concursos-perfil" in cl,str(st))
    wp("theme","activate","twentytwentyfour"); wp("theme","delete","twentytwentyone")
else:
    print("SIN PROBAR tema clásico:",o.stderr[-200:]); fallos.append("tema clásico sin probar")
print("activo:",wp("theme","list","--status=active","--field=name"))
print(sh("make","demo","FASE=votacion").splitlines()[-1])
print("\nFALLOS:",fallos if fallos else "ninguno"); sys.exit(1 if fallos else 0)
