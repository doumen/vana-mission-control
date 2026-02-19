<?php
/**
 * Template: single vana_tour
 * Comportamento MVP: esta página é "fantasma" e redireciona para a visita atual/última.
 */
defined('ABSPATH') || exit;

the_post();
$tour_id = (int) get_the_ID();

$lang = 'pt';
if (isset($_GET['lang'])) {
    $candidate = sanitize_key((string) $_GET['lang']);
    if (in_array($candidate, ['pt', 'en'], true)) $lang = $candidate;
} elseif (isset($_COOKIE['vana_lang'])) {
    $candidate = sanitize_key((string) $_COOKIE['vana_lang']);
    if (in_array($candidate, ['pt', 'en'], true)) $lang = $candidate;
}

$current = (int) get_post_meta($tour_id, '_vana_current_visit_id', true);
$last    = (int) get_post_meta($tour_id, '_vana_last_visit_id', true);
$target  = $current > 0 ? $current : $last;

if ($target > 0) {
    $u = add_query_arg('lang', $lang, get_permalink($target));
    wp_safe_redirect($u, 302);
    exit;
}

wp_safe_redirect(home_url('/tours/?lang=' . $lang), 302);
exit;
