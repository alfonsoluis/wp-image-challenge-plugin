"""Actualiza languages/photo-challenges-es_ES.po a partir del .pot recién generado.

Conserva las traducciones que ya tiene el .po y añade las de un JSON opcional
con pares [inglés, español] (o [[singular, plural], [singular, plural]]).
Uso: python3 docker/i18n.py [nuevas.json]
"""
import json
import re
import sys

POT = "languages/photo-challenges.pot"
PO = "languages/photo-challenges-es_ES.po"


def unq(s):
    return "".join(re.findall(r'"((?:[^"\\]|\\.)*)"', s))


def dec(s):
    return s.replace("\\n", "\n").replace('\\"', '"').replace("\\\\", "\\")


def esc(s):
    return s.replace("\\", "\\\\").replace('"', '\\"').replace("\n", "\\n")


def bloques(texto):
    return [b for b in texto.split("\n\n") if b.strip()]


def clave(b):
    a = re.search(r"^msgid (.*?)(?=^msgid_plural|^msgstr)", b, re.S | re.M)
    p = re.search(r"^msgid_plural (.*?)(?=^msgstr)", b, re.S | re.M)
    ctx = re.search(r"^msgctxt (.*?)(?=^msgid)", b, re.S | re.M)
    i = dec(unq(a.group(1)))
    k = (i, dec(unq(p.group(1)))) if p else i
    return (dec(unq(ctx.group(1))), k) if ctx else k


def traduccion(b):
    if re.search(r"^msgstr\[", b, re.M):
        return [dec(unq(m)) for m in re.findall(r'^msgstr\[\d\] (.*?)(?=^msgstr\[|\Z)', b, re.S | re.M)]
    m = re.search(r"^msgstr (.*)\Z", b, re.S | re.M)
    return dec(unq(m.group(1))) if m else ""


previas = {}
po_viejo = open(PO, encoding="utf-8").read()
cabecera_po = bloques(po_viejo)[0]
for b in bloques(po_viejo)[1:]:
    t = traduccion(b)
    if t and t != [""] * len(t if isinstance(t, list) else []):
        previas[clave(b)] = t
if len(sys.argv) > 1:
    for k, v in json.load(open(sys.argv[1], encoding="utf-8")):
        previas[tuple(k) if isinstance(k, list) else k] = v

salida = [cabecera_po]
sin = []
for b in bloques(open(POT, encoding="utf-8").read())[1:]:
    k = clave(b)
    v = previas.get(k)
    if not v:
        sin.append(k)
        salida.append(b)
        continue
    if isinstance(k, tuple) and not isinstance(v, str):
        b = re.sub(r"^msgstr\[0\].*\Z", 'msgstr[0] "%s"\nmsgstr[1] "%s"' % (esc(v[0]), esc(v[1])), b, flags=re.S | re.M)
    else:
        b = re.sub(r'^msgstr "".*\Z', lambda _: 'msgstr "%s"' % esc(v), b, flags=re.S | re.M)
    salida.append(b)

open(PO, "w", encoding="utf-8").write("\n\n".join(salida) + "\n")
ignorar = {"Alfonso Rodriguez", "https://github.com/alfonsoluis/wp-image-challenge-plugin"}
sin = [s for s in sin if s not in ignorar]
print("sin traducir:", sin if sin else "ninguna")
