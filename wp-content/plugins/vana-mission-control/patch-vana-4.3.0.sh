#!/bin/bash
# ============================================================
# Vana Mission Control — Patch v4.3.0 (sem perl)
# Dependências: python3, sed, php, wp-cli
# Aplicar: chmod +x patch-vana-4.3.0.sh && ./patch-vana-4.3.0.sh
# ============================================================

set -euo pipefail

PLUGIN_DIR="/home/u419701790/domains/vanamadhuryamdaily.com/public_html/beta_html/wp-content/plugins/vana-mission-control"
THEME_DIR="/home/u419701790/domains/vanamadhuryamdaily.com/public_html/beta_html/wp-content/themes/astra-child"
WP_PATH="/home/u419701790/domains/vanamadhuryamdaily.com/public_html/beta_html"
BACKUP_DIR="/home/u419701790/backups/vana-patch-$(date +%Y%m%d-%H%M%S)"
LOG="$BACKUP_DIR/patch.log"

RED='\033[0;31m'; GREEN='\033[0;32m'; YELLOW='\033[1;33m'; BLUE='\033[0;34m'; NC='\033[0m'
log()  { echo -e "${BLUE}[PATCH]${NC} $1" | tee -a "$LOG"; }
ok()   { echo -e "${GREEN}[OK]${NC}    $1" | tee -a "$LOG"; }
warn() { echo -e "${YELLOW}[WARN]${NC}  $1" | tee -a "$LOG"; }
fail() { echo -e "${RED}[FAIL]${NC}  $1" | tee -a "$LOG"; exit 1; }

echo ""
echo "╔══════════════════════════════════════════════════════╗"
echo "║   Vana Mission Control — Patch v4.3.0               ║"
echo "║   Engine: python3 (sem perl)                        ║"
echo "╚══════════════════════════════════════════════════════╝"
echo ""

# Verifica python3
command -v python3 &>/dev/null || fail "python3 não encontrado"
command -v php     &>/dev/null || fail "php não encontrado"

[[ -d "$PLUGIN_DIR" ]] || fail "Plugin não encontrado: $PLUGIN_DIR"
[[ -d "$THEME_DIR"  ]] || fail "Tema não encontrado: $THEME_DIR"

mkdir -p "$BACKUP_DIR"
log "Backup em: $BACKUP_DIR"
log "Log: $LOG"


# ════════════════════════════════════════════════════════════
#  PATCH 1 — sangha-moments.php
#  Fix ternário PHP8 + guard do_shortcode
# ════════════════════════════════════════════════════════════
SANGHA="$THEME_DIR/templates/visit/parts/sangha-moments.php"
log "PATCH 1 — $SANGHA"
[[ -f "$SANGHA" ]] || fail "Não encontrado: $SANGHA"
cp "$SANGHA" "$BACKUP_DIR/sangha-moments.php.bak"
ok "Backup criado"

python3 << PYEOF
import re, sys

filepath = "$SANGHA"

with open(filepath, 'r', encoding='utf-8') as f:
    content = f.read()

original = content

# ── Fix 1a: badge_label ternário encadeado ────────────────
# Aceita qualquer whitespace/newline entre as partes
content = re.sub(
    r'\\\$badge_label\s*=\s*\(\\\$first_ext\s*!==\s*\'\'\)\s*\?\s*\\\$lbl_video_b\s*'
    r':\s*\(\\\$first_image\s*!==\s*\'\'\)\s*\?\s*\\\$lbl_photo_b\s*:\s*\\\$lbl_msg_b\s*;',
    r'\$badge_label = (\$first_ext !== \'\')\n'
    r'    ? \$lbl_video_b\n'
    r'    : ((\$first_image !== \'\') ? \$lbl_photo_b : \$lbl_msg_b);',
    content, flags=re.DOTALL
)

