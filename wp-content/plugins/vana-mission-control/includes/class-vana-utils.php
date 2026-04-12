<?php
// includes/class-vana-utils.php
if (!defined('ABSPATH')) exit;

final class Vana_Utils {

    // =========================================================================
    // 1. LOGGING
    // =========================================================================

    /**
     * Registra logs de sistema no debug.log do WordPress de forma segura.
     */
    public static function log(string $message, string $level = 'info', array $context = []): void {
        $debug     = defined('WP_DEBUG') && WP_DEBUG;
        $debug_log = defined('WP_DEBUG_LOG') && WP_DEBUG_LOG;

        if (!$debug && !$debug_log) {
            return;
        }

        $prefix = strtoupper($level);
        $ctx    = !empty($context)
            ? ' ' . wp_json_encode($context, JSON_UNESCAPED_UNICODE)
            : '';

        error_log("[Vana/{$prefix}] {$message}{$ctx}");
    }

    // =========================================================================
    // 2. API
    // =========================================================================

    /**
     * Formata a resposta da API (JSON) para o Frontend.
     * Padrão oficial Vana: success, message, status_code, data (opcional).
     */
    public static function api_response(bool $success, string $message, int $status_code = 200, $data = null): WP_REST_Response {
        if ($status_code < 100 || $status_code > 599) {
            $status_code = 200;
        }

        return new \WP_REST_Response([
            'success' => $success,
            'message' => $message,
            'data'    => $data,
        ], $status_code);
    }

    // =========================================================================
    // 3. SANITIZAÇÃO
    // =========================================================================

    /**
     * Limpa e valida o Origin Key (ex: visit:india_2026:vrindavan)
     */
    public static function sanitize_origin_key($key): string {
        return sanitize_text_field((string) $key);
    }

    public static function esc_text(string $s): string {
        return esc_html($s);
    }

    // =========================================================================
    // 4. INTERNACIONALIZAÇÃO
    // =========================================================================

    /**
     * Detecta o idioma da request via ?lang=en.
     * Retorna 'en' ou 'pt' (default).
     */
    public static function lang_from_request(): string {
        $lang = isset($_GET['lang'])
            ? strtolower(sanitize_key((string) $_GET['lang']))
            : 'pt';
        return ($lang === 'en') ? 'en' : 'pt';
    }

    /**
     * Extrai texto de um objeto multilingue vindo do Trator JSON.
     * Formato: ['pt' => '...', 'en' => '...']
     */
    public static function pick_i18n($data, string $lang = 'pt'): string {
        if (is_string($data)) return $data;
        if (!is_array($data)) return '';
        return (string) ($data[$lang] ?? $data['pt'] ?? reset($data) ?? '');
    }

    /**
     * Extrai i18n por sufixo de chave.
     * Ex: pick_i18n_key($obj, 'title', 'en') → lê $obj['title_en']
     * Fallback: _pt → _en → ''
     */
    public static function pick_i18n_key(array $obj, string $base, string $lang = 'pt'): string {
        $k = $base . '_' . $lang;
        if (isset($obj[$k]) && is_string($obj[$k]) && $obj[$k] !== '') return $obj[$k];

        $pt = $base . '_pt';
        if (isset($obj[$pt]) && is_string($obj[$pt]) && $obj[$pt] !== '') return $obj[$pt];

        $en = $base . '_en';
        if (isset($obj[$en]) && is_string($obj[$en]) && $obj[$en] !== '') return $obj[$en];

        return '';
    }

    /**
     * Normaliza o nome de exibição do devoto.
     * Fallback para 'devotee_fallback' se vazio.
     */
    public static function normalize_display_name(?string $name, string $lang = 'pt'): string {
        $name = trim((string) $name);
        if ($name === '') return self::t('devotee_fallback', $lang);
        return $name;
    }

    /**
     * Dicionário central de strings de UI.
     * Fonte: EDITORIAL.md — sincronize sempre os dois arquivos juntos.
     *
     * Fallback: EN ausente → usa PT. Chave ausente → retorna a própria $key.
     */
    public static function t(string $key, string $lang = 'pt'): string {
        static $strings = null;

        if ($strings === null) {
            // ✅ plugin_dir_path, não get_template_directory
            $path = VANA_MC_PATH . 'i18n/strings.php';

            if (!file_exists($path)) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    trigger_error(
                        '[Vana_Utils::t] i18n não encontrado: ' . $path,
                        E_USER_WARNING
                    );
                }
                return $key;
            }

