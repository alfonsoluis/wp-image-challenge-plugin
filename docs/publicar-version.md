# Publicar una versión nueva

Dos destinos: el directorio de **WordPress.org** (lo que ve todo el mundo) y la web
real **afsmadrid.com** (Hostinger). Se hacen por separado.

## 1. Preparar la versión

1. Sube el número en tres sitios, con el mismo valor (`x.y.z`):
   - cabecera `Version:` de `foncholuis-art-challenge-manager.php`
   - `define( 'CONCURSOS_VERSION', 'x.y.z' )` en ese mismo fichero
   - `Stable tag:` de `readme.txt` (y añade la entrada en el apartado de cambios)
2. Si cambiaste textos traducibles: `make i18n`.
3. `make e2e` y comprueba que no hay FALLO.
4. Commit en `main`. Sin líneas de atribución a Claude.

## 2. WordPress.org (automático)

```bash
make release V=x.y.z
```

Comprueba que no hay cambios sin confirmar, que estás en `main` y que las tres
versiones coinciden; sube `main`, crea la etiqueta `x.y.z` y la sube. Al llegar la
etiqueta, GitHub Actions (`.github/workflows/deploy.yml`) publica en el SVN de
WordPress.org: copia el código a `trunk/`, crea `tags/x.y.z` y sincroniza
`.wordpress-org/` con `assets/`.

- Mira la pestaña **Actions** del repositorio: debe salir en verde.
- Los secretos `SVN_USERNAME` y `SVN_PASSWORD` están en GitHub → Settings → Secrets and
  variables → Actions. La contraseña SVN es distinta de la de la cuenta de wordpress.org
  y se cambia en https://profiles.wordpress.org/me/profile/edit/group/3/?screen=svn-password
  (si se cambia, hay que actualizar el secreto).
- El zip y la ficha pública se actualizan en minutos u horas:
  https://wordpress.org/plugins/foncholuis-art-challenge-manager/
- `make banners` regenera los banners de `.wordpress-org/` (se suben solos con la
  siguiente versión).
- Las traducciones no van en el zip (`.gitattributes` las excluye): las sirve
  translate.wordpress.org.

### Si Actions falla: subida manual por SVN

```bash
svn checkout https://plugins.svn.wordpress.org/foncholuis-art-challenge-manager svn
git archive HEAD | tar -x -C svn/trunk          # código sin ficheros de desarrollo
cp .wordpress-org/* svn/assets/                 # solo si cambiaron las imágenes
cd svn && svn add --force trunk assets && svn status
svn commit -m "Versión x.y.z" --username foncholuis
svn cp trunk tags/x.y.z && svn commit -m "Etiqueta x.y.z" --username foncholuis
```

Si borraste ficheros del plugin, usa también `svn rm` para ellos. Los PNG/JPG nuevos de
`assets/` necesitan `svn propset svn:mime-type image/png` (o `image/jpeg`).

## 3. afsmadrid.com (Hostinger, por SSH)

Credenciales en `.env.afs` (gitignored): `SSH_HOST`, `SSH_PORT`, `SSH_USER`. Clave:
`~/.ssh/afsmadrid_ed25519`. Si SSH está desactivado en hPanel, hay que activarlo y
volver a añadir la clave pública. Raíz de WordPress: `domains/afsmadrid.com/public_html`.

```bash
S=$(mktemp -d)
set -a; source .env.afs; set +a
printf 'Host afs\n  HostName %s\n  Port %s\n  User %s\n  IdentityFile ~/.ssh/afsmadrid_ed25519\n  IdentitiesOnly yes\n  BatchMode yes\n  LogLevel ERROR\n' \
  "$SSH_HOST" "$SSH_PORT" "$SSH_USER" > $S/ssh_afs

make zip                                         # foncholuis-art-challenge-manager.zip desde HEAD
ssh -F $S/ssh_afs afs 'cat > /tmp/fach.zip' < foncholuis-art-challenge-manager.zip
ssh -F $S/ssh_afs afs "cd domains/afsmadrid.com/public_html \
  && wp plugin install /tmp/fach.zip --force && rm /tmp/fach.zip \
  && wp cache flush && (wp litespeed-purge all || true) \
  && wp plugin get foncholuis-art-challenge-manager --field=version"
```

(zsh no separa variables con varias opciones: por eso se usa un fichero de config con
`ssh -F`.) Comprueba después alguna página pública del sitio con `curl`.

El zip no lleva `.mo`. Si cambiaron las traducciones, copia
`languages/foncholuis-art-challenge-manager-es_ES.mo` a
`wp-content/languages/plugins/` del servidor.

**Los retos se quedan en borrador salvo que se pida lo contrario:** publicar un reto
envía email a los suscriptores.
