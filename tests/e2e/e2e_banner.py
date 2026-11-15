"""Banner de retos: shortcode [concursos_banner] y bloque foncholuis/banner-retos."""
import os
import re, subprocess, sys, urllib.error, urllib.request
BASE="http://localhost:8080"; PROJECT = os.path.abspath(os.path.join(os.path.dirname(__file__), "..", ".."))
fallos=[]
def check(n,ok,d=""):
    print("OK   " if ok else "FALLO",n,d)
    if not ok: fallos.append(n)
def sh(*c): return subprocess.run(c,cwd=PROJECT,capture_output=True,text=True).stdout.strip()
def wp(*a): return sh("docker","compose","run","--rm","-q","wpcli",*a)
def get(u):
    try:
        r=urllib.request.urlopen(u); return r.status,r.read().decode()
    except urllib.error.HTTPError as e: return e.code,e.read().decode()

# La demo en votación crea un reto en votación y dos terminados.
print(sh("make","demo","FASE=votacion").splitlines()[-1])

# Página de un reto: el banner de retos pasados va en la columna lateral, sin ese reto.
terminados = wp("eval", "foreach(Concursos_Reto::all(array('publish')) as $r){ if('finalizado'===$r['status']) echo $r['post']->ID,'|',get_permalink($r['post']),'|',get_the_title($r['post']),\"\\n\"; }").splitlines()
check("hay dos retos terminados", len(terminados)>=2, str(len(terminados)))
rid, url, titulo = terminados[0].split("|", 2)
st, p = get(url)
columna = p.split('class="pc-columna"', 1)[1].split('<nav ', 1)[0] if 'class="pc-columna"' in p else ""
check("reto: columna lateral con el cuadro y el banner", '<aside class="pc-lateral"' in columna and 'concursos-banner--lateral' in columna)
banner = columna.split('concursos-banner--lateral', 1)[-1].split('</aside>', 1)[0]
check("reto: el banner no presenta el propio reto", 'pc-banner--pasados' in banner and titulo not in banner and 'El último' in banner, titulo)
check("reto: carga columna.js", "assets/js/columna.js" in p)

contenido = "\n".join([
    '<!-- wp:shortcode -->[concursos_banner]<!-- /wp:shortcode -->',
    '<!-- wp:shortcode -->[concursos_banner modo="votar"]<!-- /wp:shortcode -->',
    '<!-- wp:shortcode -->[concursos_banner modo="participar"]<!-- /wp:shortcode -->',
    '<!-- wp:foncholuis/banner-retos {"modo":"pasados","estilo":"editorial"} /-->',
])
pid = wp("post","create","--post_type=page","--post_status=publish","--post_title=Prueba banner e2e",f"--post_content={contenido}","--porcelain")
try:
    st,p = get(BASE+f"/?page_id={pid}")
    check("página 200", st==200, str(st))
    bloques = p.split('class="concursos-banner"')[1:]
    check("cuatro banners", len(bloques)==4, str(len(bloques)))

    aleatorio = bloques[0]
    check("aleatorio: varias variantes y el script", "data-pc-banner-aleatorio" in aleatorio.split(">")[0] and "assets/js/banner.js" in p)
    visibles = len(re.findall(r'<aside (?![^>]*hidden)[^>]*class="pc pc-banner', aleatorio.split('class="concursos-banner"')[0]))
    check("aleatorio: solo una variante visible sin JS", visibles==1, str(visibles))

    votar = bloques[1]
    check("votar: variante de votación", 'pc-banner--votar' in votar and 'En votación' in votar)
    check("votar: mosaico de fotos", 'pc-banner__mosaico' in votar and votar.count('pc-banner__celda')>=1)
    check("votar: enlaza a las fotos del reto", re.search(r'href="[^"]+#pc-fotos"', votar) is not None)
    check("votar: sin sizes=auto", 'sizes="auto' not in votar)

    participar = bloques[2]
    primera = participar.split('</aside>')[0]
    if 'pc-banner--participar' in primera:
        check("participar: reto abierto con su botón", 'pc-chip--envios' in primera and 'pc-boton' in primera)
    else:
        check("participar sin reto abierto: usa otra variante", 'pc-banner--' in primera)

    pasados = bloques[3]
    check("bloque: pasados con sello y fondo oscuro", 'pc-banner--pasados' in pasados and 'pc-sello' in pasados)
    check("bloque: estilo editorial", 'data-pc-estilo="editorial"' in pasados)
    check("bloque: enlaza a los retos pasados", '#pc-terminados' in pasados)
finally:
    wp("post","delete",pid,"--force")

print("FALLOS:",fallos if fallos else "ninguno"); sys.exit(1 if fallos else 0)
