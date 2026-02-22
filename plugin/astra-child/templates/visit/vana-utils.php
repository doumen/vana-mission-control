<?php
/**
 * Vana Visit — Utilitários
 * Arquivo: templates/visit/vana-utils.php
 *
 * Incluído por: templates/visit/_bootstrap.php
 *
 * Funções disponíveis:
 *   vana_fmt_date()          — formata data localizada (pt/en)
 *   vana_youtube_thumb()     — URL do thumbnail do YouTube
 *   vana_youtube_embed()     — URL de embed do YouTube
 *   vana_facebook_embed()    — URL de embed do Facebook
 *   vana_render_segments()   — renderiza botões de capítulo/segmento
 *   vana_esc_attr_json()     — escapa array para data-attribute JSON
 *   vana_is_valid_date()     — valida string YYYY-MM-DD
 *   vana_local_time_span()   — gera <span> com timestamp para dual-timezone
 */
defined('ABSPATH') || exit;

// ── Guard: evita redeclaração se incluído mais de uma vez ─────
if (function_exists('vana_fmt_date')) {
    return;
}

// ============================================================
//  1. FORMATAÇÃO DE DATA
// ============================================================

/**
 * Formata uma data YYYY-MM-DD no idioma da visita.
 *
 * @param string $date_str  Data no formato YYYY-MM-DD
 * @param string $lang      'pt' | 'en'
 * @param string $format    'full' | 'short' | 'weekday'
 * @return string           Ex: "Sábado, 21 de fevereiro de 2026"
 */
function vana_fmt_date(string $date_str, string $lang = 'pt', string $format = 'full'): string {
    if (!vana_is_valid_date($date_str)) {
        return esc_html($date_str);
    }

    try {
        $dt = new DateTimeImmutable($date_str);
    } catch (Exception $e) {
        return esc_html($date_str);
    }

    $locale = $lang === 'en' ? 'en-US' : 'pt-BR';

    // Usa IntlDateFormatter se disponível (extensão intl do PHP)
    if (class_exists('IntlDateFormatter')) {
        $patterns = [
            'full'    => [IntlDateFormatter::FULL,  IntlDateFormatter::NONE],
            'short'   => [IntlDateFormatter::SHORT, IntlDateFormatter::NONE],
            'weekday' => [IntlDateFormatter::FULL,  IntlDateFormatter::NONE],
        ];

        [$date_type, $time_type] = $patterns[$format] ?? $patterns['full'];

        $fmt = new IntlDateFormatter(
            $locale,
            $date_type,
            $time_type,
            'UTC',
            IntlDateFormatter::GREGORIAN
        );

        if ($format === 'weekday') {
            $fmt->setPattern('EEEE');
        }

        $result = $fmt->format($dt->getTimestamp());
        return $result !== false ? $result : esc_html($date_str);
    }

    // Fallback sem extensão intl
    $months_pt = [
        1 => 'janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho',
        'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro',
    ];
    $months_en = [
        1 => 'January', 'February', 'March', 'April', 'May', 'June',
        'July', 'August', 'September', 'October', 'November', 'December',
    ];
    $days_pt = ['Domingo','Segunda-feira','Terça-feira','Quarta-feira','Quinta-feira','Sexta-feira','Sábado'];
    $days_en = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];

    $day_num   = (int) $dt->format('j');
    $month_num = (int) $dt->format('n');
    $year      = $dt->format('Y');
    $dow       = (int) $dt->format('w');

    if ($lang === 'en') {
        return match($format) {
            'short'   => $months_en[$month_num] . ' ' . $day_num . ', ' . $year,
            'weekday' => $days_en[$dow],
            default   => $days_en[$dow] . ', ' . $months_en[$month_num] . ' ' . $day_num . ', ' . $year,
        };
    }

    return match($format) {
        'short'   => $day_num . ' de ' . $months_pt[$month_num] . ' de ' . $year,
        'weekday' => $days_pt[$dow],
        default   => $days_pt[$dow] . ', ' . $day_num . ' de ' . $months_pt[$month_num] . ' de ' . $year,
    };
}

// ============================================================
//  2. YOUTUBE HELPERS
// ============================================================

/**
 * Retorna a URL do thumbnail do YouTube.
 *
 * @param string $video_id  ID do vídeo YouTube
 * @param string $quality   'maxresdefault' | 'hqdefault' | 'mqdefault' | 'sddefault'
 * @return string           URL absoluta do thumbnail
 */
function vana_youtube_thumb(string $video_id, string $quality = 'hqdefault'): string {
    if ($video_id === '') return '';

    $allowed = ['maxresdefault', 'hqdefault', 'mqdefault', 'sddefault', 'default'];
    if (!in_array($quality, $allowed, true)) {
        $quality = 'hqdefault';
    }

    return esc_url(
        'https://i.ytimg.com/vi/' . rawurlencode($video_id) . '/' . $quality . '.jpg'
    );
}