            $strings = require $path;

            if (!is_array($strings)) {
                $strings = [];
            }
        }

        if (!isset($strings[$key])) {
            return $key;
        }

        $entry = $strings[$key];

        return (string) (
            $entry[$lang] ??
            $entry['en']  ??
            $entry['pt']  ??
            $key
        );
    }

    // =========================================================================
    // 5. VALIDAÇÃO DE URLs
    // =========================================================================

    /**
     * Valida URL externa de vídeo.
     * Allowlist: YouTube, Google Drive, Facebook.
     * Bloqueia links de pasta do Drive.
     * Retorna string sanitizada ou WP_Error.
     */
    public static function validate_external_video_url(string $url, string $lang = 'pt') {
        $url = trim((string) $url);
        if ($url === '') return '';

        $parts  = wp_parse_url($url);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));

        if ($scheme !== 'https') {
            return new WP_Error('vana_url_https', ($lang === 'en')
                ? 'Link must start with https://'
                : 'O link precisa começar com https://'
            );
        }

        $host = strtolower((string) ($parts['host'] ?? ''));
        $path = (string) ($parts['path'] ?? '');

        $allowed = [
            'youtube.com', 'www.youtube.com',
            'youtu.be', 'www.youtu.be',
            'drive.google.com', 'www.drive.google.com',
            'facebook.com', 'www.facebook.com',
            'fb.watch',
        ];

        if (!in_array($host, $allowed, true)) {
            return new WP_Error('vana_url_host', ($lang === 'en')
                ? 'Use a Google Drive (recommended) or YouTube (optional) link.'
                : 'Use um link do Google Drive (recomendado) ou YouTube (opcional).'
            );
        }

        if (str_contains($host, 'drive.google.com')) {
            if (str_contains($path, '/folders/') || str_contains($path, '/drive/folders/')) {
                return new WP_Error('vana_drive_folder', ($lang === 'en')
                    ? 'This looks like a Drive folder link. Please open the video file and copy the file link (/file/d/...).'
                    : 'Esse link parece ser de pasta do Drive. Abra o arquivo do vídeo e copie o link do arquivo (/file/d/...).'
                );
            }
        }

        return esc_url_raw($url);
    }

    /**
     * Garante que a URL é https e retorna sanitizada.
     * Retorna '' se inválida.
     */
    public static function safe_https_url(string $url): string {
        $url = trim($url);
        if ($url === '') return '';
        $parts = wp_parse_url($url);
        if (empty($parts['scheme']) || strtolower($parts['scheme']) !== 'https') return '';
        return esc_url_raw($url);
    }

    /**
     * Verifica se a URL é de um serviço de vídeo suportado.
     */
    public static function is_video_url(string $url): bool {
        $u = self::safe_https_url($url);
        if ($u === '') return false;
        $parts = wp_parse_url($u);
        $host  = strtolower((string) ($parts['host'] ?? ''));
        return (
            str_contains($host, 'youtube.com')      ||
            str_contains($host, 'youtu.be')         ||
            str_contains($host, 'drive.google.com')
        );
    }
    // Adicionar em includes/class-vana-utils.php
    // =========================================================================
    // 6. URL DE EMBED
    // =========================================================================

    /**
     * Converte URL YouTube em URL de embed.
     * Suporta: watch?v=, youtu.be/, shorts/, embed/
     * Retorna '' se não for YouTube válido.
     */
    public static function maybe_embed_url(string $url): string {
        $url = trim($url);
        if ($url === '') return '';

        $video_id = '';

        if (preg_match('#youtube\.com/embed/([a-zA-Z0-9_-]{11})#i', $url, $m)) {
            $video_id = $m[1];
        } elseif (preg_match('#[?&]v=([a-zA-Z0-9_-]{11})#i', $url, $m)) {
            $video_id = $m[1];
        } elseif (preg_match('#youtu\.be/([a-zA-Z0-9_-]{11})#i', $url, $m)) {
            $video_id = $m[1];
        } elseif (preg_match('#youtube\.com/shorts/([a-zA-Z0-9_-]{11})#i', $url, $m)) {
            $video_id = $m[1];
        }

        if ($video_id === '') return '';

        return 'https://www.youtube-nocookie.com/embed/'
            . $video_id
            . '?rel=0&modestbranding=1';
    }

    /**
     * Resolve o título canônico de uma visita.
     * Ordem de prioridade:
     *   1. $tour['title_pt|en']  — meta salvo no post/JSON
     *   2. get_the_title($visit_id) — título do post WP
     *   3. $city_ref              — fallback: nome da cidade
     *
     * @param array  $tour     View-model da visita (de _bootstrap.php §9e)
     * @param string $lang     'pt' | 'en'
     * @param int    $visit_id ID do post WP (para get_the_title)
     * @param string $city_ref Fallback final
     * @return string
     */
    public static function resolve_visit_title(
        array $tour,
        string $lang = 'pt',
        int $visit_id = 0,
        string $city_ref = ''
    ): string {
        // 1. Meta i18n do tour
        // Suporta duas formas:
        //  - novo padrão: $tour['title'] = ['pt'=>..., 'en'=>...]
        //  - legado: $tour['title_pt']/['title_en']
        if (isset($tour['title'])) {
            $title = self::pick_i18n($tour['title'], $lang);
            if ($title !== '') return $title;
        }

        // Legacy suffix keys (title_pt / title_en)
        $title = self::pick_i18n_key( $tour, 'title', $lang );
        if ( $title !== '' ) return $title;

        // 2. Título do post WP
        if ( $visit_id > 0 ) {
            $wp_title = get_the_title( $visit_id );
            if ( $wp_title !== '' ) return $wp_title;
        }

        // 3. city_ref como último recurso
        // Se o caller não passou $city_ref, tente extrair do próprio $tour
        if (trim((string) $city_ref) === '') {
            $possible = '';

            if (isset($tour['city']) && is_string($tour['city'])) {
                $possible = $tour['city'];
            }

            if ($possible === '' && isset($tour['city_ref']) && is_string($tour['city_ref'])) {
                $possible = $tour['city_ref'];
            }

            if ($possible === '' && isset($tour['location_meta']) && is_array($tour['location_meta'])) {
                $possible = (string) ($tour['location_meta']['city_ref'] ?? '');
            }

            if ($possible === '' && isset($tour['location']) && is_array($tour['location'])) {
                $possible = (string) ($tour['location']['city_ref'] ?? '');
            }

            $city_ref = trim((string) $possible);
        }

        return $city_ref;
    }

    // =========================================================================
    // 7. TOUR / VISIT IDENTITY HELPERS (Fase 1)
    // =========================================================================

    /**
     * Retorna identidade canônica da tour (metadados atômicos).
     */
    public static function get_tour_identity(int $tour_id, string $lang = 'pt'): array {
        $tour_id = (int) $tour_id;
        if ($tour_id <= 0) return [
            'id' => 0,
            'title' => '',
            'region_code' => '',
            'season_code' => '',
            'year_start' => 0,
            'year_end' => 0,
            'year_label' => '',
            'header_label' => '',
            'full_label' => '',
        ];

        $region = (string) get_post_meta($tour_id, '_vana_region_code', true);
        $season = (string) get_post_meta($tour_id, '_vana_season_code', true);
        $y_start = (int) get_post_meta($tour_id, '_vana_year_start', true) ?: 0;
        $y_end   = (int) get_post_meta($tour_id, '_vana_year_end', true)   ?: 0;

        $title = self::resolve_tour_title($tour_id, $lang);
        $year_label = self::tour_year_label($y_start, $y_end);
        $header_label = self::tour_header_label($tour_id, $lang);
        $full_label = self::tour_full_label($tour_id, $lang);

        return [
            'id' => $tour_id,
            'title' => (string) $title,
            'region_code' => (string) $region,
            'season_code' => (string) $season,
            'year_start' => $y_start,
            'year_end' => $y_end,
            'year_label' => (string) $year_label,
            'header_label' => (string) $header_label,
            'full_label' => (string) $full_label,
        ];
    }

    /**
     * Resolve título da tour com fallback definido.
     */
    public static function resolve_tour_title(int $tour_id, string $lang = 'pt'): string {
        $tour_id = (int) $tour_id;
        if ($tour_id <= 0) return '';

        $lang = $lang === 'en' ? 'en' : 'pt';

        $k = "_vana_title_{$lang}";
        $v = (string) get_post_meta($tour_id, $k, true);
        if ($v !== '') return $v;

        $pt = (string) get_post_meta($tour_id, '_vana_title_pt', true);
        if ($pt !== '') return $pt;

        $wp = (string) get_the_title($tour_id);
        if ($wp !== '') return $wp;

        $origin = (string) get_post_meta($tour_id, '_vana_origin_key', true);
        if ($origin !== '') return $origin;

        return '';
    }

    /**
     * Formata label de anos da tour.
     */
    public static function tour_year_label(int $year_start = 0, int $year_end = 0): string {
        $ys = (int) $year_start;
        $ye = (int) $year_end;

        if ($ys <= 0 && $ye <= 0) return '';
        if ($ys > 0 && ($ye === 0 || $ys === $ye)) return (string) $ys;
        if ($ys > 0 && $ye > 0 && $ys !== $ye) {
            $s = (string) $ys;
            $e = (string) $ye;
            return substr($s, -2) . '/' . substr($e, -2);
        }
        if ($ys > 0) return (string) $ys;
        return '';
    }

    /**
     * Header label (REGION · SEASON · YEAR) ou fallback para título.
     */
    public static function tour_header_label(int $tour_id, string $lang = 'pt'): string {
        $tour_id = (int) $tour_id;
        if ($tour_id <= 0) return '';

        $region = strtoupper(trim((string) get_post_meta($tour_id, '_vana_region_code', true)));
        $season = strtoupper(trim((string) get_post_meta($tour_id, '_vana_season_code', true)));
        $y_start = (int) get_post_meta($tour_id, '_vana_year_start', true) ?: 0;
        $y_end   = (int) get_post_meta($tour_id, '_vana_year_end', true)   ?: 0;

        $year_label = self::tour_year_label($y_start, $y_end);

        if ($region !== '' && $season !== '' && $year_label !== '') {
            return $region . ' · ' . $season . ' · ' . $year_label;
        }

        return self::resolve_tour_title($tour_id, $lang);
    }

    /**
     * Tour full label humanizado (mapas internos, extensíveis por filter).
     */
    public static function tour_full_label(int $tour_id, string $lang = 'pt'): string {
        $tour_id = (int) $tour_id;
        if ($tour_id <= 0) return '';

        $region = strtoupper(trim((string) get_post_meta($tour_id, '_vana_region_code', true)));
        $season = strtoupper(trim((string) get_post_meta($tour_id, '_vana_season_code', true)));
        $y_start = (int) get_post_meta($tour_id, '_vana_year_start', true) ?: 0;
        $y_end   = (int) get_post_meta($tour_id, '_vana_year_end', true)   ?: 0;

        // Default maps (can be filtered)
        $region_map = [
            // ISO 2 letters (legacy / country codes)
            'IN'  => $lang === 'en' ? 'India'       : 'Índia',
            'BR'  => $lang === 'en' ? 'Brazil'      : 'Brasil',
            'US'  => $lang === 'en' ? 'USA'         : 'EUA',
            'PT'  => $lang === 'en' ? 'Portugal'    : 'Portugal',
            'NL'  => $lang === 'en' ? 'Netherlands' : 'Holanda',
            'AR'  => $lang === 'en' ? 'Argentina'   : 'Argentina',
            'UY'  => $lang === 'en' ? 'Uruguay'     : 'Uruguai',
            'GB'  => $lang === 'en' ? 'England'     : 'Inglaterra',

            // 3-letter region codes (spec EDITORIAL.md)
            'IND' => $lang === 'en' ? 'India'       : 'Índia',
            'EUR' => $lang === 'en' ? 'Europe'      : 'Europa',
            'AME' => $lang === 'en' ? 'Americas'    : 'Américas',
            'BRA' => $lang === 'en' ? 'Brazil'      : 'Brasil',
        ];
        $season_map = [
            'KARTIK'    => $lang === 'en' ? 'Kartik' : 'Kartik',
            'VRAJA'     => $lang === 'en' ? 'Vraja' : 'Vraja',
            'GAURA'     => $lang === 'en' ? 'Gaura' : 'Gaura',
            'NAVADVIPA' => $lang === 'en' ? 'Navadvīpa' : 'Navadvīpa',
            'MAYAPUR'   => $lang === 'en' ? 'Māyāpur' : 'Māyāpur',
            'PURI'      => $lang === 'en' ? 'Purī' : 'Purī',
        ];

        $region_map = apply_filters('vana_tour_region_map', $region_map, $lang);
        $season_map = apply_filters('vana_tour_season_map', $season_map, $lang);

        $region_label = $region !== '' ? ($region_map[$region] ?? $region) : '';
        $season_label = $season !== '' ? ($season_map[$season] ?? $season) : '';
        $year_label = self::tour_year_label($y_start, $y_end);

        $parts = [];
        if ($region_label !== '') $parts[] = $region_label;
        if ($season_label !== '') $parts[] = $season_label;
        if ($year_label !== '') $parts[] = $year_label;

        if (!empty($parts)) return implode(' · ', $parts);

        return self::resolve_tour_title($tour_id, $lang);
    }

    /**
     * Retorna identidade da visita (atômica).
     */
    public static function get_visit_identity(int $visit_id, string $lang = 'pt'): array {
        $visit_id = (int) $visit_id;
        if ($visit_id <= 0) return [
            'id' => 0,
            'city' => '',
            'country_code' => '',
            'country_label' => '',
            'title' => '',
        ];

        $city = self::resolve_visit_city($visit_id, $lang);
        $cc = self::resolve_visit_country_code($visit_id);
        $cl = self::resolve_visit_country_label($cc, $lang);
        $title = (string) get_the_title($visit_id);

        return [
            'id' => $visit_id,
            'city' => $city,
            'country_code' => $cc,
            'country_label' => $cl,
            'title' => $title,
        ];
    }

    /**
     * Resolve cidade da visita com fallbacks.
     */
    public static function resolve_visit_city(int $visit_id, string $lang = 'pt'): string {
        $visit_id = (int) $visit_id;
        if ($visit_id <= 0) return '';

        // 1. _vana_location meta (may be array or string)
        $loc = get_post_meta($visit_id, '_vana_location', true);
        if (is_array($loc)) {
            $c = (string) ($loc['city'] ?? $loc['city_ref'] ?? '');
            if ($c !== '') return $c;
        } elseif (is_string($loc) && trim($loc) !== '') {
            return trim($loc);
        }

        // 2/3. timeline JSON
        $json = get_post_meta($visit_id, '_vana_visit_timeline_json', true);
        $vdata = $json ? json_decode($json, true) : [];

        // If timeline is an array of items ([ {...}, {...} ]) take the first item
        if (is_array($vdata) && isset($vdata[0]) && is_array($vdata[0])) {
            $vdata = $vdata[0];
        }

        if (is_array($vdata) && !empty($vdata)) {
            // schema 3.x: location_meta.city
            if (!empty($vdata['location_meta']['city'])) {
                return (string) $vdata['location_meta']['city'];
            }
            // legado: title_pt / title_en / title
            $k = 'title_' . ($lang === 'en' ? 'en' : 'pt');
            if (!empty($vdata[$k]) && is_string($vdata[$k])) return (string) $vdata[$k];
            if (!empty($vdata['title_pt']) && is_string($vdata['title_pt'])) return (string) $vdata['title_pt'];
            if (!empty($vdata['title']) && is_string($vdata['title'])) return (string) $vdata['title'];
        }

        // 3.5. _vana_title_pt / _vana_title_en
        // ⚠️ ATENÇÃO: _vana_title_pt/en é o título EDITORIAL da visita,
        // não necessariamente a cidade isolada.
        // Este fallback existe para visitas legadas sem _vana_location preenchido.
        // Quando o Trator gravar _vana_location corretamente, este passo
        // não será mais atingido.
        // TODO: auditar visitas sem _vana_location e migrar dados.
        $title_key = '_vana_title_' . ($lang === 'en' ? 'en' : 'pt');
        $t = (string) get_post_meta($visit_id, $title_key, true);
        if ($t !== '') return $t;

        // 4. WP post title
        return (string) get_the_title($visit_id);
    }

    /**
     * Resolve country code (canonical) for a visit.
     */
    public static function resolve_visit_country_code(int $visit_id): string {
        $visit_id = (int) $visit_id;
        if ($visit_id <= 0) return '';
        $v = (string) get_post_meta($visit_id, '_vana_country_code', true);
        $v = strtoupper(trim($v));
        return $v === '' ? '' : $v;
    }

    /**
     * Resolve label for a country code using internal map and filter.
     */
    public static function resolve_visit_country_label(string $country_code, string $lang = 'pt'): string {
        $country_code = strtoupper(trim((string) $country_code));
        if ($country_code === '') return '';

        $maps = [
            'pt' => [
                'BR' => 'Brasil', 'IN' => 'Índia', 'US' => 'EUA', 'AR' => 'Argentina', 'UY' => 'Uruguai',
                'PT' => 'Portugal', 'ES' => 'Espanha', 'IT' => 'Itália', 'FR' => 'França', 'DE' => 'Alemanha', 'GB' => 'Inglaterra',
            ],
            'en' => [
                'BR' => 'Brazil', 'IN' => 'India', 'US' => 'USA', 'AR' => 'Argentina', 'UY' => 'Uruguay',
                'PT' => 'Portugal', 'ES' => 'Spain', 'IT' => 'Italy', 'FR' => 'France', 'DE' => 'Germany', 'GB' => 'England',
            ],
        ];

        $maps = apply_filters('vana_country_labels', $maps);

        $lang = $lang === 'en' ? 'en' : 'pt';
        return $maps[$lang][$country_code] ?? $country_code;
    }

    /**
     * Label usado em nav/prev-next: cidade [+ COUNTRY CODE]
     */
    public static function visit_nav_label(int $visit_id, string $lang = 'pt', bool $with_country = false): string {
        $city = self::resolve_visit_city($visit_id, $lang);
        if ($city === '') return '';
        if ($with_country) {
            $cc = self::resolve_visit_country_code($visit_id);
            return $cc !== '' ? $city . ' [' . $cc . ']' : $city;
        }
        return $city;
    }

    /**
     * Formata data da visita com formato fornecido.
     */
    public static function visit_date_label(int $visit_id, string $format = 'd/m'): string {
        $visit_id = (int) $visit_id;
        if ($visit_id <= 0) return '';
        $raw = (string) get_post_meta($visit_id, '_vana_start_date', true);
        if ($raw === '') return '';
        $ts = strtotime($raw);
        if ($ts === false) return '';
        return date($format, $ts);
    }

    /**
     * Contador da visita dentro da tour (Visita X de Y).
     */
    public static function visit_counter_label(int $visit_id, int $tour_id, string $lang = 'pt'): string {
        $visit_id = (int) $visit_id;
        $tour_id  = (int) $tour_id;
        if ($visit_id <= 0 || $tour_id <= 0) return '';

        // 1) por _vana_start_date
        $args = [
            'post_type' => 'vana_visit',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'fields' => 'ids',
            'meta_key' => '_vana_start_date',
            'orderby' => 'meta_value',
            'order' => 'ASC',
            'meta_query' => [[
                'key' => '_vana_tour_id', 'value' => $tour_id, 'compare' => '=', 'type' => 'NUMERIC'
            ]],
            'no_found_rows' => true,
        ];

        $ids = get_posts($args);

        // 2) fallback: menu_order
        if (empty($ids)) {
            $args2 = $args;
            unset($args2['meta_key']);
            $args2['orderby'] = 'menu_order';
            $args2['order'] = 'ASC';
            $ids = get_posts($args2);
        }

        // 3) fallback: date
        if (empty($ids)) {
            $args3 = $args;
            unset($args3['meta_key']);
            $args3['orderby'] = 'date';
            $args3['order'] = 'ASC';
            $ids = get_posts($args3);
        }

        $ids = is_array($ids) ? $ids : [];
        $total = count($ids);
        if ($total === 0) return '';

        $pos = array_search($visit_id, $ids, true);
        if ($pos === false) return '';

        $label = $lang === 'en'
            ? sprintf('Visit %d of %d', $pos + 1, $total)
            : sprintf('Visita %d de %d', $pos + 1, $total);

        return $label;
    }

}

// =============================================================================
// ALIAS GLOBAL — vana_t()
// Resolve chamadas em templates beta que usam vana_t() em vez de Vana_Utils::t()
// =============================================================================
if (!function_exists('vana_t')) {
    function vana_t(string $key, string $lang = 'pt'): string {
        return Vana_Utils::t($key, $lang);
    }
}
