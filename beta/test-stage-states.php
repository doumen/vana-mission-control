<?php
/**
 * Script de teste: 4 estados do Stage — Fase 1
 * Uso: wp eval-file test-stage-states.php
 *
 * Testa as funções:
 *   vana_normalize_event()
 *   vana_get_stage_content()
 */

$pass = 0;
$fail = 0;

// ═════════════════════════════════════════════════════════
//  1. ESTADO — VOD (YouTube)
// ═════════════════════════════════════════════════════════

echo "\n=== TESTE 1: VOD State ===\n";

$event_vod = vana_normalize_event([
    'active_vod' => [
        'provider'  => 'youtube',
        'video_id'  => 'dQw4w9WgXcQ',
        'title_pt'  => 'Hari-katha com Gurudeva',
        'title_en'  => 'Hari-katha with Gurudeva',
    ],
    'vod_list'   => [],
    'gallery'    => [],
    'sangha'     => [],
    'event_key'  => '2026-03-21',
    'title_pt'   => 'Hari-katha',
    'title_en'   => 'Katha Session',
    'time_start' => '10:00',
    'status'     => 'live',
]);

$stage_vod = vana_get_stage_content($event_vod);

echo "  type     : " . ( $stage_vod['type'] ?? '(null)' ) . "\n";
echo "  live     : " . ( isset($stage_vod['live']) ? ($stage_vod['live'] ? 'true' : 'false') : '(null)' ) . "\n";
echo "  provider : " . ( $stage_vod['data']['provider'] ?? '(null)' ) . "\n";
echo "  video_id : " . ( $stage_vod['data']['video_id'] ?? '(null)' ) . "\n";
echo "  ESPERADO : type=vod, live=true, provider=youtube\n";

if ( ($stage_vod['type'] ?? '') === 'vod' && ($stage_vod['live'] ?? false) === true && ($stage_vod['data']['provider'] ?? '') === 'youtube' ) {
    echo "  ✅ PASSED\n";
    $pass++;
} else {
    echo "  ❌ FAILED\n";
    $fail++;
}


// ═════════════════════════════════════════════════════════
//  2. ESTADO — GALLERY (sem VOD)
// ═════════════════════════════════════════════════════════

echo "\n=== TESTE 2: Gallery State ===\n";

$event_gallery = vana_normalize_event([
    'active_vod' => [],
    'vod_list'   => [],
    'gallery'    => [
        ['url' => 'https://example.com/photo1.jpg', 'caption' => 'Foto 1'],
        ['url' => 'https://example.com/photo2.jpg', 'caption' => 'Foto 2'],
        ['url' => 'https://example.com/photo3.jpg', 'caption' => 'Foto 3'],
    ],
    'sangha'     => [],
    'event_key'  => '2026-03-21',
    'title_pt'   => 'Galeria da visita',
    'title_en'   => 'Visit Gallery',
    'time_start' => '14:00',
    'status'     => 'completed',
]);

$stage_gallery = vana_get_stage_content($event_gallery);

echo "  type  : " . ( $stage_gallery['type'] ?? '(null)' ) . "\n";
echo "  count : " . ( $stage_gallery['count'] ?? '(null)' ) . "\n";
echo "  ESPERADO: type=gallery, count=3\n";

if ( ($stage_gallery['type'] ?? '') === 'gallery' && ($stage_gallery['count'] ?? 0) === 3 ) {
    echo "  ✅ PASSED\n";
    $pass++;
} else {
    echo "  ❌ FAILED\n";
    $fail++;
}


// ═════════════════════════════════════════════════════════
//  3. ESTADO — SANGHA (sem VOD, sem gallery)
// ═════════════════════════════════════════════════════════

echo "\n=== TESTE 3: Sangha State ===\n";

$event_sangha = vana_normalize_event([
    'active_vod' => [],
    'vod_list'   => [],
    'gallery'    => [],
    'sangha'     => [
        [
            'text'   => 'Que Gurudeva seja glorificado por sempre!',
            'author' => 'Devoto de Madhuryam',
        ],
    ],
    'event_key'  => '2026-03-21',
    'title_pt'   => 'Sangha Moment',
    'title_en'   => 'Sangha Moment',
    'time_start' => '16:00',
    'status'     => 'completed',
]);

$stage_sangha = vana_get_stage_content($event_sangha);

echo "  type   : " . ( $stage_sangha['type'] ?? '(null)' ) . "\n";
echo "  author : " . ( $stage_sangha['data']['author'] ?? '(null)' ) . "\n";
echo "  text   : " . ( $stage_sangha['data']['text'] ?? '(null)' ) . "\n";
echo "  ESPERADO: type=sangha, author preenchido\n";

if ( ($stage_sangha['type'] ?? '') === 'sangha' && !empty($stage_sangha['data']['author']) ) {
    echo "  ✅ PASSED\n";
    $pass++;
} else {
    echo "  ❌ FAILED\n";
    $fail++;
}


