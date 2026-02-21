<?php
/**
 * Template: Single Vana Visit (Luminous + Stage + Tabs + Cached Tour Nav + Lightbox Slider + Sangha Wall + Geo LGPD + Dual-Timezone)
 * 100% Visual Original + Javascript Intacto + Internacionalização Completa + Fuso Horário Local
 */
if (!defined('ABSPATH')) exit;

// ==========================================
// 1. HELPER DE CACHE PARA TOUR NAV (PREV/NEXT)
// ==========================================
if (!function_exists('vana_visit_start_date_key')) {
    function vana_visit_start_date_key(int $post_id): string {
        $raw = get_post_meta($post_id, '_vana_visit_timeline_json', true);
        $data = is_string($raw) ? json_decode($raw, true) : null;
        if (!is_array($data)) return '';
        $days = $data['days'] ?? null;
        if (!is_array($days) || empty($days[0]) || !is_array($days[0])) return '';
        $d = (string)($days[0]['date_local'] ?? '');
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) ? $d : '';
    }
}

if (!function_exists('vana_visit_prev_next_ids')) {
    function vana_visit_prev_next_ids(int $current_id): array {
        // Usa a lógica de Bússola Materializada atualizada
        $sequence = function_exists('vana_get_chronological_visits') ? vana_get_chronological_visits() : [];
        if (empty($sequence)) return [0, 0];
        
        $ids = array_column($sequence, 'id');
        $idx = array_search($current_id, $ids);

        if (false === $idx) return [0, 0];

        $prev = ($idx > 0) ? $ids[$idx - 1] : 0;
        $next = ($idx < count($ids) - 1) ? $ids[$idx + 1] : 0;

        return [$prev, $next];
    }
}

// Inicialização do Idioma
$lang = Vana_Utils::lang_from_request();
$visit_id = get_the_ID();

// Tenta achar a Tour Pai
$tour_id = wp_get_post_parent_id($visit_id);
if (!$tour_id) $tour_id = (int) get_post_meta($visit_id, '_vana_tour_id', true);
if (!$tour_id) $tour_id = (int) get_post_meta($visit_id, '_tour_id', true);
$tour_url = '';
$tour_title = '';
if ($tour_id) {
    $tour_url = get_permalink($tour_id);
    $tour_title = get_the_title($tour_id);
} else {
    $terms = wp_get_post_terms($visit_id, 'vana_tour');
    if (!empty($terms) && !is_wp_error($terms)) {
        $tour_title = $terms[0]->name;
        $tour_url = get_term_link($terms[0]);
    }
}

// ==========================================
// 2. PARSER DO JSON & ESTADO (TABS + VOD)
// ==========================================
$raw_data = get_post_meta($visit_id, '_vana_visit_timeline_json', true);
$data = is_string($raw_data) ? json_decode($raw_data, true) : [];
$data = is_array($data) ? $data : [];

// Extração de Localização Base (Visita) e Timezone (Dual-Time)
$location_meta = is_array($data['location_meta'] ?? null) ? $data['location_meta'] : [];
$visit_city_ref = (string)($location_meta['city_ref'] ?? '');
$visit_tz_string = (string)($location_meta['tz'] ?? 'UTC');

// Cria o objeto de Timezone do Evento
try { 
    $visit_tz = new DateTimeZone($visit_tz_string ?: 'UTC'); 
} catch (Exception $e) { 
    $visit_tz = new DateTimeZone('UTC'); 
}

$days = is_array($data['days'] ?? null) ? $data['days'] : [];

$active_day_key = sanitize_text_field((string)($_GET['v_day'] ?? ''));
$day_index_by_date = [];
foreach ($days as $i => $d) {
    $k = (string)($d['date_local'] ?? '');
    if ($k !== '') $day_index_by_date[$k] = $i;
}
$active_index = ($active_day_key !== '' && isset($day_index_by_date[$active_day_key])) ? (int)$day_index_by_date[$active_day_key] : 0;
$active_day = $days[$active_index] ?? [];
$active_day_date = (string)($active_day['date_local'] ?? '');

$vod_list = is_array($active_day['vod'] ?? null) ? $active_day['vod'] : [];
$vod_count = count($vod_list);

// Default VOD
$default_vod_index = $vod_count > 0 ? ($vod_count - 1) : 0;
$active_vod_index = isset($_GET['vod']) ? (int)$_GET['vod'] : $default_vod_index;
$active_vod_index = max(0, min($vod_count - 1, $active_vod_index));
$active_vod = ($vod_count > 0 && isset($vod_list[$active_vod_index]) && is_array($vod_list[$active_vod_index])) ? $vod_list[$active_vod_index] : [];

get_header();
do_action('astra_primary_content_top');
?>
<div id="primary" class="content-area primary">
  <?php do_action('astra_primary_content_before'); ?>
  <main id="main" class="site-main" style="background-color: var(--vana-bg-soft); padding-bottom: 60px;">
    <?php do_action('astra_content_before'); ?>

    <style>
/* ====================================
   VANA LUMINOUS DESIGN SYSTEM v5.1
   (O SEU CSS ORIGINAL INTACTO)
   ==================================== */