/**
 * Retorna a URL de embed do YouTube (nocookie).
 *
 * @param string $video_id  ID do vídeo YouTube
 * @param array  $params    Parâmetros extras (rel, modestbranding, etc.)
 * @return string           URL de embed
 */
function vana_youtube_embed(string $video_id, array $params = []): string {
    if ($video_id === '') return '';

    $defaults = [
        'rel'             => '0',
        'modestbranding'  => '1',
        'enablejsapi'     => '1',
        'origin'          => home_url(),
    ];

    $args = array_merge($defaults, $params);

    return esc_url(
        'https://www.youtube-nocookie.com/embed/' . rawurlencode($video_id)
        . '?' . http_build_query($args)
    );
}

// ============================================================
//  3. FACEBOOK EMBED
// ============================================================

/**
 * Retorna a URL de embed do Facebook para vídeo/live.
 *
 * @param string $fb_url    URL pública do vídeo/live no Facebook
 * @return string           URL de embed do Facebook
 */
function vana_facebook_embed(string $fb_url): string {
    if ($fb_url === '') return '';

    return esc_url(
        'https://www.facebook.com/plugins/video.php'
        . '?href=' . rawurlencode($fb_url)
        . '&show_text=false&appId'
        . '&autoplay=false'
        . '&mute=false'
    );
}

// ============================================================
//  4. SEGMENTOS / CAPÍTULOS
// ============================================================

/**
 * Renderiza os botões de segmento/capítulo do palco.
 *
 * Espera array no formato:
 * [
 *   ['t' => '0:00',  'label' => 'Mangala-arati'],
 *   ['t' => '12:30', 'label' => 'Tulasi-puja'],
 * ]
 *
 * @param array  $segments  Array de segmentos
 * @param string $lang      'pt' | 'en'
 * @return void             Imprime HTML diretamente
 */
function vana_render_segments(array $segments, string $lang = 'pt'): void {
    if (empty($segments)) return;

    $label_prefix = $lang === 'en' ? 'Jump to' : 'Ir para';
    ?>
    <div class="vana-stage-segments" role="list" aria-label="<?php echo esc_attr($lang === 'en' ? 'Chapters' : 'Capítulos'); ?>">
        <?php foreach ($segments as $seg) :
            $t     = sanitize_text_field((string) ($seg['t']     ?? ''));
            $label = sanitize_text_field((string) ($seg['label'] ?? ''));
            if ($t === '' || $label === '') continue;
            ?>
            <button
                type="button"
                class="vana-seg-btn"
                role="listitem"
                data-vana-stage-seg="1"
                data-t="<?php echo esc_attr($t); ?>"
                aria-label="<?php echo esc_attr($label_prefix . ': ' . $label . ' (' . $t . ')'); ?>"
            >
                <strong><?php echo esc_html($t); ?></strong>
                <?php echo esc_html($label); ?>
            </button>
        <?php endforeach; ?>
    </div>
    <?php
}

// ============================================================
//  5. ESCAPE HELPERS
// ============================================================

/**
 * Escapa um array/objeto PHP para uso em data-attribute HTML.
 * Uso: data-config="<?php echo vana_esc_attr_json($array); ?>"
 *
 * @param mixed $data   Qualquer valor serializável em JSON
 * @return string       JSON escapado para atributo HTML
 */
function vana_esc_attr_json(mixed $data): string {
    $json = wp_json_encode($data, JSON_UNESCAPED_UNICODE | JSON_HEX_QUOT | JSON_HEX_APOS);
    return $json !== false ? esc_attr($json) : '';
}

// ============================================================
//  6. VALIDAÇÃO
// ============================================================

/**
 * Valida se uma string é uma data no formato YYYY-MM-DD.
 *
 * @param string $date_str
 * @return bool
 */
function vana_is_valid_date(string $date_str): bool {
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_str)) {
        return false;
    }
    [$y, $m, $d] = explode('-', $date_str);
    return checkdate((int) $m, (int) $d, (int) $y);
}

// ============================================================
//  7. DUAL-TIMEZONE HELPER
// ============================================================

/**
 * Gera um <span> com data-ts para conversão de fuso no JS.
 *
 * O JS (initDualTimezone) lerá o data-ts e preencherá o conteúdo
 * com o horário local do visitante, apenas se fuso ≠ fuso do evento.
 *
 * @param string       $time_str   Horário no formato "HH:MM" (fuso do evento)
 * @param string       $date_str   Data no formato "YYYY-MM-DD"
 * @param DateTimeZone $event_tz   Fuso horário do evento
 * @return string                  HTML do <span> ou '' em caso de erro
 */
function vana_local_time_span(string $time_str, string $date_str, DateTimeZone $event_tz): string {
    if ($time_str === '' || !vana_is_valid_date($date_str)) return '';

    try {
        $dt = new DateTimeImmutable(
            $date_str . ' ' . $time_str . ':00',
            $event_tz
        );
    } catch (Exception $e) {
        return '';
    }

    $ts = $dt->getTimestamp();

    return sprintf(
        '<span class="vana-local-time-target" data-ts="%d" aria-live="polite"></span>',
        $ts
    );
}
