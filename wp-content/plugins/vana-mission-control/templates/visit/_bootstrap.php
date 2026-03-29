<?php
// Adicionar ao _bootstrap.php existente (após $days estar disponível):

// ── Index do visit.json processado ──────────────────────────────────
$index = [];
if ( ! empty( $post_meta['_vana_visit_data'][0] ) ) {
    $visit_data = json_decode( $post_meta['_vana_visit_data'][0], true );
    $index      = $visit_data['index'] ?? [];
}

// ── Dia ativo (priority: ?day= GET param → primary_event → first) ──
$active_day_key = sanitize_text_field( $_GET['day'] ?? '' );

if ( ! $active_day_key && ! empty( $days ) ) {
    // Usa primary_event do primeiro dia para determinar dia ativo
    $active_day_key = $days[0]['day_key'] ?? '';
}

// Encontra o array completo do dia ativo
$active_day = null;
foreach ( $days as $d ) {
    if ( ( $d['day_key'] ?? '' ) === $active_day_key ) {
        $active_day = $d;
        break;
    }
}
if ( ! $active_day && ! empty( $days ) ) {
    $active_day = $days[0];
}
