#!/bin/bash
# patch-sangha-fix.sh — Cirúrgico, sem perl
# Corrige apenas as 2 linhas de ternário encadeado no sangha-moments.php

FILE="/home/u419701790/domains/vanamadhuryamdaily.com/public_html/beta_html/wp-content/themes/astra-child/templates/visit/parts/sangha-moments.php"
BACKUP="/home/u419701790/backups/sangha-$(date +%Y%m%d-%H%M%S).bak"

echo "Backup: $BACKUP"
cp "$FILE" "$BACKUP"

python3 - "$FILE" << 'PYEOF'
import sys

filepath = sys.argv[1]

with open(filepath, 'r', encoding='utf-8') as f:
    lines = f.readlines()

out = []
i = 0
changed = 0

while i < len(lines):
    line = lines[i]

    # ── Detecta início do badge_label ternário ──
    if "$badge_label" in line and "? $lbl_video_b" not in line and "($first_ext" in line:
        # Consome até o ponto-e-vírgula
        block = line
        while ";" not in block and i + 1 < len(lines):
            i += 1
            block += lines[i]
        out.append("      $badge_label = ($first_ext !== '')\n")
        out.append("                    ? $lbl_video_b\n")
        out.append("                    : (($first_image !== '') ? $lbl_photo_b : $lbl_msg_b);\n")
        changed += 1
        i += 1
        continue

    # ── Detecta início do badge_icon ternário ──
    if "$badge_icon" in line and "? 'dashicons-video-alt3'" not in line and "($first_ext" in line:
        block = line
        while ";" not in block and i + 1 < len(lines):
            i += 1
            block += lines[i]
        out.append("      $badge_icon = ($first_ext !== '')\n")
        out.append("                   ? 'dashicons-video-alt3'\n")
        out.append("                   : (($first_image !== '') ? 'dashicons-format-image' : 'dashicons-format-quote');\n")
        changed += 1
        i += 1
        continue

    out.append(line)
    i += 1

if changed > 0:
    with open(filepath, 'w', encoding='utf-8') as f:
        f.writelines(out)
    print(f"OK — {changed} bloco(s) corrigido(s)")
else:
    print("SKIP — padrão não encontrado (já corrigido?)")
PYEOF

echo "Verificando sintaxe..."
php -l "$FILE" && echo "✅ Sintaxe OK" || echo "❌ Erro de sintaxe — restaurando backup" && cp "$BACKUP" "$FILE"