:root {
  --vana-bg: #ffffff;
  --vana-bg-soft: #f8fafc;
  --vana-line: #e2e8f0;
  --vana-text: #0f172a;
  --vana-muted: #64748b;
  --vana-gold: #FFD906;
  --vana-blue: #170DF2;
  --vana-pink: #F30B73;
  --vana-orange: #F35C0B;
  --vana-pinksoft: #F288B8;
  --vana-hero-gradient: radial-gradient(circle at 28.33% 11.66%, rgba(243, 11, 115, 0.1) 0%, 17.5%, rgba(243, 11, 115, 0) 35%), radial-gradient(circle at 17.5% 87.5%, rgba(255, 217, 6, 0.15) 0%, 17.5%, rgba(255, 217, 6, 0) 35%), radial-gradient(circle at 47.5% 6.66%, rgba(243, 92, 11, 0.1) 0%, 17.5%, rgba(243, 92, 11, 0) 35%), radial-gradient(circle at 74.58% 75%, rgba(23, 13, 242, 0.08) 0%, 17.5%, rgba(23, 13, 242, 0) 35%), radial-gradient(circle at 48.9% 49.52%, #FFFFFF 0%, 100%, rgba(255, 255, 255, 0) 100%);
}

.vana-wrap { max-width: 1200px; margin: 0 auto; padding: 0 16px; font-family: 'Questrial', sans-serif; color: var(--vana-text); }
.vana-card a:focus-visible, .vana-card button:focus-visible, #vanaCopyFbLink:focus-visible, .vana-tab:focus-visible, .vana-nav-btn:focus-visible, .vana-moment-btn:focus-visible { outline: 3px solid rgba(255, 217, 6, 0.9); outline-offset: 3px; border-radius: 8px; }
.vana-hero { background-color: var(--vana-bg); background-image: var(--vana-hero-gradient); color: var(--vana-text); border-bottom: 4px solid var(--vana-gold); padding: 30px 20px 60px; text-align: center; }
.vana-tour-nav { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
.vana-nav-btn { font-weight: 900; color: var(--vana-text); text-decoration: none; font-size: 0.95rem; background: rgba(255, 255, 255, 0.6); padding: 8px 16px; border-radius: 8px; border: 1px solid var(--vana-line); transition: 0.2s; }
.vana-nav-btn:hover { background: var(--vana-gold); border-color: var(--vana-gold); }
.vana-badge { background: rgba(255, 217, 6, 0.2); border: 1px solid var(--vana-gold); color: #8a6b00; padding: 4px 12px; border-radius: 20px; font-weight: bold; font-size: 0.8rem; text-transform: uppercase; }
.vana-hero h1 { font-family: 'Syne', sans-serif; font-size: clamp(2rem, 5vw, 3.2rem); color: var(--vana-text); margin: 15px 0 0; font-weight: 800; }
.vana-tabs { display: flex; gap: 10px; flex-wrap: wrap; margin: 20px 0 30px; }
.vana-tab { padding: 10px 18px; border-radius: 999px; border: 1px solid var(--vana-line); font-weight: 800; text-decoration: none; transition: 0.2s; font-family: 'Syne', sans-serif; background: #fff; color: var(--vana-text); }
.vana-tab.active { background: var(--vana-text); color: #fff; border-color: var(--vana-text); box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1); }
.vana-tab:not(.active):hover { background: var(--vana-bg-soft); border-color: var(--vana-gold); }
.vana-stage { background: #fff; border: 1px solid var(--vana-line); border-radius: 16px; overflow: hidden; box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05); margin-bottom: 40px; }
.vana-stage-video { position: relative; width: 100%; padding-bottom: 56.25%; height: 0; background: #0b1220; }
.vana-stage-video iframe { position: absolute; inset: 0; width: 100%; height: 100%; border: 0; }
.vana-stage-info { background: #fff; color: var(--vana-text); border-top: 1px solid var(--vana-line); padding: 25px; }
.vana-stage-info-badge { background: var(--vana-gold); color: #111; padding: 4px 10px; border-radius: 6px; font-weight: bold; font-size: 0.8rem; text-transform: uppercase; }
.vana-stage-segments { padding: 15px 25px 25px; border-top: 1px solid var(--vana-line); background: #fafcfd; }
.vana-seg-btn { padding: 8px 14px; border-radius: 999px; border: 1px solid var(--vana-line); background: #fff; font-weight: 700; color: var(--vana-text); font-size: 0.9rem; cursor: pointer; transition: 0.2s; display: inline-flex; gap: 8px; align-items: center; margin: 4px; }
.vana-seg-btn:hover { border-color: var(--vana-gold); background: #fffdf0; }
.vana-seg-btn strong { color: var(--vana-orange); font-family: monospace; font-size: 0.95rem; }
.vana-section-title { font-family: 'Syne', sans-serif; font-size: 1.6rem; color: var(--vana-text); margin: 40px 0 20px; border-left: 4px solid var(--vana-gold); padding-left: 12px; }
.vana-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 20px; }
.vana-card { background: #fff; border: 1px solid var(--vana-line); border-radius: 12px; overflow: hidden; transition: 0.2s; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.02); }
.vana-card:hover { transform: translateY(-3px); box-shadow: 0 10px 15px rgba(0, 0, 0, 0.08); border-color: #cbd5e1; }
.vana-card a, .vana-card button.vana-card-trigger { all: unset; display: block; cursor: pointer; height: 100%; text-decoration: none; }
.vana-card__media { position: relative; width: 100%; padding-bottom: 56.25%; background: var(--vana-bg-soft); overflow: hidden; }
.vana-card__media img { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; transition: 0.3s; }
.vana-card:hover .vana-card__media img { transform: scale(1.05); }
.vana-card__play { position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); width: 44px; height: 44px; background: rgba(255, 255, 255, 0.9); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: var(--vana-text); box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1); transition: 0.2s; pointer-events: none; }
.vana-card:hover .vana-card__play { background: var(--vana-gold); color: #000; transform: translate(-50%, -50%) scale(1.1); }
.vana-card__body { padding: 15px; display: flex; flex-direction: column; height: 100%; }
.vana-card__name { margin: 0; font-weight: 700; font-family: 'Syne', sans-serif; font-size: 1.05rem; line-height: 1.3; }
.vana-sangha-wall { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 25px; margin-top: 30px; }
.vana-moment { background: #fff; border: 1px solid var(--vana-line); border-radius: 18px; overflow: hidden; box-shadow: 0 8px 20px rgba(0, 0, 0, 0.04); transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
.vana-moment:hover { transform: translateY(-6px); border-color: var(--vana-gold); box-shadow: 0 14px 30px rgba(255, 217, 6, 0.12); }
.vana-moment-btn { all: unset; display: block; cursor: pointer; width: 100%; }
.vana-moment-inner { padding: 20px 20px 10px; flex-grow: 1; }
.vana-moment-user { display: flex; align-items: center; gap: 14px; margin-bottom: 15px; }
.vana-moment-avatar { width: 42px; height: 42px; border-radius: 50%; background: var(--vana-hero-gradient); border: 1px solid var(--vana-line); display: flex; align-items: center; justify-content: center; color: var(--vana-text); font-weight: 900; font-family: 'Syne', sans-serif; flex-shrink: 0; }
.vana-moment-name { font-family: 'Syne', sans-serif; font-weight: 900; color: var(--vana-text); font-size: 1.1rem; }
.vana-moment-text { position: relative; background: var(--vana-bg-soft); border: 1px solid var(--vana-line); border-radius: 16px; padding: 16px; color: #334155; line-height: 1.6; font-size: 1.05rem; font-style: italic; margin: 0 0 15px; min-height: 40px; display: -webkit-box; -webkit-line-clamp: 4; -webkit-box-orient: vertical; overflow: hidden; }
.vana-moment-text:after { content: ""; position: absolute; top: -8px; left: 20px; width: 14px; height: 14px; background: var(--vana-bg-soft); border-left: 1px solid var(--vana-line); border-top: 1px solid var(--vana-line); transform: rotate(45deg); }
.vana-moment-media { margin: 0 22px 15px; border-radius: 12px; overflow: hidden; background: #fff; border: 6px solid #fff; box-shadow: 0 6px 15px rgba(0, 0, 0, 0.12); }
.vana-moment-media img { width: 100%; height: auto; display: block; }
.vana-moment-footer { display: flex; justify-content: space-between; align-items: center; padding: 0 22px 20px; color: var(--vana-muted); font-size: 0.85rem; font-weight: 700; }
.vana-moment-badge { display: inline-flex; align-items: center; gap: 6px; background: #f8fafc; border: 1px solid var(--vana-line); padding: 4px 12px; border-radius: 999px; font-weight: 900; text-transform: uppercase; font-size: 0.7rem; color: var(--vana-muted); }
.vana-moment-badge .dashicons { font-size: 14px; width: 14px; height: 14px; color: var(--vana-gold); }
.vana-moment--text-only { background: linear-gradient(135deg, #ffffff 0%, #fefcf0 100%); border-bottom: 3px solid var(--vana-gold); }
.vana-moment--text-only .vana-moment-text { font-size: 1.25rem; line-height: 1.6; text-align: center; padding: 20px 10px; font-weight: 500; -webkit-line-clamp: 6; }
.vana-moment--text-only .vana-moment-inner:before { content: "\f122"; font-family: dashicons; position: absolute; top: 15px; right: 20px; font-size: 3rem; color: var(--vana-gold); opacity: 0.15; pointer-events: none; }
.vana-schedule-list { background: #fff; border: 1px solid var(--vana-line); border-radius: 12px; overflow: hidden; }
.vana-schedule-item { display: flex; align-items: flex-start; padding: 16px 20px; border-bottom: 1px solid var(--vana-line); }
.vana-schedule-item:nth-child(even) { background: #fafcfd; }
.vana-schedule-time { font-weight: 700; color: var(--vana-orange); width: 70px; flex-shrink: 0; font-family: monospace; font-size: 1.1rem; }
.vana-schedule-title { flex-grow: 1; margin: 0 15px; font-weight: 700; color: var(--vana-text); font-size: 1.05rem; }
.vana-schedule-status { font-size: 0.75rem; padding: 4px 10px; border-radius: 20px; text-transform: uppercase; font-weight: 800; background: var(--vana-bg-soft); color: var(--vana-muted); }
.status-live { background: #fee2e2; color: #dc2626; }
.status-done { background: #dcfce7; color: #16a34a; }
.vana-gallery-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(130px, 1fr)); gap: 12px; }
.vana-gallery-item { aspect-ratio: 1; border-radius: 8px; overflow: hidden; cursor: pointer; background: var(--vana-line); }
.vana-gallery-item img { width: 100%; height: 100%; object-fit: cover; transition: 0.3s; }
.vana-gallery-item:hover img { transform: scale(1.08); }
.vana-form-wrap { background: #fff; padding: 40px; border-radius: 16px; margin-top: 40px; border: 1px solid var(--vana-line); box-shadow: 0 10px 30px rgba(0, 0, 0, 0.03); }
.vana-form-wrap input, .vana-form-wrap textarea { width: 100%; padding: 12px 16px; border-radius: 8px; border: 1px solid #cbd5e1; background: var(--vana-bg-soft); color: var(--vana-text); font-family: 'Questrial', sans-serif; transition: 0.2s; margin-top: 5px; }
.vana-form-wrap input:focus, .vana-form-wrap textarea:focus { outline: none; border-color: var(--vana-gold); background: #fff; box-shadow: 0 0 0 3px rgba(255, 217, 6, 0.2); }
.vana-modal { position: fixed; inset: 0; z-index: 99999; display: none; }
.vana-modal.is-active { display: block; }
.vana-modal__backdrop { position: absolute; inset: 0; background: rgba(15, 23, 42, 0.9); backdrop-filter: blur(5px); }
.vana-modal__dialog { position: relative; max-width: 1000px; margin: 3vh auto; background: #fff; border-radius: 16px; overflow: hidden; display: flex; flex-direction: column; max-height: 94vh; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5); }
.vana-modal__close { position: absolute; top: 15px; right: 15px; z-index: 20; background: #fff; color: var(--vana-text); border: none; border-radius: 50%; width: 40px; height: 40px; cursor: pointer; font-size: 1.5rem; display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 10px rgba(0, 0, 0, 0.15); transition: 0.2s; }
.vana-modal__close:hover { background: var(--vana-gold); }
.vana-modal__nav { position: absolute; top: 50%; transform: translateY(-50%); background: rgba(255, 255, 255, 0.9); color: var(--vana-text); border: none; width: 44px; height: 44px; border-radius: 50%; font-size: 1.5rem; display: flex; align-items: center; justify-content: center; cursor: pointer; z-index: 15; box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1); transition: 0.2s; }
.vana-modal__nav:hover { background: var(--vana-gold); }
.vana-modal__nav--prev { left: 15px; }
.vana-modal__nav--next { right: 15px; }
.vana-modal__media { background: #000; min-height: 200px; display: flex; align-items: center; justify-content: center; position: relative; }
.vana-embed { position: relative; width: 100%; height: 0; padding-bottom: 56.25%; background: #000; overflow: hidden; }
.vana-embed iframe { position: absolute; top: 0; left: 0; width: 100%; height: 100%; border: 0; }
.vana-modal__media .vana-embed { min-height: 400px; }
@media (max-width: 768px) { .vana-modal__media .vana-embed { min-height: 250px; } }
.vana-media-container { width: 100%; max-height: 60vh; display: flex; align-items: center; justify-content: center; background: var(--vana-bg-soft); border-radius: 12px; overflow: hidden; }
.vana-media-container img { max-width: 100%; max-height: 60vh; object-fit: contain; display: block; }
.vana-modal__img { max-width: 100%; max-height: 70vh; width: auto; height: auto; object-fit: contain; display: block; }
.vana-modal__body { padding: 25px 30px; overflow-y: auto; background: #fff; }
#vanaModalMessage { padding-top: 20px; font-size: 1.1rem; line-height: 1.7; color: var(--vana-text); }
#vanaModalMessage img { margin-bottom: 20px; display: block; }
    </style>

    <header class="vana-hero">
      <div class="vana-wrap" style="padding:0;">
        <?php [$prev_id, $next_id] = vana_visit_prev_next_ids($visit_id); ?>
        <div class="vana-tour-nav">
          <div>
            <?php if ($tour_url && $tour_title): ?>
                <a href="<?php echo esc_url($tour_url); ?>" class="vana-nav-btn" style="border:none; background:transparent; padding:0; color:var(--vana-gold);">← <?php echo esc_html($tour_title); ?></a>
            <?php endif; ?>
          </div>
          <div class="vana-visit-siblings" style="display:flex; gap:10px; align-items:center;">
            <?php if ($prev_id): ?><a href="<?php echo esc_url(get_permalink($prev_id)); ?>" class="vana-nav-btn">← <?php echo ($lang === 'en' ? 'Previous' : 'Anterior'); ?></a><?php endif; ?>
            <?php if ($next_id): ?><a href="<?php echo esc_url(get_permalink($next_id)); ?>" class="vana-nav-btn"><?php echo ($lang === 'en' ? 'Next' : 'Próxima'); ?> →</a><?php endif; ?>
            
            <div class="vana-lang-switcher" style="display: inline-flex; margin-left: 10px;">
                <?php 
                $current_url = remove_query_arg('lang'); 
                if ($lang === 'pt'): 
                ?>
                    <a href="<?php echo esc_url(add_query_arg('lang', 'en', $current_url)); ?>" class="vana-nav-btn lang-btn" style="border: 2px solid var(--vana-gold); background: #fff;">
                        🇺🇸 EN
                    </a>
                <?php else: ?>
                    <a href="<?php echo esc_url(add_query_arg('lang', 'pt', $current_url)); ?>" class="vana-nav-btn lang-btn" style="border: 2px solid var(--vana-gold); background: #fff;">
                        🇧🇷 PT
                    </a>
                <?php endif; ?>
            </div>
          </div>
        </div>
        <span class="vana-badge"><?php echo ($lang === 'en' ? 'Mission Diary' : 'Diário da Missão'); ?></span>
        <h1><?php echo esc_html(get_the_title()); ?></h1>
        
        <?php if ($visit_city_ref): ?>
          <div style="margin-top: 15px; display: inline-flex; align-items: center; gap: 8px; font-weight: 700; color: var(--vana-text); background: rgba(255,255,255,0.8); padding: 6px 16px; border-radius: 20px; border: 1px solid var(--vana-line);">
            <span class="dashicons dashicons-location" style="color: var(--vana-pink);"></span>
            <?php echo esc_html($visit_city_ref); ?>
          </div>
        <?php endif; ?>
      </div>
    </header>

    <div class="vana-wrap">
      <?php if (!empty($days)): ?>
        
        <div class="vana-tabs">
          <?php foreach ($days as $i => $d):
            $date = (string)($d['date_local'] ?? '');
            $ts = $date ? strtotime($date . ' 12:00:00') : 0;
            $label = $ts ? wp_date('d/m', $ts) : ($lang === 'en' ? 'Day' : 'Dia');
            $is_active = ($i === $active_index);
            $url = add_query_arg(['v_day' => $date], get_permalink($visit_id));
            $url = remove_query_arg('vod', $url);
            // Preservar o lang na aba
            if ($lang === 'en') $url = add_query_arg('lang', 'en', $url);
          ?>
            <a href="<?php echo esc_url($url); ?>" class="vana-tab <?php echo $is_active ? 'active' : ''; ?>">
              <?php echo esc_html($label); ?>
            </a>
          <?php endforeach; ?>
        </div>

        <?php
          // PREPARAÇÃO DO PALCO PRINCIPAL
          $hero = is_array($active_day['hero'] ?? null) ? $active_day['hero'] : [];
          $schedule = is_array($active_day['schedule'] ?? null) ? $active_day['schedule'] : [];
          $galleries = is_array($active_day['galleries'] ?? null) ? $active_day['galleries'] : [];
          
          $stage_item = !empty($active_vod) ? $active_vod : $hero;
          
          $stage_title = Vana_Utils::pick_i18n_key($stage_item, 'title', $lang);
          $stage_desc = Vana_Utils::pick_i18n_key($stage_item, 'description', $lang); 
          $stage_provider = (string)($stage_item['provider'] ?? '');
          $stage_video_id = (string)($stage_item['video_id'] ?? '');
          $stage_url = (string)($stage_item['url'] ?? '');
          $stage_segments = is_array($stage_item['segments'] ?? null) ? $stage_item['segments'] : [];

          $stage_loc = is_array($stage_item['location'] ?? null) ? $stage_item['location'] : [];
          $stage_loc_name = (string)($stage_loc['name'] ?? '');
          $stage_lat = (string)($stage_loc['lat'] ?? '');
          $stage_lng = (string)($stage_loc['lng'] ?? '');
        if (!function_exists('vana_drive_file_id')) {
          function vana_drive_file_id(string $url): string
          {
            if (!$url)
              return '';
            if (preg_match('~\/d\/([a-zA-Z0-9_-]+)~', $url, $m))
              return $m[1];
            return '';
          }
        }
        if (!function_exists('vana_stage_resolve_media')) {
          function vana_stage_resolve_media(array $item): array
          {
            $provider = strtolower((string) ($item['provider'] ?? ''));
            $video_id = (string) ($item['video_id'] ?? '');
            $url = (string) ($item['url'] ?? '');

            if ($provider === 'facebook' && $url === '' && $video_id !== '' && preg_match('~^https?://~i', $video_id)) {
              $url = $video_id;
            }
            if (($provider === 'instagram' || $provider === 'drive') && $url === '' && $video_id !== '' && preg_match('~^https?://~i', $video_id)) {
              $url = $video_id;
            }
            if ($provider === 'youtube' && $video_id !== '' && !preg_match('/^[A-Za-z0-9_-]{11}$/', $video_id)) {
              if (preg_match('%(?:youtube(?:-nocookie)?\.com/(?:[^/]+/.+/|(?:v|e(?:mbed)?)/|.*[?&]v=)|youtu\.be/)([^"&?/ ]{11})%i', $video_id, $m)) {
                $video_id = $m[1];
              }
            }

            return ['provider' => $provider, 'video_id' => $video_id, 'url' => $url];
          }
        }
        ?>    
        <div class="vana-stage">
			<?php
        $has_live = false;
        if (!empty($schedule)) {
          foreach ($schedule as $it) {
            if (is_array($it) && (($it['status'] ?? '') === 'live')) {
              $has_live = true;
              break;
            }
          }
        }

      $resolved = vana_stage_resolve_media($stage_item);
      $stage_provider = (string) ($resolved['provider'] ?? $stage_provider);
      $stage_video_id = (string) ($resolved['video_id'] ?? $stage_video_id);
      $stage_url = (string) ($resolved['url'] ?? $stage_url);

      ?>

         <div class="vana-stage-video">
           <?php if ($stage_provider === 'youtube' && $stage_video_id): ?>
              <iframe id="vanaStageIframe" src="https://www.youtube-nocookie.com/embed/<?php echo esc_attr($stage_video_id); ?>?rel=0" style="position:absolute; inset:0; width:100%; height:100%; border:0;" allowfullscreen loading="lazy"></iframe>
           <?php elseif ($stage_provider === 'drive' && $stage_url): ?>
             <?php $fid = vana_drive_file_id($stage_url); ?>
             <?php if ($fid): ?>
               <iframe id="vanaStageIframe" src="https://drive.google.com/file/d/<?php echo esc_attr($fid); ?>/preview" style="position:absolute; inset:0; width:100%; height:100%; border:0;" allow="autoplay" loading="lazy"></iframe>
             <?php else: ?>
               <div style="position:absolute; inset:0; background:#fff; display:flex; align-items:center; justify-content:center;">
                  <a href="<?php echo esc_url($stage_url); ?>" target="_blank" rel="noopener" style="font-weight:900; text-decoration:none; color:var(--vana-text); font-size:1.2rem; background:var(--vana-gold); padding:12px 24px; border-radius:8px;"><?php echo ($lang === 'en' ? 'Watch on Google Drive →' : 'Abrir vídeo no Drive →'); ?></a>
                </div>
              <?php endif; ?>
            <?php elseif ($stage_provider === 'facebook' && $stage_url): ?>
              <?php
                $fb_href  = esc_url_raw($stage_url);
                $fb_embed = 'https://www.facebook.com/plugins/video.php?href=' . rawurlencode($fb_href) . '&show_text=0&width=1200';
                $fb_label = ($lang === 'en') ? 'Class (Facebook)' : 'Aula ao vivo (Facebook)';
                $fb_help  = ($lang === 'en') ? 'If the embedded player does not load, open it directly on Facebook or copy the link.' : 'Se o player embutido não carregar, abra diretamente no Facebook ou copie o link.';
                $fb_open  = ($lang === 'en') ? 'Open on Facebook →' : 'Abrir no Facebook →';
                $fb_copy  = ($lang === 'en') ? 'Copy link' : 'Copiar Link';
              ?>
              <iframe id="vanaFbIframe" src="<?php echo esc_url($fb_embed); ?>" title="<?php echo esc_attr($fb_label); ?>" style="position:absolute; inset:0; width:100%; height:100%; border:0;" scrolling="no" allow="autoplay; clipboard-write; encrypted-media; picture-in-picture; web-share" allowfullscreen="1" referrerpolicy="origin-when-cross-origin"></iframe>
              
              <div id="vanaFbFallback" style="display:none; padding:40px; text-align:center; background:rgba(255,255,255,0.92); position:absolute; inset:0; z-index:2; flex-direction:column; align-items:center; justify-content:center; backdrop-filter:blur(6px);">
                <div style="font-weight:900; color:var(--vana-text); font-size:1.3rem; margin-bottom:10px; font-family:'Syne',sans-serif;"><?php echo esc_html($fb_label); ?></div>
                <div style="color:var(--vana-muted); margin-bottom:25px; max-width:80%;"><?php echo esc_html($fb_help); ?></div>
                <div style="display:flex; gap:10px; justify-content:center; flex-wrap:wrap;">
                  <a href="<?php echo esc_url($stage_url); ?>" target="_blank" rel="noopener" style="display:inline-block; background:var(--vana-blue); color:#fff; padding:12px 24px; border-radius:8px; font-weight:900; text-decoration:none; font-size:1.05rem;"><?php echo esc_html($fb_open); ?></a>
                  <button type="button" id="vanaCopyFbLink" data-url="<?php echo esc_attr($stage_url); ?>" style="display:inline-block; background:#fff; color:var(--vana-text); border:1px solid var(--vana-line); padding:12px 24px; border-radius:8px; font-weight:900; font-size:1.05rem; cursor:pointer; transition:0.2s;"><?php echo esc_html($fb_copy); ?></button>
                </div>
              </div>

           <?php elseif ($stage_provider === 'instagram' && $stage_url): ?>
              <div style="padding:40px; text-align:center; background:#fff; position:absolute; inset:0; display:flex; flex-direction:column; align-items:center; justify-content:center;">
                <div style="font-weight:900; color:var(--vana-text); font-size:1.3rem; margin-bottom:10px; font-family:'Syne',sans-serif;"><?php echo ($lang === 'en' ? 'Class (Instagram)' : 'Aula ao vivo (Instagram)'); ?></div>
                <div style="color:var(--vana-muted); margin-bottom:20px;"><?php echo ($lang === 'en' ? 'The video opens in a new tab.' : 'O vídeo abre numa nova aba.'); ?></div>
                <a href="<?php echo esc_url($stage_url); ?>" target="_blank" rel="noopener" style="display:inline-block; background:var(--vana-pink); color:#fff; padding:12px 24px; border-radius:8px; font-weight:900; text-decoration:none; font-size:1.1rem;"><?php echo ($lang === 'en' ? 'Open on Instagram →' : 'Abrir no Instagram →'); ?></a>
              </div>
           <?php elseif ($stage_url): ?>
              <div style="position:absolute; inset:0; background:#fff; display:flex; align-items:center; justify-content:center;">
                <a href="<?php echo esc_url($stage_url); ?>" target="_blank" rel="noopener" style="font-weight:900; text-decoration:none; color:var(--vana-text); font-size:1.2rem; background:var(--vana-line); padding:12px 24px; border-radius:8px;"><?php echo ($lang === 'en' ? 'Open video link →' : 'Abrir link do vídeo →'); ?></a>
              </div>
            <?php else: ?>
              <div style="position:absolute; inset:0; background:#fff; display:flex; align-items:center; justify-content:center; color:var(--vana-muted); font-size:1.2rem; text-align:center; padding:20px;">
				<?php
					if ($has_live) {
						echo ($lang === 'en')
                          ? 'LIVE (waiting for link). The video will appear soon.'
                          : 'AO VIVO (aguardando link). O vídeo aparecerá em breve.';
                    } else {
						echo ($lang === 'en')
                          ? 'No class selected for this day.'
                          : 'Nenhuma aula selecionada para este dia.';
                    }
                    ?>
              </div>
            <?php endif; ?>
          </div>

          <div class="vana-stage-info" style="display: block;">
            <div style="display: flex; align-items: center; gap: 15px; margin-bottom: <?php echo $stage_desc ? '15px' : '0'; ?>;">
                <span class="vana-stage-info-badge"><?php echo ($lang === 'en' ? 'Class' : 'Aula'); ?></span>
                <h3 id="vanaStageTitle" style="margin:0;"><?php echo esc_html($stage_title ?: ($lang === 'en' ? 'Recording' : 'Gravação')); ?></h3>
            </div>
            
            <?php if ($stage_desc): ?>
                <div class="vana-stage-desc" style="color: var(--vana-muted); line-height: 1.6; font-size: 1.05rem;">
                    <?php echo nl2br(esc_html($stage_desc)); ?>
                </div>
            <?php endif; ?>

            <?php 
            $lat = is_numeric($stage_lat) ? (string)$stage_lat : '';
            $lng = is_numeric($stage_lng) ? (string)$stage_lng : '';
            $has_coords = ($lat !== '' && $lng !== '');
            $maps_embed = $has_coords
              ? 'https://maps.google.com/maps?q=' . rawurlencode($lat . ',' . $lng) . '&hl=' . ($lang === 'en' ? 'en' : 'pt') . '&z=15&output=embed'
              : '';
            ?>

            <?php if ($stage_loc_name || $has_coords): ?>
              <div class="vana-stage-loc" style="margin-top:16px;">
                <div style="display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap; padding:10px 14px; border-radius:12px; border:1px solid var(--vana-line); background:var(--vana-bg-soft);">
                  
                  <div style="display:flex; align-items:center; gap:10px;">
                    <span class="dashicons dashicons-location-alt" aria-hidden="true" style="color:var(--vana-pink);"></span>
                    <strong style="color:var(--vana-text);"><?php echo esc_html($stage_loc_name ?: $visit_city_ref); ?></strong>
                  </div>

                  <?php if ($has_coords): ?>
                    <button type="button" class="vana-btn" id="vanaLoadMapBtn" style="padding:8px 12px; background:#fff; border:1px solid var(--vana-line); border-radius:8px; cursor:pointer; font-weight:700;">
                      <?php echo esc_html($lang === 'en' ? 'Load map' : 'Carregar mapa'); ?>
                    </button>
                  <?php endif; ?>
                </div>

                <?php if ($has_coords): ?>
                  <div id="vanaMapWrap" style="display:none; margin-top:12px; border-radius:12px; overflow:hidden; border:1px solid var(--vana-line); height:200px; background:#e2e8f0;">
                    <iframe id="vanaMapIframe" width="100%" height="100%" style="border:0;" loading="lazy" referrerpolicy="no-referrer-when-downgrade" allowfullscreen data-src="<?php echo esc_url($maps_embed); ?>"></iframe>
                  </div>

                  <script>
                  (function(){
                    const btn = document.getElementById('vanaLoadMapBtn');
                    const wrap = document.getElementById('vanaMapWrap');
                    const iframe = document.getElementById('vanaMapIframe');
                    if (!btn || !wrap || !iframe) return;

                    btn.addEventListener('click', function(){
                      if (!iframe.getAttribute('src')) {
                        iframe.setAttribute('src', iframe.getAttribute('data-src') || '');
                      }
                      wrap.style.display = 'block';
                      btn.disabled = true;
                    });
                  })();
                  </script>
                <?php endif; ?>
              </div>
            <?php endif; ?>
          </div>
          
          <?php if (!empty($stage_segments) && $stage_provider === 'youtube'): ?>
            <div class="vana-stage-segments">
              <div style="font-weight:900; color:var(--vana-muted); margin-bottom:10px; font-size:0.9rem; text-transform:uppercase;">
                <?php echo esc_html($lang === 'en' ? 'Chapters' : 'Tópicos Abordados'); ?>
              </div>
              <div style="display:flex; flex-wrap:wrap; gap:8px;">
                <?php foreach ($stage_segments as $seg):
                  if (!is_array($seg)) continue;
                  $t = sanitize_text_field((string)($seg['t'] ?? $seg['time_local'] ?? $seg['time'] ?? ''));
                  $st = Vana_Utils::pick_i18n_key($seg, 'title', $lang);
                  if ($t === '' || $st === '') continue;
                ?>
                  <button type="button" class="vana-seg-btn" data-vana-stage-seg="1" data-t="<?php echo esc_attr($t); ?>">
                    <strong><?php echo esc_html($t); ?></strong> <?php echo esc_html($st); ?>
                  </button>
                <?php endforeach; ?>
              </div>
            </div>
          <?php endif; ?>
        </div>

        <?php if (!empty($vod_list)): ?>
          <h3 class="vana-section-title"><?php echo esc_html($lang === 'en' ? 'Classes & Recordings' : 'Aulas e Gravações'); ?></h3>
          <div class="vana-grid">
            <?php foreach ($vod_list as $i => $v):
              if (!is_array($v)) continue;
              $vid = (string)($v['video_id'] ?? '');
              $p = (string)($v['provider'] ?? '');
              $title = Vana_Utils::pick_i18n_key($v, 'title', $lang);
              $url = add_query_arg(['v_day' => $active_day_date, 'vod' => (int)$i], get_permalink($visit_id));
              if ($lang === 'en') $url = add_query_arg('lang', 'en', $url); // Preserva o idioma
              $is_active = ($i === $active_vod_index);
              $thumb = $p === 'youtube' && $vid ? "https://img.youtube.com/vi/{$vid}/hqdefault.jpg" : '';
            ?>
              <article class="vana-card" style="<?php echo $is_active ? 'border-color:var(--vana-gold); box-shadow:0 0 0 3px rgba(255,217,6,0.3);' : ''; ?>">
                <a href="<?php echo esc_url($url); ?>">
                  <div class="vana-card__media">
                    <?php if ($thumb): ?>
                      <img src="<?php echo esc_url($thumb); ?>" alt="" loading="lazy" onerror="this.style.display='none'">
                    <?php endif; ?>
                    <div class="vana-card__play"><span class="dashicons dashicons-controls-play"></span></div>
                  </div>
                  <div class="vana-card__body">
                    <h4 class="vana-card__name"><?php echo esc_html($title ?: '—'); ?></h4>
                    <div style="margin-top:auto; font-size:0.8rem; font-weight:bold; color:var(--vana-muted); text-transform:uppercase; display:flex; justify-content:space-between;">
                        <span><?php echo esc_html(strtoupper($p ?: 'LINK')); ?></span>
                        <span style="color:var(--vana-gold);"><?php echo $is_active ? ($lang === 'en' ? 'NOW PLAYING' : 'EM EXIBIÇÃO') : ''; ?></span>
                    </div>
                  </div>
                </a>
              </article>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>

        <?php if (!empty($schedule)): ?>
          <h3 class="vana-section-title"><?php echo esc_html($lang === 'en' ? 'Schedule' : 'Programação'); ?></h3>
          <div class="vana-schedule-list">
            <?php foreach ($schedule as $item): ?>
              <?php
                if (!is_array($item)) continue;

                $time  = sanitize_text_field((string)($item['time_local'] ?? ''));
                $title = Vana_Utils::pick_i18n_key($item, 'title', $lang);
                $st    = (string)($item['status'] ?? '');

                if (!$title && !$time) continue;

                $status_label = ($st === 'live')
                  ? ($lang === 'en' ? 'LIVE' : 'AO VIVO')
                  : (($st === 'done') ? ($lang === 'en' ? 'DONE' : 'Concluído') : '');

                $status_class = ($st === 'live') ? 'status-live' : (($st === 'done') ? 'status-done' : '');

                // Timestamp absoluto (Unix) do evento
                $timestamp = 0;
                if ($time && preg_match('/^\d{1,2}:\d{2}$/', trim($time))) {
                    try {
                        $dt = new DateTime("$active_day_date $time", $visit_tz);
                        $timestamp = $dt->getTimestamp();
                    } catch (Exception $e) {
                        $timestamp = 0;
                    }
                }
              ?>
              <div class="vana-schedule-item">
                <div style="width: 85px; flex-shrink: 0; display: flex; flex-direction: column; gap: 4px;">
                  <div class="vana-schedule-time" style="width:auto;"><?php echo esc_html($time ?: '—'); ?></div>

                  <?php if ($timestamp): ?>
                    <div
                      class="vana-local-time-target"
                      data-ts="<?php echo esc_attr((string)$timestamp); ?>"
                      data-label="<?php echo esc_attr($lang === 'en' ? 'Your time' : 'Seu horário'); ?>"
                      style="font-size:0.7rem; color:var(--vana-muted); font-weight:800; text-transform:uppercase; line-height:1;"
                    ></div>
                  <?php endif; ?>
                </div>

                <div class="vana-schedule-title"><?php echo esc_html($title ?: '—'); ?></div>

                <?php if ($status_label): ?>
                  <div class="vana-schedule-status <?php echo esc_attr($status_class); ?>"><?php echo esc_html($status_label); ?></div>
                <?php endif; ?>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>

        <?php if (!empty($galleries)): ?>
          <h3 class="vana-section-title"><?php echo esc_html($lang === 'en' ? 'Photos' : 'Fotos'); ?></h3>
          <?php
            $day_key = (string)($active_day['date_local'] ?? 'day');
            $all_items = [];

            foreach ($galleries as $gallery) {
              if (!is_array($gallery)) continue;
              $items = $gallery['items'] ?? null;
              if (!is_array($items)) continue;
              foreach ($items as $img) {
                if (!is_array($img)) continue;
                $thumb = (string)($img['thumb_url'] ?? '');
                $full  = (string)($img['full_url'] ?? $thumb);
                if ($thumb === '') continue;
                $all_items[] = [
                  'thumb' => $thumb,
                  'full' => $full,
                  'caption' => Vana_Utils::pick_i18n_key($img, 'caption', $lang),
                ];
              }
            }
          ?>

          <?php if (!empty($all_items)): ?>
            <div class="vana-gallery-grid">
              <?php foreach ($all_items as $idx => $it): ?>
                <div class="vana-gallery-item"
                  data-vana-photo="1"
                  data-gallery="<?php echo esc_attr($day_key); ?>"
                  data-idx="<?php echo esc_attr((int)$idx); ?>"
                  data-full="<?php echo esc_url($it['full']); ?>"
                  data-caption="<?php echo esc_attr((string)$it['caption']); ?>"
                >
                  <img src="<?php echo esc_url($it['thumb']); ?>" alt="<?php echo esc_attr((string)$it['caption']); ?>" loading="lazy" onerror="this.parentNode.style.display='none'">
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        <?php endif; ?>

      <?php else: ?>
        <div style="padding: 80px 20px; text-align: center; background: #fff; border-radius: 16px; border: 1px solid var(--vana-line);">
          <span class="dashicons dashicons-update" style="font-size: 3rem; color: var(--vana-line); margin-bottom: 20px;"></span>
          <h2 style="font-family:'Syne',sans-serif; color: var(--vana-text);"><?php echo ($lang === 'en' ? 'Diary Processing' : 'Diário em Processamento'); ?></h2>
        </div>
      <?php endif; ?>

      <section style="margin-top: 60px; padding-top: 40px; border-top: 2px solid var(--vana-line);">
        <h2 class="vana-section-title" style="border:none; margin-bottom:0; padding-left:0;">
          <span class="dashicons dashicons-heart" style="font-size: 2rem; color: var(--vana-pink);"></span> <?php echo ($lang === 'en' ? 'Sangha Moments' : 'Momentos da Sangha'); ?>
        </h2>
        <p style="color: var(--vana-muted); font-size: 1.1rem; margin-bottom: 30px;"><?php echo ($lang === 'en' ? 'Share your moments from this visit.' : 'Partilhe os seus momentos e relatos desta visita.'); ?></p>

        <?php
        $submissions = new WP_Query([
            'post_type' => 'vana_submission', 'post_status' => 'publish', 'posts_per_page' => 48,
            'meta_query' => [['key' => '_visit_id', 'value' => $visit_id, 'compare' => '=', 'type' => 'NUMERIC']]
        ]);
        function vana_format_date_local($ts): string { return $ts ? wp_date('d/m/Y', (int)$ts) : ''; }
        ?>

        <?php if ($submissions->have_posts()): ?>
        <div class="vana-sangha-wall">
          <?php while ($submissions->have_posts()): $submissions->the_post();
            $sid = get_the_ID();
            $name_raw = (string) get_post_meta($sid, '_sender_display_name', true);
            $name = trim($name_raw) !== '' ? trim($name_raw) : ($lang === 'en' ? 'Anonymous' : 'Anônimo');
            $msg = wp_strip_all_tags((string) get_post_meta($sid, '_message', true));
            $image = esc_url((string) get_post_meta($sid, '_image_url', true));
            $external_raw = (string) get_post_meta($sid, '_external_url', true);
            $is_video = trim($external_raw) !== '';
            $ts = (int) get_post_meta($sid, '_submitted_at', true);
            $date = $ts ? vana_format_date_local($ts) : '';
            
            // Lógica para gerar Thumb (YouTube, Facebook ou Google Drive)
            $video_thumb = '';
            $provider_type = ''; 

            if ($is_video) {
                if (preg_match('%(?:youtube(?:-nocookie)?\.com/(?:[^/]+/.+/|(?:v|e(?:mbed)?)/|.*[?&]v=)|youtu\.be/)([^"&?/ ]{11})%i', $external_raw, $match)) {
                    $video_thumb = "https://img.youtube.com/vi/{$match[1]}/hqdefault.jpg";
                    $provider_type = 'youtube';
                } 
                else if (strpos($external_raw, 'drive.google.com') !== false) {
                    if (preg_match('~\/d\/([a-zA-Z0-9_-]+)~', $external_raw, $m)) {
                        $video_thumb = "https://drive.google.com/thumbnail?id=" . $m[1] . "&sz=w400";
                    }
                    $provider_type = 'drive';
                }
                else if (strpos($external_raw, 'facebook.com') !== false || strpos($external_raw, 'fb.watch') !== false) {
                    $video_thumb = 'FB_VIDEO'; 
                    $provider_type = 'facebook';
                }
            }    

            $is_text_only = (!$image && !$video_thumb);
            $moment_class = $is_text_only ? 'vana-moment vana-moment--text-only' : 'vana-moment';
            $initial = mb_strtoupper(mb_substr($name, 0, 1));
            $kicker = $lang === 'en' ? 'Sangha realization' : 'Relato da Sangha';
            
            // Badge dinâmico para o rodapé
            $badge_label = $is_video ? ($lang === 'en' ? 'Video' : 'Vídeo') : ($image ? ($lang === 'en' ? 'Photo' : 'Foto') : ($lang === 'en' ? 'Message' : 'Mensagem'));
            $badge_icon = $is_video ? 'dashicons-video-alt3' : ($image ? 'dashicons-format-image' : 'dashicons-format-quote');
          ?>
            <article class="<?php echo esc_attr($moment_class); ?>">
            <button type="button" class="vana-moment-btn" 
              data-vana-modal-open="1"
              data-vana-sangha-item="1"  data-kicker="<?php echo esc_attr($kicker); ?>"
              data-title="<?php echo esc_attr($name); ?>"
              data-message="<?php echo esc_attr($msg); ?>"
              data-image="<?php echo esc_attr($image); ?>"
              data-external-url="<?php echo esc_attr($external_raw); ?>">
                
                <div class="vana-moment-inner">
                  <div class="vana-moment-user">
                    <div class="vana-moment-avatar"><?php echo esc_html($initial); ?></div>
                    <div class="vana-moment-name"><?php echo esc_html($name); ?></div>
                  </div>

                  <?php if ($msg): ?>
                    <div class="vana-moment-text"><?php echo esc_html($msg); ?></div>
                  <?php endif; ?>
                </div>

                <?php if ($image): ?>
                  <div class="vana-moment-media">
                    <img src="<?php echo $image; ?>" alt="" loading="lazy">
                  </div>
                <?php elseif ($video_thumb): ?>
                  <div class="vana-moment-media" style="position:relative; background:#f1f5f9; border-radius:12px; overflow:hidden; aspect-ratio:16/9; display:flex; align-items:center; justify-content:center;">
                    <?php if ($video_thumb === 'FB_VIDEO'): ?>
                        <div style="position:absolute; inset:0; background:#1877F2; display:flex; align-items:center; justify-content:center; flex-direction:column; color:#fff;">
                            <span class="dashicons dashicons-facebook-alt" style="font-size:40px; width:40px; height:40px;"></span>
                            <small style="font-weight:bold; margin-top:5px;">Facebook Video</small>
                        </div>
                    <?php else: ?>
                        <img src="<?php echo esc_url($video_thumb); ?>" alt="Video" style="width:100%; height:100%; object-fit:cover; opacity:0.8;" onerror="this.previousElementSibling.style.display='flex'; this.style.display='none';">
                        <div style="position:absolute; top:50%; left:50%; transform:translate(-50%,-50%); color:#fff; font-size:40px; text-shadow: 0 2px 10px rgba(0,0,0,0.3);">
                            <span class="dashicons <?php echo ($provider_type === 'drive') ? 'dashicons-googleicon' : 'dashicons-controls-play'; ?>"></span>
                        </div>
                    <?php endif; ?>
                  </div>
                <?php endif; ?>

                <div class="vana-moment-footer">
                  <span class="vana-moment-badge">
                    <span class="dashicons <?php echo esc_attr($badge_icon); ?>"></span>
                    <?php echo esc_html($badge_label); ?>
                  </span>
                  
                  <?php 
                  $public_city = get_post_meta($sid, '_vana_public_user_city', true);
                  if ($public_city): ?>
                    <span style="display:flex; align-items:center; gap:5px; color:var(--vana-muted); font-size:0.8rem; font-weight:800;">
                      <span class="dashicons dashicons-location" style="font-size:14px; color:var(--vana-pink);"></span>
                      <?php echo esc_html($public_city); ?>
                    </span>
                  <?php else: ?>
                    <span><?php echo esc_html($date); ?></span>
                  <?php endif; ?>
                </div>
              </button>
            </article>
          <?php endwhile; wp_reset_postdata(); ?>
        </div>        
        <?php endif; ?>

        <div id="form-oferenda" class="vana-form-wrap">
          <h3 style="font-family:'Syne',sans-serif; font-size: 1.8rem; margin:0 0 5px; color:var(--vana-text);">
            <?php echo esc_html($lang === 'en' ? 'Submit your Offering' : 'Enviar Oferenda'); ?>
          </h3>
          
          <form
            id="vanaCheckinForm"
            enctype="multipart/form-data"
            data-i18n-sending="<?php echo esc_attr($lang === 'en' ? 'Sending...' : 'Enviando...'); ?>"
            data-i18n-submit="<?php echo esc_attr($lang === 'en' ? 'Send Offering' : 'Enviar Oferenda'); ?>"
            data-i18n-ok="<?php echo esc_attr($lang === 'en' ? 'Sent! Awaiting moderation.' : 'Enviado! Aguardando moderação.'); ?>"
            data-i18n-err="<?php echo esc_attr($lang === 'en' ? 'Error.' : 'Erro.'); ?>"
            data-i18n-conn="<?php echo esc_attr($lang === 'en' ? 'Connection error.' : 'Erro de conexão.'); ?>"
            data-i18n-rate="<?php echo esc_attr($lang === 'en' ? 'Too many submissions. Please try again later.' : 'Muitos envios em pouco tempo. Tente mais tarde.'); ?>"
          >
            <input type="hidden" name="visit_id" value="<?php echo esc_attr($visit_id); ?>">
            <input type="text" name="website" style="display:none" tabindex="-1">

            <input type="hidden" name="user_lat" id="vana_lat" value="">
            <input type="hidden" name="user_lng" id="vana_lng" value="">

            <div class="vana-field" style="margin: 20px 0 15px; padding: 16px; border: 1px solid var(--vana-line); border-radius: 12px; background: var(--vana-bg-soft);">
              
              <div style="font-weight:700; color:var(--vana-text); margin-bottom: 15px;">
                <span class="dashicons dashicons-admin-site" style="color: var(--vana-gold); margin-right:5px;"></span>
                <?php echo esc_html($lang === 'en' ? 'Global Sangha Map (Optional)' : 'Mapa Global da Sangha (Opcional)'); ?>
              </div>

              <div style="background:#fff; border:1px solid var(--vana-line); padding:15px; border-radius:8px; margin-bottom:15px;">
                
                <label style="display:flex; gap:10px; align-items:flex-start; cursor:pointer;">
                  <input type="checkbox" name="consent_city_public" id="consentCityPublic" value="1" style="width:auto; margin-top:4px;">
                  <div>
                    <span style="display:block; font-weight:700; font-size:0.95rem;">
                      <?php echo esc_html($lang === 'en' ? 'Display my city on the public card' : 'Exibir minha cidade no cartão público'); ?>
                    </span>
                    <span style="display:block; color: var(--vana-muted); font-size:0.85rem; margin-top:4px;">
                      <?php echo esc_html($lang === 'en' 
                        ? 'Your city/country will appear publicly to inspire other devotees.' 
                        : 'Sua cidade/país aparecerá publicamente para inspirar outros devotos.'); ?>
                    </span>
                  </div>
                </label>

                <div id="vanaGeoFallback" style="display:none; margin-top:12px; padding-top:12px; border-top:1px dashed var(--vana-line);">
                  
                  <select name="user_city_fallback" id="vana_city_fallback" style="width:100%; padding:10px; border:1px solid var(--vana-line); border-radius:8px;">
                    <option value=""><?php echo esc_html($lang === 'en' ? 'Select...' : 'Selecione...'); ?></option>
                    
                    <optgroup label="<?php echo esc_attr($lang === 'en' ? 'Sacred Dhamas' : 'Dhamas Sagrados'); ?>">
                      <option value="navadvipa_in">Navadvīpa, Índia</option>
                      <option value="vrindavan_in">Vṛndāvana, Índia</option>
                      <option value="govardhan_in">Govardhana, Índia</option>
                      <option value="mayapur_in">Māyāpur, Índia</option>
                    </optgroup>
                    
                    <optgroup label="<?php echo esc_attr($lang === 'en' ? 'Cities' : 'Cidades'); ?>">
                      <option value="sao_paulo_br">São Paulo, Brasil</option>
                      <option value="rio_br">Rio de Janeiro, Brasil</option>
                      <option value="lisbon_pt">Lisboa, Portugal</option>
                      <option value="porto_pt">Porto, Portugal</option>
                    </optgroup>
                    
                    <optgroup label="<?php echo esc_attr($lang === 'en' ? 'Countries' : 'Países'); ?>">
                      <option value="brazil">Brasil</option>
                      <option value="india">Índia</option>
                      <option value="portugal">Portugal</option>
                    </optgroup>
                    
                    <option value="other"><?php echo esc_html($lang === 'en' ? 'Other location...' : 'Outro local...'); ?></option>
                  </select>

                  <div id="vanaCityOtherWrap" style="display:none; margin-top:10px;">
                    <input type="text" name="user_city_other" id="vana_city_other" maxlength="80"
                           style="width:100%; padding:10px; border:1px solid var(--vana-line); border-radius:8px;"
                           placeholder="<?php echo esc_attr($lang === 'en' ? 'City, Country or just Country' : 'Cidade, País ou apenas País'); ?>">
                  </div>
                </div>

              </div>

              <div style="background:#fff; border:1px solid var(--vana-line); padding:15px; border-radius:8px; display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:12px;">
                
                <div style="flex:1; min-width:200px;">
                  <span style="display:block; font-weight:700; font-size:0.95rem;">
                    <?php echo esc_html($lang === 'en' ? 'Help build the Global Heatmap' : 'Ajudar no Mapa de Calor Global'); ?>
                  </span>
                  <span style="display:block; color: var(--vana-muted); font-size:0.85rem; margin-top:4px;">
                    <?php echo esc_html($lang === 'en' 
                      ? 'Your exact GPS is blurred (~5km grid), kept private, and deleted after 90 days.' 
                      : 'Seu GPS exato é ofuscado (grid ~5km), mantido privado e apagado após 90 dias.'); ?>
                  </span>
                </div>
                
                <button type="button" id="vanaGeoBtn" data-i18n-ask="<?php echo esc_attr($lang==='en'?'Requesting...':'Solicitando...'); ?>" style="background: var(--vana-gold); border:1px solid var(--vana-line); padding:10px 16px; border-radius:8px; cursor:pointer; font-weight:700;">
                  <span class="dashicons dashicons-location" style="color:#111;"></span>
                  <?php echo esc_html($lang === 'en' ? 'Use my GPS' : 'Usar meu GPS'); ?>
                </button>
              </div>
              
              <div id="vanaGeoStatus" style="margin-top:10px; font-size:0.85rem; font-weight:600; text-align:right;"></div>

            </div>

            <div style="margin-bottom:15px;"><label><?php echo $lang === 'en' ? 'Name' : 'Nome'; ?></label><input type="text" name="sender_name" style="width:100%; padding:10px; border:1px solid var(--vana-line); border-radius:6px;"></div>
            <div style="margin-bottom:15px;"><label><?php echo $lang === 'en' ? 'Message' : 'Mensagem'; ?></label><textarea name="message" rows="3" style="width:100%; padding:10px; border:1px solid var(--vana-line); border-radius:6px;"></textarea></div>
            <div style="margin-bottom:15px;"><label><?php echo $lang === 'en' ? 'Photo (Max 5MB)' : 'Foto (Máx 5MB)'; ?></label><input type="file" name="image" accept="image/png,image/jpeg,image/webp" style="width:100%; background:#fff; border:1px dashed var(--vana-line); padding:10px; border-radius:6px;"></div>
            <div style="margin-bottom:20px;"><label><?php echo $lang === 'en' ? 'Video Link (Optional)' : 'Link de Vídeo (Opcional)'; ?></label><input type="url" name="external_url" placeholder="https://..." style="width:100%; padding:10px; border:1px solid var(--vana-line); border-radius:6px;"></div>
            
            <label style="display:flex; gap:12px; margin:20px 0; align-items:center; background:var(--vana-bg-soft); padding:15px; border-radius:8px; border:1px solid var(--vana-line); cursor:pointer;">
              <input type="checkbox" name="consent_publish" value="1" required style="width:auto; margin:0;"><span style="color:var(--vana-text); font-weight:600;"><?php echo $lang === 'en' ? 'I authorize publication on the site.' : 'Autorizo a publicação no site.'; ?></span>
            </label>
            
            <button type="submit" id="btnSubmitCheckin" style="background:var(--vana-gold); color:#000; border:none; padding:16px; border-radius:8px; font-weight:800; width:100%; font-size:1.1rem; cursor:pointer; font-family:'Syne',sans-serif; transition:0.2s;">
              <?php echo esc_html($lang === 'en' ? 'Send Offering' : 'Enviar Oferenda'); ?>
            </button>
            <div id="checkinResponse" style="margin-top:15px; font-weight:700; text-align:center;"></div>
          </form>
        </div>
      </section>
    </div>

    <div class="vana-modal" id="vanaSubmissionModal" aria-hidden="true">
      <div class="vana-modal__backdrop" data-vana-modal-close="1"></div>
      <div class="vana-modal__dialog" role="dialog" aria-modal="true">
        <button type="button" class="vana-modal__close" data-vana-modal-close="1">×</button>
        
        <button type="button" class="vana-modal__nav vana-modal__nav--prev" id="vanaModalPrev" style="display:none;">‹</button>
        <button type="button" class="vana-modal__nav vana-modal__nav--next" id="vanaModalNext" style="display:none;">›</button>
        
        <div class="vana-modal__media" id="vanaModalMedia"></div>
        <div class="vana-modal__body">
          <p class="vana-modal__kicker" id="vanaModalKicker" style="color:var(--vana-orange); font-weight:800; font-size:0.85rem; margin:0 0 5px; text-transform:uppercase;"></p>
          <h3 class="vana-modal__title" id="vanaModalTitle" style="color:var(--vana-text); margin:0 0 10px; font-family:'Syne',sans-serif;"></h3>
          <p class="vana-modal__message" id="vanaModalMessage" style="color:var(--vana-muted); line-height:1.6; margin:0; font-size:1.05rem;"></p>
          <div style="margin-top:20px;"><a id="vanaModalExternalLink" href="#" target="_blank" style="display:none; background:var(--vana-blue); color:#fff; padding:10px 20px; border-radius:6px; text-decoration:none; font-weight:bold;"><?php echo ($lang === 'en' ? 'Open Original Link →' : 'Abrir Link Original →'); ?></a></div>
        </div>
      </div>
    </div>

<script>
// =====================================
// UTILIDADES: TEMPO E PROVIDERS
// =====================================
function parseYouTubeId(url){
    try {
        const u = new URL(url);
        if (u.hostname.includes('youtu.be')) return u.pathname.replace('/', '') || '';
        const v = u.searchParams.get('v');
        if (v) return v;
        const m = u.pathname.match(/\/shorts\/([^\/]+)/);
        return (m && m[1]) ? m[1] : '';
    } catch(e){ return ''; }
}

function timeToSeconds(t){
    const parts = String(t).trim().split(':').map(n => parseInt(n,10));
    if (parts.some(Number.isNaN)) return 0;
    if (parts.length === 2) return parts[0]*60 + parts[1];
    if (parts.length === 3) return parts[0]*3600 + parts[1]*60 + parts[2];
    return 0;
}

// =====================================
// CONVERSÃO DE FUSO HORÁRIO (SCHEDULE)
// =====================================
function vanaRenderLocalTimes(){
    const timeTargets = document.querySelectorAll('.vana-local-time-target');
    if (!timeTargets || timeTargets.length === 0) return;

    const formatter = new Intl.DateTimeFormat(navigator.language || 'pt-BR', {
        hour: '2-digit',
        minute: '2-digit',
        hour12: false
    });

    timeTargets.forEach(el => {
        const ts = parseInt(el.getAttribute('data-ts') || '0', 10);
        if (!ts) return;
        const localTime = formatter.format(new Date(ts * 1000));
        const label = el.getAttribute('data-label') || '';
        el.textContent = label ? `${localTime} ${label}` : localTime;
    });
}

// =====================================
// FUNÇÕES DE RENDERIZAÇÃO
// =====================================
function renderVanaPhoto(idx, currentSet) {
    const item = currentSet[idx];
    const mediaEl = document.getElementById('vanaModalMedia');
    const msgEl = document.getElementById('vanaModalMessage');
    if(!item || !mediaEl) return;

    mediaEl.innerHTML = `<img class="vana-modal__img" src="${item.dataset.full}" alt="">`;
    document.getElementById('vanaModalKicker').textContent = '<?php echo $lang === "en" ? "Photo" : "Foto"; ?>';
    document.getElementById('vanaModalTitle').textContent = item.dataset.caption || '';
    msgEl.innerHTML = '';
    document.getElementById('vanaModalExternalLink').style.display = 'none';
}

function renderMedia(data) {
    const mediaEl = document.getElementById('vanaModalMedia');
    const msgEl = document.getElementById('vanaModalMessage');
    if(!mediaEl || !msgEl) return;

    const ext = (data.externalUrl || '').trim();
    const image = (data.image || '').trim();
    const message = (data.message || '').trim();
    
    const ytid = parseYouTubeId(ext);
    const isFB = ext.includes('facebook.com') || ext.includes('fb.watch');
    const isDrive = ext.includes('drive.google.com');
    const hasVideo = (ytid || isFB || isDrive);

    mediaEl.innerHTML = '';

    if(ytid){
        mediaEl.innerHTML = `<div class="vana-embed"><iframe src="https://www.youtube.com/embed/${ytid}?autoplay=1" frameborder="0" allowfullscreen></iframe></div>`;
    } else if(isFB){
        const fbUrl = `https://www.facebook.com/plugins/video.php?href=${encodeURIComponent(ext)}&show_text=0&width=1200`;
        mediaEl.innerHTML = `<div class="vana-embed"><iframe src="${fbUrl}" scrolling="no" frameborder="0" allowfullscreen="true" allow="autoplay; encrypted-media;"></iframe></div>`;
    } else if(isDrive){
        const dId = ext.match(/d\/([a-zA-Z0-9_-]+)/);
        mediaEl.innerHTML = dId ? `<div class="vana-embed"><iframe src="https://drive.google.com/file/d/${dId[1]}/preview" allow="autoplay"></iframe></div>` : '';
    } else if(image){
        mediaEl.innerHTML = `<div class="vana-media-container"><img src="${image}" alt=""></div>`;
    } else {
        mediaEl.innerHTML = `<div style="padding:60px; text-align:center; background:var(--vana-bg-soft); width:100%;"><span class="dashicons dashicons-format-quote" style="font-size:4rem; color:var(--vana-gold); opacity:0.6;"></span></div>`;
    }

    let bodyHTML = '';
    if(hasVideo && image) {
        bodyHTML += `<div style="margin-bottom:20px; border-radius:12px; overflow:hidden; border:1px solid var(--vana-line);"><img src="${image}" style="width:100%; height:auto; display:block;"></div>`;
    }
    if(message) {
        bodyHTML += `<div style="white-space:pre-wrap; font-size:1.1rem; line-height:1.6; color:var(--vana-text);">${message}</div>`;
    } else if(!hasVideo && !image) {
        bodyHTML += `<div style="text-align:center; font-style:italic; color:var(--vana-muted); padding:20px 0;"><?php echo $lang === 'en' ? 'Silent offering.' : 'Oferenda silenciosa.'; ?></div>`;
    }
    msgEl.innerHTML = bodyHTML;

    document.getElementById('vanaModalKicker').textContent = data.kicker || '<?php echo $lang === "en" ? "Sangha Realization" : "Relato da Sangha"; ?>';
    document.getElementById('vanaModalTitle').textContent = data.title || '<?php echo $lang === "en" ? "Anonymous" : "Anônimo"; ?>';
    
    const linkEl = document.getElementById('vanaModalExternalLink');
    if(ext){
        linkEl.href = ext; linkEl.style.display = 'inline-block';
        if(ytid) linkEl.textContent = '<?php echo $lang === "en" ? "Watch on YouTube →" : "Ver no YouTube →"; ?>';
        else if(isFB) linkEl.textContent = '<?php echo $lang === "en" ? "Watch on Facebook →" : "Ver no Facebook →"; ?>';
        else if(isDrive) linkEl.textContent = '<?php echo $lang === "en" ? "Watch on Drive →" : "Ver no Drive →"; ?>';
        else linkEl.textContent = '<?php echo $lang === "en" ? "View original →" : "Ver original →"; ?>';
    } else { linkEl.style.display = 'none'; }
}

// =====================================
// SISTEMA UNIFICADO DE NAVEGAÇÃO
// =====================================
(function(){
    const modal = document.getElementById('vanaSubmissionModal');
    const btnPrev = document.getElementById('vanaModalPrev');
    const btnNext = document.getElementById('vanaModalNext');

    let currentSet = [];
    let currentIndex = -1;
    let navMode = 'media';

    function navigate(delta) {
        if (currentSet.length <= 1) return;
        currentIndex = (currentIndex + delta + currentSet.length) % currentSet.length;
        const item = currentSet[currentIndex];
        navMode === 'photo' ? renderVanaPhoto(currentIndex, currentSet) : renderMedia(item.dataset);
    }

    document.addEventListener('click', function(e){
        const segBtn = e.target.closest('[data-vana-stage-seg="1"]');
        if(segBtn){
            const secs = timeToSeconds(segBtn.dataset.t);
            const iframe = document.getElementById('vanaStageIframe');
            if(iframe){
                const u = new URL(iframe.src);
                u.searchParams.set('start', String(secs)); u.searchParams.set('autoplay', '1');
                iframe.src = u.toString();
            }
            return;
        }

        const photoBtn = e.target.closest('[data-vana-photo="1"]:not([data-vana-sangha-item])');
        if(photoBtn){
            const group = photoBtn.dataset.gallery || 'default';
            currentSet = Array.from(document.querySelectorAll(`[data-vana-photo="1"][data-gallery="${group}"]`));
            currentIndex = currentSet.indexOf(photoBtn);
            navMode = 'photo';
            renderVanaPhoto(currentIndex, currentSet);
            modal.classList.add('is-active');
            modal.setAttribute('aria-hidden', 'false');
            if(btnPrev) btnPrev.style.display = currentSet.length > 1 ? 'flex' : 'none';
            if(btnNext) btnNext.style.display = currentSet.length > 1 ? 'flex' : 'none';
            return;
        }

        const sanghaBtn = e.target.closest('[data-vana-sangha-item="1"]');
        if(sanghaBtn){
            currentSet = Array.from(document.querySelectorAll('[data-vana-sangha-item="1"]'));
            currentIndex = currentSet.indexOf(sanghaBtn);
            navMode = 'sangha';
            renderMedia(sanghaBtn.dataset);
            modal.classList.add('is-active');
            modal.setAttribute('aria-hidden', 'false');
            if(btnPrev) btnPrev.style.display = currentSet.length > 1 ? 'flex' : 'none';
            if(btnNext) btnNext.style.display = currentSet.length > 1 ? 'flex' : 'none';
            return;
        }

        if(e.target.closest('[data-vana-modal-close]') || (modal && e.target === modal.querySelector('.vana-modal__backdrop'))){
            modal.classList.remove('is-active');
            modal.setAttribute('aria-hidden', 'true');
            document.getElementById('vanaModalMedia').innerHTML = '';
        }
    });

    if(btnPrev) btnPrev.addEventListener('click', (e) => { e.stopPropagation(); navigate(-1); });
    if(btnNext) btnNext.addEventListener('click', (e) => { e.stopPropagation(); navigate(1); });

    document.addEventListener('keydown', (e) => {
        if(!modal || modal.getAttribute('aria-hidden') === 'true') return;
        if(e.key === 'ArrowLeft') navigate(-1);
        if(e.key === 'ArrowRight') navigate(1);
        if(e.key === 'Escape') {
            modal.classList.remove('is-active');
            modal.setAttribute('aria-hidden', 'true');
            document.getElementById('vanaModalMedia').innerHTML = '';
        }
    });

    const fbIframe = document.getElementById('vanaFbIframe');
    const fallback = document.getElementById('vanaFbFallback');
    if(fbIframe && fallback) {
        let loaded = false;
        fbIframe.addEventListener('load', () => { loaded = true; fallback.style.display = 'none'; });
        fbIframe.addEventListener('error', () => { fallback.style.display = 'flex'; });
        setTimeout(() => { if(!loaded) fallback.style.display = 'flex'; }, 10000);

        const btnCopy = document.getElementById('vanaCopyFbLink');
        if(btnCopy){
            btnCopy.addEventListener('click', async () => {
                const url = btnCopy.dataset.url || '';
                if(!url) return;
                try{
                    if(navigator.clipboard && window.isSecureContext){
                        await navigator.clipboard.writeText(url);
                        const old = btnCopy.textContent; 
                        btnCopy.textContent = '<?php echo $lang === "en" ? "Copied! ✓" : "Copiado! ✓"; ?>';
                        setTimeout(() => btnCopy.textContent = old, 2000);
                    } else { window.prompt('<?php echo $lang === "en" ? "Copy link:" : "Copie o link:"; ?>', url); }
                } catch(err){ window.prompt('<?php echo $lang === "en" ? "Copy link:" : "Copie o link:"; ?>', url); }
            });
        }
    }

    // Renderiza horários convertidos do Schedule
    vanaRenderLocalTimes();
})();

// =====================================
// FORMULÁRIO DE ENVIO E GEOLOCALIZAÇÃO
// =====================================
(function() {
  const chkCity = document.getElementById('consentCityPublic');
  const fallbackWrap = document.getElementById('vanaGeoFallback');
  const citySelect = document.getElementById('vana_city_fallback');
  const otherWrap = document.getElementById('vanaCityOtherWrap');
  const geoBtn = document.getElementById('vanaGeoBtn');
  const geoStatus = document.getElementById('vanaGeoStatus');
  const latEl = document.getElementById('vana_lat');
  const lngEl = document.getElementById('vana_lng');

  // Mostrar dropdown quando marcar
  if (chkCity && fallbackWrap) {
    chkCity.addEventListener('change', (e) => {
      fallbackWrap.style.display = e.target.checked ? 'block' : 'none';
    });
  }

  // Mostrar campo "Outro"
  if (citySelect && otherWrap) {
    citySelect.addEventListener('change', (e) => {
      otherWrap.style.display = (e.target.value === 'other') ? 'block' : 'none';
    });
  }

  // Capturar GPS
  if (geoBtn && latEl && lngEl && geoStatus) {
    geoBtn.addEventListener('click', function() {
      if (!navigator.geolocation) {
        geoStatus.textContent = '<?php echo $lang === "en" ? "GPS not supported by browser." : "GPS não suportado pelo navegador."; ?>';
        geoStatus.style.color = '#dc2626';
        return;
      }

      geoBtn.disabled = true;
      geoBtn.style.opacity = '0.6';
      geoStatus.textContent = geoBtn.dataset.i18nAsk || '<?php echo $lang === "en" ? "Requesting..." : "Solicitando..."; ?>';

      navigator.geolocation.getCurrentPosition(
        function(pos) {
          const lat = pos?.coords?.latitude;
          const lng = pos?.coords?.longitude;

          if (lat != null && lng != null) {
            latEl.value = String(lat);
            lngEl.value = String(lng);
            geoStatus.textContent = '<?php echo $lang === "en" ? "✓ GPS captured (~5km grid, private, 90 days)" : "✓ GPS capturado (grid ~5km, privado, 90 dias)"; ?>';
            geoStatus.style.color = '#16a34a';
          }
        },
        function(error) {
          geoStatus.textContent = '<?php echo $lang === "en" ? "Permission denied or failed." : "Permissão negada ou falhou."; ?>';
          geoStatus.style.color = '#dc2626';
          geoBtn.disabled = false;
          geoBtn.style.opacity = '1';
        }
      );
    });
  }

  // --- Submit do formulário ---
  const form = document.getElementById('vanaCheckinForm');
  if(!form) return;

  const t = (key, fallback) => (form.dataset && form.dataset[key]) ? form.dataset[key] : fallback;

  form.addEventListener('submit', async function(e){
    e.preventDefault();

    const btn = document.getElementById('btnSubmitCheckin');
    const out = document.getElementById('checkinResponse');
    if (!btn || !out) return;

    // Guardar estado
    const keep = {
      lat: latEl ? latEl.value : '',
      lng: lngEl ? lngEl.value : '',
      chk: chkCity ? chkCity.checked : false,
      city: citySelect ? citySelect.value : '',
      other: document.getElementById('vana_city_other') ? document.getElementById('vana_city_other').value : ''
    };

    btn.disabled = true;
    btn.textContent = t('i18nSending', '<?php echo $lang === "en" ? "Sending..." : "Enviando..."; ?>');
    out.style.color = 'var(--vana-muted)';
    out.textContent = '';

    try {
      const res = await fetch('/wp-json/vana/v1/checkin', { method: 'POST', body: new FormData(form) });
      const json = await res.json().catch(() => ({}));

      if (res.status === 429) {
        out.style.color = '#dc2626';
        out.textContent = t('i18nRate', '<?php echo $lang === "en" ? "Too many submissions. Please try again later." : "Muitos envios em pouco tempo. Tente mais tarde."; ?>');
        return;
      }

      if (json && json.success) {
        out.style.color = '#16a34a';
        out.textContent = json.message || t('i18nOk', '<?php echo $lang === "en" ? "Sent! Awaiting moderation." : "Enviado! Aguardando moderação."; ?>');

        form.reset();

        // Restaurar estado do GPS
        if (latEl) latEl.value = keep.lat;
        if (lngEl) lngEl.value = keep.lng;
        if (chkCity) { chkCity.checked = keep.chk; chkCity.dispatchEvent(new Event('change')); }
        if (citySelect) { citySelect.value = keep.city; citySelect.dispatchEvent(new Event('change')); }
        if (document.getElementById('vana_city_other')) { document.getElementById('vana_city_other').value = keep.other; }
        
        if (keep.lat) {
            geoBtn.disabled = true;
            geoBtn.style.opacity = '0.6';
        }
      } else {
        out.style.color = '#dc2626';
        out.textContent = (json && json.message) ? json.message : t('i18nErr', '<?php echo $lang === "en" ? "Error." : "Erro."; ?>');
      }
    } catch(err) {
      out.style.color = '#dc2626';
      out.textContent = t('i18nConn', '<?php echo $lang === "en" ? "Connection error." : "Erro de conexão."; ?>');
    } finally {
      btn.disabled = false;
      btn.textContent = t('i18nSubmit', '<?php echo $lang === "en" ? "Send Offering" : "Enviar Oferenda"; ?>');
    }
  });
})();
</script>

    <?php do_action('astra_content_after'); ?>
  </main>
  <?php do_action('astra_primary_content_after'); ?>
</div>
<?php do_action('astra_primary_content_bottom'); get_footer(); ?>