# Fix alternativo caso não tenha parênteses na expressão original
content = re.sub(
    r'(\\\$badge_label\s*=\s*)\(\\\$first_ext\s*!==\s*\'\'\)\s*\?\s*\\\$lbl_video_b\s*'
    r'\n\s*:\s*\(\\\$first_image\s*!==\s*\'\'\)\s*\?\s*\\\$lbl_photo_b\s*\n\s*:\s*\\\$lbl_msg_b\s*;',
    r'\$badge_label = (\$first_ext !== \'\')\n'
    r'    ? \$lbl_video_b\n'
    r'    : ((\$first_image !== \'\') ? \$lbl_photo_b : \$lbl_msg_b);',
    content, flags=re.DOTALL
)

# ── Fix 1b: badge_icon ternário encadeado ─────────────────
content = re.sub(
    r'\\\$badge_icon\s*=\s*\(\\\$first_ext\s*!==\s*\'\'\)\s*\?\s*\'dashicons-video-alt3\'\s*'
    r'\n\s*:\s*\(\\\$first_image\s*!==\s*\'\'\)\s*\?\s*\'dashicons-format-image\'\s*'
    r'\n\s*:\s*\'dashicons-format-quote\'\s*;',
    r"\$badge_icon = (\$first_ext !== '')\n"
    r"    ? 'dashicons-video-alt3'\n"
    r"    : ((\$first_image !== '') ? 'dashicons-format-image' : 'dashicons-format-quote');",
    content, flags=re.DOTALL
)

# ── Fix 1c: guard do_shortcode ────────────────────────────
# Substitui o bloco <div id="form-oferenda"...> ... </div>
old_form = re.compile(
    r'<div id="form-oferenda"[^>]*>\s*<\?php\s*'
    r'echo do_shortcode\(\s*sprintf\(\s*'
    r'\'?\[vana_oferenda_form[^\]]*\]\'?[^)]*\)\s*\);\s*\?>\s*</div>',
    re.DOTALL
)

new_form = """<div id="form-oferenda" style="margin-top:40px;">
    <?php
    if (
        shortcode_exists('vana_oferenda_form') &&
        class_exists('Vana_Submission_CPT')
    ) {
        echo do_shortcode(
            sprintf(
                '[vana_oferenda_form visit_id="%d" lang="%s"]',
                (int) \$visit_id,
                esc_attr(\$lang)
            )
        );
    } elseif (defined('WP_DEBUG') && WP_DEBUG) {
        echo '<!-- [vana_oferenda_form] indisponivel neste contexto -->';
    }
    ?>
</div>"""

if old_form.search(content):
    content = old_form.sub(new_form, content, count=1)
    print("Fix 1c (guard shortcode) aplicado")
else:
    print("Fix 1c SKIP — padrão do_shortcode não encontrado (já corrigido?)")

if content != original:
    with open(filepath, 'w', encoding='utf-8') as f:
        f.write(content)
    print("PATCH 1 — arquivo salvo")
else:
    print("PATCH 1 SKIP — nenhuma alteração necessária")
PYEOF

php -l "$SANGHA" >> "$LOG" 2>&1 \
    && ok "PATCH 1 — sintaxe PHP OK" \
    || { cp "$BACKUP_DIR/sangha-moments.php.bak" "$SANGHA"; fail "PATCH 1 — sintaxe inválida, backup restaurado"; }


# ════════════════════════════════════════════════════════════
#  PATCH 2 — Remove API legada
# ════════════════════════════════════════════════════════════
LEGACY="$PLUGIN_DIR/api/class-vana-checkin-api.php"
log "PATCH 2 — Remover API legada"

if [[ -f "$LEGACY" ]]; then
    cp "$LEGACY" "$BACKUP_DIR/class-vana-checkin-api.LEGADO.bak"
    rm "$LEGACY"
    ok "PATCH 2 — API legada removida"
else
    warn "PATCH 2 — Já removida (skip)"
fi


