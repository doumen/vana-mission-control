#!/usr/bin/env python3
"""
Test Phase 1 remotamente via SSH

Cria teste-locais e executa via WP-CLI eval no servidor
"""
import paramiko
import sys

HOST = '149.62.37.117'
PORT = 65002
USER = 'u419701790'
KEY_PATH = None  # usar password ou key discovery

test_code = '''
<?php
// FASE 1 — Teste dos 4 Estados (remoto)

$base_path = '/home/u419701790/domains/vanamadhuryamdaily.com/public_html/beta_html';
$plugin_path = $base_path . '/wp-content/plugins/vana-mission-control';

// Carregar
require_once $plugin_path . '/inc/vana-stage.php';

// Mock para Vana_Utils
if ( ! class_exists( 'Vana_Utils' ) ) {
    class Vana_Utils {
        public static function pick_i18n_key( $data, $key, $lang = 'pt' ) {
            if ( ! is_array( $data ) ) return '';
            $k = $key . '_' . $lang;
            return (string) ( $data[ $k ] ?? $data[ $key ] ?? '' );
        }
    }
}

// Mock vana_t
if ( ! function_exists( 'vana_t' ) ) {
    function vana_t( $key, $lang = 'pt' ) {
        $strings = [
            'stage.aria'       => $lang === 'en' ? 'Stage' : 'Palco',
            'stage.class'      => $lang === 'en' ? 'Class' : 'Aula',
            'stage.empty'      => $lang === 'en' ? 'No content available' : 'Sem conteúdo disponível',
        ];
        return $strings[ $key ] ?? $key;
    }
}

echo "=== FASE 1 — Testes dos 4 Estados ===\\n\\n";

// ESTADO 1 — VOD
$event_1 = vana_normalize_event([
    'active_vod' => [
        'provider'   => 'youtube',
        'video_id'   => 'dQw4w9WgXcQ',
        'url'        => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        'title_pt'   => 'Hari-katha',
    ],
    'vod_list'   => [],
    'gallery'    => [],
    'sangha'     => [],
    'event_key'  => '2026-02-15',
    'title_pt'   => 'Test VOD',
]);

$stage_1 = vana_get_stage_content( $event_1 );
echo "Estado 1 (VOD): " . ( $stage_1['type'] === 'vod' ? "✓ PASSOU" : "✗ FALHOU" ) . "\\n";

// ESTADO 2 — GALLERY
$event_2 = vana_normalize_event([
    'active_vod' => [],
    'vod_list'   => [],
    'gallery'    => [
        [ 'url' => 'https://example.com/1.jpg', 'caption' => 'Foto 1' ],
        [ 'url' => 'https://example.com/2.jpg', 'caption' => 'Foto 2' ],
    ],
    'sangha'     => [],
    'event_key'  => '2026-02-15',
    'title_pt'   => 'Test Gallery',
]);

$stage_2 = vana_get_stage_content( $event_2 );
echo "Estado 2 (Gallery): " . ( $stage_2['type'] === 'gallery' ? "✓ PASSOU" : "✗ FALHOU" ) . "\\n";

// ESTADO 3 — SANGHA
$event_3 = vana_normalize_event([
    'active_vod' => [],
    'vod_list'   => [],
    'gallery'    => [],
    'sangha'     => [
        [ 'text' => 'Teste de relato', 'author' => 'Devoto' ],
    ],
    'event_key'  => '2026-02-15',
    'title_pt'   => 'Test Sangha',
]);

$stage_3 = vana_get_stage_content( $event_3 );
echo "Estado 3 (Sangha): " . ( $stage_3['type'] === 'sangha' ? "✓ PASSOU" : "✗ FALHOU" ) . "\\n";

// ESTADO 4 — PLACEHOLDER
$event_4 = vana_normalize_event([
    'active_vod' => [],
    'vod_list'   => [],
    'gallery'    => [],
    'sangha'     => [],
    'event_key'  => '2026-02-15',
    'title_pt'   => 'Test Placeholder',
]);

$stage_4 = vana_get_stage_content( $event_4 );
echo "Estado 4 (Placeholder): " . ( $stage_4['type'] === 'placeholder' ? "✓ PASSOU" : "✗ FALHOU" ) . "\\n";

echo "\\n✅ Fase 1 — Testes concluídos!\\n";
?>
'''

def run_test():
    try:
        ssh = paramiko.SSHClient()
        ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
        
        ssh.connect(HOST, port=PORT, username=USER, allow_agent=True, look_for_keys=True)
        print(f"✓ Conectado a {HOST}:{PORT}")
        
        # Criar arquivo temporário de teste
        stdin, stdout, stderr = ssh.exec_command(
            f"cd /home/u419701790/domains/vanamadhuryamdaily.com/public_html/beta_html && "
            f"wp eval '{repr(test_code)[1:-1]}' --allow-root 2>&1"
        )
        
        output = stdout.read().decode('utf-8')
        errors = stderr.read().decode('utf-8')
        
        print("\n--- Output ---")
        print(output)
        
        if errors:
            print("\n--- Errors ---")
            print(errors)
        
        ssh.close()
        return 0
    except Exception as e:
        print(f"✗ Erro: {e}")
        return 1

if __name__ == '__main__':
    sys.exit(run_test())
