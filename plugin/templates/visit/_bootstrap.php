<?php
/**
 * Bootstrap: Vana Visit
 * Carrega dados, timezone, idioma e gera índices runtime.
 *
 * Variáveis exportadas para os partials:
 *   $lang, $visit_id, $tour_id, $tour_url, $tour_title
 *   $data, $days, $location_meta, $visit_tz, $visit_city_ref
 *   $active_index, $active_day, $active_day_date
 *   $vod_list, $vod_count, $active_vod_index, $active_vod
 *   $prev_id, $next_id
 */
defined('ABSPATH') || exit;

// ── 1. IDIOMA ──────────────────────────────────────────────
$lang = Vana_Utils::lang_from_request(); // 'pt' | 'en'

// ── 2. POST & TOUR ─────────────────────────────────────────
$visit_id = get_the_ID();

$tour_id    = (int) wp_get_post_parent_id($visit_id);
if (!$tour_id) $tour_id = (int) get_post_meta($visit_id, '_vana_tour_id',  true);
if (!$tour_id) $tour_id = (int) get_post_meta($visit_id, '_tour_id',       true);

$tour_url   = '';
$tour_title = '';

if ($tour_id) {
    $tour_url   = get_permalink($tour_id);
    $tour_title = get_the_title($tour_id);
} else {
    $terms = wp_get_post_terms($visit_id, 'vana_tour');
    if (!empty($terms) && !is_wp_error($terms)) {
        $tour_title = $terms[0]->name;
        $tour_url   = get_term_link($terms[0]);
    }
}

// ── 3. JSON DA TIMELINE ────────────────────────────────────
$raw_data = get_post_meta($visit_id, '_vana_visit_timeline_json', true);
$data     = is_string($raw_data) ? json_decode($raw_data, true) : [];
$data     = is_array($data) ? $data : [];

// ── 4. LOCALIZAÇÃO & TIMEZONE ──────────────────────────────
$location_meta  = is_array($data['location_meta'] ?? null) ? $data['location_meta'] : [];
$visit_city_ref = (string) ($location_meta['city_ref'] ?? '');
$visit_tz_str   = (string) ($location_meta['tz']       ?? 'UTC');

try {
    $visit_tz = new DateTimeZone($visit_tz_str ?: 'UTC');
} catch (Exception $e) {
    $visit_tz = new DateTimeZone('UTC');
}

// ── 5. DIAS ────────────────────────────────────────────────
$days = is_array($data['days'] ?? null) ? $data['days'] : [];

// ── 6. DIA ATIVO (via ?v_day=YYYY-MM-DD) ──────────────────
$active_day_key    = sanitize_text_field((string) ($_GET['v_day'] ?? ''));
$day_index_by_date = [];

foreach ($days as $i => $d) {
    $k = (string) ($d['date_local'] ?? '');
    if ($k !== '') $day_index_by_date[$k] = $i;
}

$active_index    = ($active_day_key !== '' && isset($day_index_by_date[$active_day_key]))
                   ? (int) $day_index_by_date[$active_day_key]
                   : 0;
$active_day      = $days[$active_index] ?? [];
$active_day_date = (string) ($active_day['date_local'] ?? '');

// ── 7. VOD ─────────────────────────────────────────────────
$vod_list  = is_array($active_day['vod'] ?? null) ? $active_day['vod'] : [];
$vod_count = count($vod_list);

$default_vod_index  = $vod_count > 0 ? ($vod_count - 1) : 0;
$active_vod_index   = isset($_GET['vod'])
                      ? max(0, min($vod_count - 1, (int) $_GET['vod']))
                      : $default_vod_index;
$active_vod         = ($vod_count > 0 && isset($vod_list[$active_vod_index]) && is_array($vod_list[$active_vod_index]))
                      ? $vod_list[$active_vod_index]
                      : [];

// ── 8. PREV / NEXT (bússola materializada) ─────────────────
if (!function_exists('vana_visit_prev_next_ids')) {
    function vana_visit_prev_next_ids(int $current_id): array {
        $sequence = function_exists('vana_get_chronological_visits')
                    ? vana_get_chronological_visits()
                    : [];
        if (empty($sequence)) return [0, 0];

        $ids = array_column($sequence, 'id');
        $idx = array_search($current_id, $ids, true);
        if (false === $idx) return [0, 0];

        return [
            ($idx > 0)                  ? $ids[$idx - 1] : 0,
            ($idx < count($ids) - 1)    ? $ids[$idx + 1] : 0,
        ];
    }
}

[$prev_id, $next_id] = vana_visit_prev_next_ids($visit_id);

// ── 9. HELPER: event_key em runtime ────────────────────────
if (!function_exists('vana_make_event_key')) {
    /**
     * Gera event_key canônico: YYYYMMDD-HHMM-slug
     * Exemplo: 20260214-0800-mangala-arati
     */
    function vana_make_event_key(string $date_local, string $time_local, string $title_pt): string {
        $date_part = str_replace('-', '', $date_local);                  // 20260214
        $time_part = str_replace(':', '', substr($time_local, 0, 5));   // 0800
        $slug_part = sanitize_title($title_pt);                          // mangala-arati
        return "{$date_part}-{$time_part}-{$slug_part}";
    }
}

// ── 10. HELPERS DE MÉDIA (inline para compatibilidade) ─────
if (!function_exists('vana_drive_file_id')) {
    function vana_drive_file_id(string $url): string {
        if (!$url) return '';
        if (preg_match('~\/d\/([a-zA-Z0-9_-]+)~', $url, $m)) return $m[1];
        return '';
    }
}

if (!function_exists('vana_stage_resolve_media')) {
    function vana_stage_resolve_media(array $item): array {
        $provider = strtolower((string) ($item['provider'] ?? ''));
        $video_id = (string) ($item['video_id'] ?? '');
        $url      = (string) ($item['url']      ?? '');

        // Facebook: video_id pode ser URL completa
        if ($provider === 'facebook' && $url === '' && preg_match('~^https?://~i', $video_id)) {
            $url = $video_id; $video_id = '';
        }
        // Instagram / Drive: idem
        if (in_array($provider, ['instagram', 'drive'], true) && $url === '' && preg_match('~^https?://~i', $video_id)) {
            $url = $video_id; $video_id = '';
        }
        // YouTube: aceita URL completa no campo video_id
        if ($provider === 'youtube' && $video_id !== '' && !preg_match('/^[A-Za-z0-9_-]{11}$/', $video_id)) {
            if (preg_match('%(?:youtube(?:-nocookie)?\.com/(?:[^/]+/.+/|(?:v|e(?:mbed)?)/|.*[?&]v=)|youtu\.be/)([^"&?/ ]{11})%i', $video_id, $m)) {
                $video_id = $m[1];
            }
        }
        return ['provider' => $provider, 'video_id' => $video_id, 'url' => $url];
    }
}

// ── 11. HELPER: URL preservando lang ───────────────────────
if (!function_exists('vana_visit_url')) {
    function vana_visit_url(int $post_id, string $v_day = '', int $vod = -1, string $lang = 'pt'): string {
        $url = get_permalink($post_id);
        if ($v_day !== '') $url = add_query_arg('v_day', $v_day, $url);
        if ($vod >= 0)     $url = add_query_arg('vod',   $vod,   $url);
        if ($lang === 'en') $url = add_query_arg('lang', 'en',   $url);
        return $url;
    }
}