# ════════════════════════════════════════════════════════════
#  PATCH 3 — vana-mission-control.php
#  + requires v2
#  + fix init_hooks (CPTs via plugins_loaded)
#  + version bump
# ════════════════════════════════════════════════════════════
MAIN="$PLUGIN_DIR/vana-mission-control.php"
log "PATCH 3 — $MAIN"
[[ -f "$MAIN" ]] || fail "Não encontrado: $MAIN"
cp "$MAIN" "$BACKUP_DIR/vana-mission-control.php.bak"

python3 << PYEOF
import re

filepath = "$MAIN"

with open(filepath, 'r', encoding='utf-8') as f:
    content = f.read()

original = content

# ── Fix 3a: version bump ──────────────────────────────────
content = content.replace("define('VANA_MC_VERSION', '4.2.4')", "define('VANA_MC_VERSION', '4.3.0')")
content = content.replace('Version: 4.2.4', 'Version: 4.3.0')
print("Fix 3a (version bump) aplicado")

# ── Fix 3b: inserir requires v2 ──────────────────────────
ANCHOR = "require_once VANA_MC_PATH . 'includes/class-vana-submission-cpt.php';"
INSERT = """require_once VANA_MC_PATH . 'includes/class-vana-image-processor.php'; // v2
require_once VANA_MC_PATH . 'includes/class-vana-r2-client.php';        // v2"""

if 'class-vana-image-processor.php' not in content:
    content = content.replace(ANCHOR, ANCHOR + '\n' + INSERT)
    print("Fix 3b (requires v2) inseridos")
else:
    print("Fix 3b SKIP — requires v2 já existem")

# ── Fix 3c: mover CPTs para plugins_loaded ────────────────
# Remove chamadas diretas
has_direct_visit = re.search(r'^\s+Vana_Visit_CPT::init\(\);\s*$', content, re.MULTILINE)
has_direct_sub   = re.search(r'^\s+Vana_Submission_CPT::init\(\);\s*$', content, re.MULTILINE)

if has_direct_visit or has_direct_sub:
    # Remove as linhas diretas
    content = re.sub(r'^\s+Vana_Visit_CPT::init\(\);\n', '', content, flags=re.MULTILINE)
    content = re.sub(r'^\s+Vana_Submission_CPT::init\(\);\n', '', content, flags=re.MULTILINE)

    # Insere após o hook init_components
    HOOK_ANCHOR = "add_action('plugins_loaded', [\$this, 'init_components'], 10);"
    HOOK_INSERT = """
        add_action('plugins_loaded', function () {
            Vana_Visit_CPT::init();
            Vana_Submission_CPT::init();
        }, 5); // prioridade 5 — antes do init_components"""

    if HOOK_ANCHOR in content:
        content = content.replace(HOOK_ANCHOR, HOOK_ANCHOR + HOOK_INSERT, 1)
        print("Fix 3c (CPTs via plugins_loaded) aplicado")
    else:
        print("Fix 3c WARN — âncora do hook não encontrada")
else:
    print("Fix 3c SKIP — CPTs já no plugins_loaded")

# ── Fix 3d: render_submission_box → schema v2 ─────────────
old_box = re.compile(
    r'public function render_submission_box\(\$post\): void \{.*?'
    r'echo \'</div>\';\s*\}',
    re.DOTALL
)

