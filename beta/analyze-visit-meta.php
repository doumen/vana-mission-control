<?php
/**
 * Análise de meta fields das visitas
 * Foco: _vana_origin_key, _vana_parent_tour_origin_key, _vana_tour_id
 */

// Carrega WP
define('WP_USE_THEMES', false);
require('../../../wp-load.php');

echo "\n=== ANÁLISE DE META FIELDS DAS VISITAS ===\n\n";

// 1. Busca tour
$tour_id = 360; // "Tour Espiritual Índia 2026"
$tour = get_post($tour_id);
echo "TOUR (ID $tour_id):\n";
echo "  Title: " . $tour->post_title . "\n";
$origin_key = get_post_meta($tour_id, '_vana_origin_key', true);
echo "  _vana_origin_key: " . ($origin_key ?: '(vazio)') . "\n";
echo "\n";

// 2. Busca todas as visitas
$visits = get_posts([
    'post_type'      => 'vana_visit',
    'posts_per_page' => -1,
    'orderby'        => 'post_date',
    'order'          => 'DESC'
]);

echo "TOTAL DE VISITAS: " . count($visits) . "\n\n";

// 3. Análise detalhada
foreach ($visits as $visit) {
    echo "---\n";
    echo "Visita: {$visit->post_title} (ID: {$visit->ID})\n";
    echo "  Post Parent: " . ($visit->post_parent ?: 'nenhum') . "\n";
    
    $origin = get_post_meta($visit->ID, '_vana_origin_key', true);
    echo "  _vana_origin_key: " . ($origin ?: '(vazio)') . "\n";
    
    $parent_origin = get_post_meta($visit->ID, '_vana_parent_tour_origin_key', true);
    echo "  _vana_parent_tour_origin_key: " . ($parent_origin ?: '(vazio)') . "\n";
    
    $tour_id_meta = get_post_meta($visit->ID, '_vana_tour_id', true);
    echo "  _vana_tour_id: " . ($tour_id_meta ?: '(vazio)') . "\n";
    
    // Relacionamento
    if ($visit->post_parent > 0) {
        $parent = get_post($visit->post_parent);
        echo "  Post Parent Title: {$parent->post_title}\n";
    }
    if ($tour_id_meta > 0) {
        $linked_tour = get_post($tour_id_meta);
        echo "  Linked Tour: {$linked_tour->post_title}\n";
    }
}

echo "\n=== RESUMO ===\n";
echo "Tour 360 origin_key: " . ($origin_key ?: 'VAZIO') . "\n";

// Conta quantas visitas têm relacionamento pela origin_key
if ($origin_key) {
    $matching = get_posts([
        'post_type'  => 'vana_visit',
        'meta_key'   => '_vana_parent_tour_origin_key',
        'meta_value' => $origin_key,
        'posts_per_page' => -1
    ]);
    echo "Visitas com _vana_parent_tour_origin_key matching: " . count($matching) . "\n";
} else {
    echo "Visitas com _vana_parent_tour_origin_key matching: 0 (tour 360 sem origin_key)\n";
}

echo "\n";