// ═════════════════════════════════════════════════════════
//  4. ESTADO — PLACEHOLDER (tudo vazio, status=scheduled)
// ═════════════════════════════════════════════════════════

echo "\n=== TESTE 4: Placeholder State ===\n";

$event_placeholder = vana_normalize_event([
    'active_vod' => [],
    'vod_list'   => [],
    'gallery'    => [],
    'sangha'     => [],
    'event_key'  => '2026-03-21',
    'title_pt'   => 'Aula futura',
    'title_en'   => 'Upcoming Class',
    'time_start' => '18:00',
    'status'     => 'scheduled',
]);

$stage_placeholder = vana_get_stage_content($event_placeholder);

echo "  type   : " . ( $stage_placeholder['type'] ?? '(null)' ) . "\n";
echo "  title  : " . ( $stage_placeholder['event']['title'] ?? '(null)' ) . "\n";
echo "  status : " . ( $stage_placeholder['event']['status'] ?? '(null)' ) . "\n";
echo "  ESPERADO: type=placeholder, title preenchido\n";

if ( ($stage_placeholder['type'] ?? '') === 'placeholder' && !empty($stage_placeholder['event']['title']) ) {
    echo "  ✅ PASSED\n";
    $pass++;
} else {
    echo "  ❌ FAILED\n";
    $fail++;
}


// ═════════════════════════════════════════════════════════
//  5. BÔNUS — VOD não-live (status=completed)
// ═════════════════════════════════════════════════════════

echo "\n=== TESTE 5 (bônus): VOD não-live ===\n";

$event_vod_replay = vana_normalize_event([
    'active_vod' => [
        'provider'  => 'youtube',
        'video_id'  => 'abc123replay',
        'title_pt'  => 'Replay da katha',
        'title_en'  => 'Katha Replay',
    ],
    'vod_list'   => [],
    'gallery'    => [],
    'sangha'     => [],
    'event_key'  => '2026-03-20',
    'title_pt'   => 'Replay',
    'title_en'   => 'Replay',
    'time_start' => '10:00',
    'status'     => 'completed',
]);

$stage_replay = vana_get_stage_content($event_vod_replay);

echo "  type : " . ( $stage_replay['type'] ?? '(null)' ) . "\n";
echo "  live : " . ( isset($stage_replay['live']) ? ($stage_replay['live'] ? 'true' : 'false') : '(null)' ) . "\n";
echo "  ESPERADO: type=vod, live=false\n";

if ( ($stage_replay['type'] ?? '') === 'vod' && ($stage_replay['live'] ?? true) === false ) {
    echo "  ✅ PASSED\n";
    $pass++;
} else {
    echo "  ❌ FAILED\n";
    $fail++;
}


// ═════════════════════════════════════════════════════════
//  6. BÔNUS — vod_list sem active_vod (fallback para lista)
// ═════════════════════════════════════════════════════════

echo "\n=== TESTE 6 (bônus): vod_list sem active_vod ===\n";

$event_vodlist = vana_normalize_event([
    'active_vod' => [],
    'vod_list'   => [
        [
            'provider' => 'youtube',
            'video_id' => 'listitem001',
            'title_pt' => 'Katha da lista',
            'title_en' => 'List Katha',
        ],
    ],
    'gallery'    => [],
    'sangha'     => [],
    'event_key'  => '2026-03-19',
    'title_pt'   => 'Katha da lista',
    'title_en'   => 'List Katha',
    'time_start' => '09:00',
    'status'     => 'completed',
]);

$stage_vodlist = vana_get_stage_content($event_vodlist);

echo "  type     : " . ( $stage_vodlist['type'] ?? '(null)' ) . "\n";
echo "  video_id : " . ( $stage_vodlist['data']['video_id'] ?? '(null)' ) . "\n";
echo "  ESPERADO: type=vod, video_id=listitem001\n";

if ( ($stage_vodlist['type'] ?? '') === 'vod' && ($stage_vodlist['data']['video_id'] ?? '') === 'listitem001' ) {
    echo "  ✅ PASSED\n";
    $pass++;
} else {
    echo "  ❌ FAILED\n";
    $fail++;
}


// ═════════════════════════════════════════════════════════
//  SUMMARY
// ═════════════════════════════════════════════════════════

$total = $pass + $fail;
echo "\n" . str_repeat('═', 52) . "\n";
echo "  FASE 1 — STAGE FOUNDATION — RESULTADO FINAL\n";
echo str_repeat('═', 52) . "\n";
echo "  ✅ PASSED : {$pass}/{$total}\n";
echo "  ❌ FAILED : {$fail}/{$total}\n";
echo str_repeat('═', 52) . "\n\n";

if ( $fail === 0 ) {
    echo "  🎉 TODOS OS TESTES PASSARAM! Fase 1 validada.\n\n";
} else {
    echo "  ⚠️  {$fail} teste(s) falharam. Revisar acima.\n\n";
}