new_box = r'''public function render_submission_box(\WP_Post $post): void {
        $name     = get_post_meta($post->ID, '_sender_display_name', true) ?: 'Anônimo';
        $msg      = (string) get_post_meta($post->ID, '_message',      true);
        $subtype  = (string) get_post_meta($post->ID, '_subtype',      true) ?: 'devotee';
        $time     = (int)    get_post_meta($post->ID, '_submitted_at', true);
        $city     = (string) get_post_meta($post->ID, '_vana_public_user_city', true);
        $date_str = $time ? wp_date('d/m/Y \à\s H:i', $time) : 'Desconhecida';

        // Schema v2 com fallback v1
        $media_items = [];
        $raw = get_post_meta($post->ID, '_media_items', true);
        if (!empty($raw)) {
            $media_items = is_array($raw) ? $raw : (json_decode($raw, true) ?? []);
        } else {
            $img_v1 = (string) get_post_meta($post->ID, '_image_url',    true);
            $ext_v1 = (string) get_post_meta($post->ID, '_external_url', true);
            if ($img_v1) $media_items[] = ['type' => 'image', 'url' => $img_v1,  'subtype' => 'devotee', 'status' => 'approved'];
            if ($ext_v1) $media_items[] = ['type' => 'video', 'url' => $ext_v1,  'subtype' => 'devotee', 'status' => 'approved'];
        }

        echo '<div style="background:#f8fafc;padding:20px;border-radius:8px;border:1px solid #e2e8f0;">';
        echo '<p><strong>Data:</strong> '      . esc_html($date_str) . '</p>';
        echo '<p><strong>Devoto(a):</strong> ' . esc_html($name)     . '</p>';
        echo '<p><strong>Subtype:</strong> '   . esc_html($subtype)  . '</p>';
        if ($city) echo '<p><strong>Cidade:</strong> ' . esc_html($city) . '</p>';

        if ($msg) {
            echo '<hr style="margin:15px 0;border:0;border-top:1px solid #cbd5e1;">';
            echo '<p><strong>Mensagem:</strong></p>';
            echo '<div style="background:#fff;padding:15px;border:1px solid #cbd5e1;border-radius:6px;font-size:14px;line-height:1.6;">' . nl2br(esc_html($msg)) . '</div>';
        }

        if (!empty($media_items)) {
            echo '<hr style="margin:15px 0;border:0;border-top:1px solid #cbd5e1;">';
            echo '<p><strong>Mídias (' . count($media_items) . '):</strong></p>';
            echo '<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(120px,1fr));gap:10px;">';
            foreach ($media_items as $item) {
                $type   = $item['type']    ?? 'image';
                $url    = esc_url($item['url'] ?? '');
                $status = $item['status']  ?? 'pending';
                $sub    = $item['subtype'] ?? '';
                $color  = match($status) {
                    'approved' => '#15803d',
                    'rejected' => '#b91c1c',
                    default    => '#d97706',
                };
                echo '<div style="border:1px solid #e2e8f0;border-radius:8px;overflow:hidden;background:#fff;">';
                if ($type === 'image' && $url) {
                    echo '<img src="' . $url . '" style="width:100%;height:80px;object-fit:cover;display:block;" loading="lazy">';
                } else {
                    echo '<div style="height:80px;display:flex;align-items:center;justify-content:center;background:#f1f5f9;font-size:24px;">📹</div>';
                }
                echo '<div style="padding:4px 6px;font-size:11px;">';
                echo '<span style="background:' . $color . ';color:#fff;border-radius:4px;padding:1px 5px;font-size:10px;font-weight:700;">' . esc_html($status) . '</span>';
                if ($sub) echo ' <small>' . esc_html($sub) . '</small>';
                echo '</div></div>';
            }
            echo '</div>';
        }
        echo '</div>';
    }'''

if old_box.search(content):
    content = old_box.sub(new_box, content, count=1)
    print("Fix 3d (render_submission_box v2) aplicado")
else:
    print("Fix 3d SKIP — método não encontrado (já atualizado?)")

if content != original:
    with open(filepath, 'w', encoding='utf-8') as f:
        f.write(content)
    print("PATCH 3 — arquivo salvo")
else:
    print("PATCH 3 SKIP — nenhuma alteração necessária")
PYEOF

php -l "$MAIN" >> "$LOG" 2>&1 \
    && ok "PATCH 3 — sintaxe PHP OK" \
    || { cp "$BACKUP_DIR/vana-mission-control.php.bak" "$MAIN"; fail "PATCH 3 — sintaxe inválida, backup restaurado"; }


# ════════════════════════════════════════════════════════════
#  VERIFICAÇÕES FINAIS
# ════════════════════════════════════════════════════════════
log "Verificando dependências..."

