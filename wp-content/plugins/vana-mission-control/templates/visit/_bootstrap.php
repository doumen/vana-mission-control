<?php
/**
 * Bootstrap SSR — single-vana_visit
 *
 * Chamado no topo do template single da visita (antes do get_header).
 * Resolve VisitStageViewModel e expõe variáveis via extract().
 *
 * Variáveis disponíveis após este arquivo:
 *
 *   — Do ViewModel (to_template_vars):
 *       $visit_id, $visit_ref, $timeline, $overrides,
 *       $active_day, $active_day_date, $active_events, $active_event,
 *       $hero, $stage_mode,
 *       $viewer_mode, $viewer_event_key, $viewer_item_id,
 *       $editorial_hero_type, $editorial_hero_event_key, $editorial_hero_item_id,
 *       $visit_timezone, $visit_status
 *
 *   — Derivadas (calculadas aqui):
 *       $lang               string     'pt'|'en'
 *       $data               array      alias de $timeline
 *       $days               array      $timeline['days']
 *       $active_index       int        índice do dia ativo em $days
 *       $tour_id            int        ID do post pai (tour)
 *       $tour_url           string     permalink do tour
 *       $tour_title         string     título do tour
 *       $country_code       string     ISO 3166-1 alpha-2 (BR, IN, AR…)
 *       $visit_city_ref     string     referência da cidade
 *       $visit_tz_str       string     string do timezone
 *       $visit_tz           DateTimeZone objeto timezone
 *       $prev_visit         array|null {id, permalink, title, has_mag, country_code}
 *       $next_visit         array|null {id, permalink, title, has_mag, country_code}
 *
 * @package VanaMissionControl
 * @since   3.1.0
 * v3.1 — 2026-03-24
 * + $country_code exposto para hero-header.php
 * + country_code adicionado ao array de prev_visit / next_visit
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// ── Guard: evita double-resolve em includes aninhados ─────────────────────────
if ( isset( $vana_bootstrap_loaded ) && $vana_bootstrap_loaded === true ) {
    return;
}
$vana_bootstrap_loaded = true;

// ── PRE-LOAD: Carrega funções utilitárias do Stage antes das parts ─────────────
$vana_stage_file = defined( 'VANA_MC_PATH' )
    ? VANA_MC_PATH . 'inc/vana-stage.php'
    : dirname( __DIR__, 2 ) . '/inc/vana-stage.php';

if ( file_exists( $vana_stage_file ) ) {
    require_once $vana_stage_file;
}

// ── Dependências do plugin ────────────────────────────────────────────────────
if ( ! class_exists( 'VisitStageResolver' ) ) {
    wp_die(
        esc_html__( 'VanaMissionControl plugin não está ativo.', 'vana-mission-control' ),
        '',
        [ 'response' => 500 ]
    );
}

// ── Valida post ───────────────────────────────────────────────────────────────
$visit_id = get_the_ID();

if ( ! $visit_id || get_post_type( $visit_id ) !== 'vana_visit' ) {
    wp_die(
        esc_html__( 'Post inválido para este template.', 'vana-mission-control' ),
        '',
        [ 'response' => 404 ]
    );
}

// ── 1. Resolve ViewModel ──────────────────────────────────────────────────────
$vana_vm = VisitStageResolver::resolve( $visit_id );
extract( $vana_vm->to_template_vars() );

// ── 2. Idioma ─────────────────────────────────────────────────────────────────
$lang = sanitize_key( $_GET['lang'] ?? 'pt' );
$lang = in_array( $lang, [ 'pt', 'en' ], true ) ? $lang : 'pt';

// ── 3. Aliases de dados ───────────────────────────────────────────────────────
$data = $timeline;
$days = is_array( $data['days'] ?? null ) ? $data['days'] : [];

// ── 4. Índice do dia ativo ────────────────────────────────────────────────────
$active_index = 0;
foreach ( $days as $i => $d ) {
    if ( ( $d['date_local'] ?? '' ) === $active_day_date ) {
        $active_index = $i;
        break;
    }
}

// ── 5. Tour ───────────────────────────────────────────────────────────────────
$tour_id = (int) wp_get_post_parent_id( $visit_id );
if ( ! $tour_id ) {
    $tour_id = (int) get_post_meta( $visit_id, '_vana_tour_id', true );
}
$tour_url   = $tour_id ? (string) get_permalink( $tour_id ) : '';
$tour_title = $tour_id ? (string) get_the_title( $tour_id ) : '';

// ── 6. País da visita ─────────────────────────────────────────────────────────
// Fonte 1: post meta _vana_country_code (canônico, editável no admin)
// Fonte 2: timeline JSON country_code   (fallback quando Trator já exportar)
// Formato: ISO 3166-1 alpha-2 em maiúsculas — BR, IN, AR, UY, TH…
$country_code = strtoupper( trim(
    (string) get_post_meta( $visit_id, '_vana_country_code', true )
) );
if ( $country_code === '' ) {
    $country_code = strtoupper( trim(
        (string) ( $data['country_code'] ?? '' )
    ) );
}

// ── 7. Localização & Timezone ─────────────────────────────────────────────────
$location_meta  = is_array( $data['location_meta'] ?? null ) ? $data['location_meta'] : [];
$visit_city_ref = (string) ( $location_meta['city_ref'] ?? '' );
$visit_tz_str   = (string) ( $location_meta['tz'] ?? $visit_timezone ?? 'UTC' );

try {
    $visit_tz = new DateTimeZone( $visit_tz_str ?: 'UTC' );
} catch ( Exception $e ) {
    $visit_tz = new DateTimeZone( 'UTC' );
}

// ── 8. Navegação Prev / Next ──────────────────────────────────────────────────
if ( ! function_exists( 'vana_visit_prev_next_ids' ) ) {
    function vana_visit_prev_next_ids( int $current_id, int $tour_id = 0 ): array {
        // DT-004: Navegação é sempre cronológica global.
        // Parâmetro $tour_id aceito por compatibilidade, mas não usado no resolver.
        // Tour é contexto para o hero, nunca fronteira de navegação.

        if ( function_exists( 'vana_get_chronological_visits' ) ) {
            $sequence = vana_get_chronological_visits();
            if ( ! empty( $sequence ) ) {
                $ids = array_column( $sequence, 'id' );
                $idx = array_search( $current_id, $ids, true );
                if ( $idx !== false ) {
                    return [
                        ( $idx > 0 )                  ? (int) $ids[ $idx - 1 ] : 0,
                        ( $idx < count( $ids ) - 1 )  ? (int) $ids[ $idx + 1 ] : 0,
                    ];
                }
            }
        }

        $start = get_post_meta( $current_id, '_vana_start_date', true );
        if ( ! $start ) {
            return [ 0, 0 ];
        }

        $base = [
            'post_type'      => 'vana_visit',
            'post_status'    => 'publish',
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'no_found_rows'  => true,
            'meta_key'       => '_vana_start_date',
        ];

        $prev_q = new WP_Query( array_merge( $base, [
            'orderby'    => 'meta_value',
            'order'      => 'DESC',
            'meta_query' => [ [
                'key'     => '_vana_start_date',
                'value'   => $start,
                'compare' => '<',
                'type'    => 'DATE',
            ] ],
        ] ) );

        $next_q = new WP_Query( array_merge( $base, [
            'orderby'    => 'meta_value',
            'order'      => 'ASC',
            'meta_query' => [ [
                'key'     => '_vana_start_date',
                'value'   => $start,
                'compare' => '>',
                'type'    => 'DATE',
            ] ],
        ] ) );

        return [
            ! empty( $prev_q->posts ) ? (int) $prev_q->posts[0] : 0,
            ! empty( $next_q->posts ) ? (int) $next_q->posts[0] : 0,
        ];
    }
}

if ( ! function_exists( '_vana_build_nav_visit' ) ) {
    function _vana_build_nav_visit( int $id, string $lang ): ?array {
        if ( $id <= 0 ) {
            return null;
        }

        $json  = get_post_meta( $id, '_vana_visit_timeline_json', true );
        $vdata = $json ? json_decode( $json, true ) : [];
        $vdata = is_array( $vdata ) ? $vdata : [];

        // country_code: meta canônico → fallback JSON
        $cc = strtoupper( trim(
            (string) get_post_meta( $id, '_vana_country_code', true )
        ) );
        if ( $cc === '' ) {
            $cc = strtoupper( trim( (string) ( $vdata['country_code'] ?? '' ) ) );
        }

        return [
            'id'           => $id,
            'permalink'    => (string) get_permalink( $id ),
            'title'        => (string) ( $vdata[ 'title_' . $lang ] ?? $vdata['title_pt'] ?? get_the_title( $id ) ),
            'has_mag'      => get_post_meta( $id, '_vana_mag_state', true ) === 'publicada',
            'country_code' => $cc,   // ← exposto para hero-header.php (badge nav)
        ];
    }
}

[ $prev_id, $next_id ] = vana_visit_prev_next_ids( $visit_id, $tour_id );
$prev_visit = _vana_build_nav_visit( $prev_id, $lang );
$next_visit = _vana_build_nav_visit( $next_id, $lang );

// ── 9. Tour object para templates Hero ───────────────────────────────────
$tour = [
    'id'          => $tour_id,
    'title_pt'    => $tour_title,
    'title_en'    => $tour_title,
    'description_pt' => (string) ( $data['description_pt'] ?? '' ),
    'description_en' => (string) ( $data['description_en'] ?? '' ),
    'thumbnail'   => (string) ( $data['cover_url'] ?? '' ),
    'video_url'   => (string) ( $data['hero']['video_url'] ?? '' ),
    'days'        => $days,
    'region_code' => $country_code,
    'season_code' => (string) ( $data['season_code'] ?? '' ),
    'has_live'    => $visit_status === 'live',
    'is_new'      => false,
    'created_at'  => $tour_id ? get_post_time( 'Y-m-d H:i:s', true, $tour_id ) : '',
    'nav'         => [
        'prev' => [
            'id'       => $prev_visit['id'] ?? 0,
            'title_pt' => $prev_visit['title'] ?? '',
            'title_en' => $prev_visit['title'] ?? '',
            'url'      => $prev_visit['permalink'] ?? '',
        ],
        'next' => [
            'id'       => $next_visit['id'] ?? 0,
            'title_pt' => $next_visit['title'] ?? '',
            'title_en' => $next_visit['title'] ?? '',
            'url'      => $next_visit['permalink'] ?? '',
        ],
    ],
];

// ── 9. Montar $tour para hero-header.php e partials ──────────────────────────
//
// Contrato (CONTRATO.md § 6):
//   hero-header.php v3 consome $tour em vez de $data diretamente.
//   $tour é uma view-model compacta derivada de $data + metas do post.
//
// Estrutura obrigatória:
//   $tour['id']           int     — ID do post da visita
//   $tour['title']        array   — ['pt' => '...', 'en' => '...']
//   $tour['description']  array   — ['pt' => '...', 'en' => '...']
//   $tour['thumbnail']    string  — URL HTTPS da imagem de capa
//   $tour['video_url']    string  — URL pública do YouTube (hero-header converte p/ embed)
//   $tour['days']         array   — $days (passado ao _hero-day-selector.php)
//   $tour['nav']          array   — prev/next para _hero-nav.php
//   $tour['region_code']  string  — ISO 3166-1 alpha-2 (BR, IN…) → badge região
//   $tour['season_code']  string  — ex: INDIA_2026 → badge período
//   $tour['has_live']     bool    — badge ao vivo
//   $tour['is_new']       bool    — badge novo
//   $tour['created_at']   string  — ISO 8601 para regra dos 30 dias

// ── 9a. Título e descrição bilíngues ─────────────────────────────────────────
// Fonte 1: chaves separadas  title_pt / title_en  (padrão legado do JSON)
// Fonte 2: chave composta    title => ['pt'=>..., 'en'=>...]  (padrão v3)
// Normaliza sempre para array i18n que pick_i18n_key() entende.

$_tour_title_pt = (string) ( $data['title_pt'] ?? $data['title'] ?? get_the_title( $visit_id ) );
$_tour_title_en = (string) ( $data['title_en'] ?? $_tour_title_pt );

$_tour_desc_pt  = (string) ( $data['description_pt'] ?? $data['description'] ?? '' );
$_tour_desc_en  = (string) ( $data['description_en'] ?? $_tour_desc_pt );

// ── 9b. Mídia ─────────────────────────────────────────────────────────────────
// cover_url → thumbnail (imagem estática)
// youtube_url / video_url → video_url (hero converte para embed)

$_cover_url = (string) ( $data['cover_url'] ?? '' );

// Fallback de thumbnail: thumb do YouTube do primeiro dia com vídeo
if ( $_cover_url === '' ) {
    foreach ( $days as $_d ) {
        $_yt = (string) ( $_d['hero']['youtube_url'] ?? $_d['youtube_url'] ?? '' );
        if ( $_yt !== '' && preg_match(
            '/(?:v=|\/embed\/|\.be\/|\/shorts\/)([a-zA-Z0-9_-]{11})/',
            $_yt,
            $_m
        ) ) {
            $_cover_url = 'https://i.ytimg.com/vi/' . $_m[1] . '/maxresdefault.jpg';
            break;
        }
    }
}

// Video URL: primeiro dia que tiver youtube_url
$_video_url = (string) ( $data['video_url'] ?? '' );
if ( $_video_url === '' ) {
    foreach ( $days as $_d ) {
        $_yt = (string) ( $_d['hero']['youtube_url'] ?? $_d['youtube_url'] ?? '' );
        if ( $_yt !== '' ) {
            $_video_url = $_yt;
            break;
        }
    }
}

// ── 9c. Navegação (contrato _hero-nav.php) ───────────────────────────────────
// pick_i18n_key($prev, 'title', $lang) espera $prev['title'] = ['pt'=>..., 'en'=>...]
// NÃO title_pt / title_en separados.

$_nav_prev = [];
if ( $prev_visit ) {
    $_nav_prev = [
        'id'    => $prev_visit['id'],
        'url'   => $prev_visit['permalink'],
        'title' => [
            'pt' => $prev_visit['title'],
            'en' => $prev_visit['title'],   // bilíngue se disponível no futuro
        ],
        'country_code' => $prev_visit['country_code'] ?? '',
    ];
}

$_nav_next = [];
if ( $next_visit ) {
    $_nav_next = [
        'id'    => $next_visit['id'],
        'url'   => $next_visit['permalink'],
        'title' => [
            'pt' => $next_visit['title'],
            'en' => $next_visit['title'],
        ],
        'country_code' => $next_visit['country_code'] ?? '',
    ];
}

// ── 9d. Badges ───────────────────────────────────────────────────────────────
$_season_code = strtoupper( trim( (string) ( $data['season_code'] ?? '' ) ) );
$_has_live    = ! empty( $data['has_live'] ) && $data['has_live'] === true;
$_is_new      = ! empty( $data['is_new']   ) && $data['is_new']  === true;

// created_at: meta canônico → fallback data de publicação do post
$_created_at = (string) get_post_meta( $visit_id, '_vana_published_at', true );
if ( $_created_at === '' ) {
    $_created_at = (string) get_the_date( 'c', $visit_id ); // ISO 8601
}

// ── 9e. Montagem final ────────────────────────────────────────────────────────
$tour = [
    // Identidade
    'id'           => $visit_id,

    // Título e descrição (array i18n — padrão v3)
    'title'        => [ 'pt' => $_tour_title_pt, 'en' => $_tour_title_en ],
    'description'  => [ 'pt' => $_tour_desc_pt,  'en' => $_tour_desc_en  ],

    // Mídia
    'thumbnail'    => $_cover_url,
    'video_url'    => $_video_url,

    // Dias (repassado ao _hero-day-selector.php)
    'days'         => $days,

    // Navegação prev/next
    'nav'          => [
        'prev' => $_nav_prev,
        'next' => $_nav_next,
    ],

    // Badges
    'region_code'  => $country_code,   // já resolvido na seção 6
    'season_code'  => $_season_code,
    'has_live'     => $_has_live,
    'is_new'       => $_is_new,
    'created_at'   => $_created_at,
];

// ── 9f. Limpeza de vars temporárias ──────────────────────────────────────────
// Evita poluir o escopo do template com vars prefixadas com _
unset(
    $_tour_title_pt, $_tour_title_en,
    $_tour_desc_pt,  $_tour_desc_en,
    $_cover_url,     $_video_url,
    $_nav_prev,      $_nav_next,
    $_season_code,   $_has_live, $_is_new, $_created_at,
    $_d, $_yt, $_m
);
