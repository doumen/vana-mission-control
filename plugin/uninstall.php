<?php
/**
 * Desinstalação do plugin
 * 
 * @package Vana_Mission_Control
 */

// Se não for chamado via WordPress, sai
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

global $wpdb;

// 1. Remove tabela customizada
$table_name = $wpdb->prefix . 'vana_origin_index';
$wpdb->query("DROP TABLE IF EXISTS {$table_name}");

// 2. Remove options
delete_option('vana_auto_publish');
delete_option('vana_rate_limit');
delete_option('vana_mc_db_version');

// 3. [OPCIONAL] Remove posts de tours (cuidado!)
// Descomente apenas se quiser limpar TUDO na desinstalação
/*
$wpdb->query("DELETE FROM {$wpdb->posts} WHERE post_type = 'vana_tour'");
$wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE post_id NOT IN (SELECT ID FROM {$wpdb->posts})");
*/

// 4. Log final (se WP_DEBUG ativo)
if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
    error_log('[VANA] Plugin desinstalado e dados removidos');
}