declare -A DEPS=(
    ["Image Processor v2"]="$PLUGIN_DIR/includes/class-vana-image-processor.php"
    ["R2 Client v2"]="$PLUGIN_DIR/includes/class-vana-r2-client.php"
    ["Checkin API v2"]="$PLUGIN_DIR/includes/class-vana-checkin-api.php"
    ["Oferenda Form"]="$PLUGIN_DIR/templates/oferenda-form.php"
    ["Submission CPT"]="$PLUGIN_DIR/includes/class-vana-submission-cpt.php"
)

MISSING=0
for label in "${!DEPS[@]}"; do
    f="${DEPS[$label]}"
    if [[ -f "$f" ]]; then
        ok "  ✓ $label"
    else
        warn "  ✗ $label → FALTANDO: $f"
        MISSING=$((MISSING + 1))
    fi
done

[[ ! -f "$PLUGIN_DIR/api/class-vana-checkin-api.php" ]] \
    && ok "  ✓ API legada removida" \
    || warn "  ✗ API legada ainda existe!"


# ════════════════════════════════════════════════════════════
#  WP-CLI flush + debug log
# ════════════════════════════════════════════════════════════
if command -v wp &>/dev/null; then
    log "WP-CLI flush..."
    wp rewrite flush --path="$WP_PATH" --allow-root >> "$LOG" 2>&1 \
        && ok "Rewrite rules flushed" || warn "wp rewrite flush falhou"
    wp cache flush --path="$WP_PATH" --allow-root >> "$LOG" 2>&1 \
        && ok "Cache limpo" || warn "wp cache flush falhou (skip)"

    log "Verificando classes via WP-CLI..."
    wp eval "
        \$checks = [
            'shortcode_exists vana_oferenda_form' => shortcode_exists('vana_oferenda_form'),
            'class Vana_Submission_CPT'            => class_exists('Vana_Submission_CPT'),
            'class Vana_Image_Processor'           => class_exists('Vana_Image_Processor'),
            'class Vana_R2_Client'                 => class_exists('Vana_R2_Client'),
            'class Vana_Checkin_API'               => class_exists('Vana_Checkin_API'),
        ];
        foreach (\$checks as \$k => \$v) {
            echo (\$v ? '[OK] ' : '[MISS] ') . \$k . PHP_EOL;
        }
    " --path="$WP_PATH" --allow-root 2>&1 | tee -a "$LOG"
else
    warn "WP-CLI não disponível — verifique manualmente"
fi


# ════════════════════════════════════════════════════════════
#  RELATÓRIO
# ════════════════════════════════════════════════════════════
echo ""
echo "╔══════════════════════════════════════════════════════╗"
echo "║   RELATÓRIO FINAL                                   ║"
echo "╠══════════════════════════════════════════════════════╣"
echo "║  P1 sangha-moments.php  — ternário + guard          ║"
echo "║  P2 API legada          — removida                  ║"
echo "║  P3 vana-mission-ctrl   — requires + init + box v2  ║"
echo "╠══════════════════════════════════════════════════════╣"
if [[ $MISSING -eq 0 ]]; then
echo "║  Status: ✅ COMPLETO                                ║"
else
echo "║  Status: ⚠️  $MISSING arquivo(s) faltando (upload SFTP) ║"
fi
echo "║  Backups: $BACKUP_DIR  ║"
echo "╚══════════════════════════════════════════════════════╝"
echo ""

if [[ $MISSING -gt 0 ]]; then
    warn "Arquivos para upload via SFTP:"
    [[ ! -f "$PLUGIN_DIR/includes/class-vana-image-processor.php" ]] && \
        warn "  → includes/class-vana-image-processor.php"
    [[ ! -f "$PLUGIN_DIR/includes/class-vana-r2-client.php" ]] && \
        warn "  → includes/class-vana-r2-client.php"
    [[ ! -f "$PLUGIN_DIR/templates/oferenda-form.php" ]] && \
        warn "  → templates/oferenda-form.php"
fi
