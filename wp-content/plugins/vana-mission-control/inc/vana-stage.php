<?php
/**
 * Vana Stage — Camada de abstração do Stage
 *
 * Arquivo: inc/vana-stage.php
 * Requer:  vana_stage_resolve_media() (já existente no tema)
 *
 * Funções exportadas:
 *   vana_normalize_event( array $flat )  → array schema 5.1
 *   vana_get_stage_content( array $event ) → array stage
 *   vana_render_vod_player( array $vod, string $lang ) → string HTML
 *
 * @package VanaMissionControl
 * @since   5.1.0
 */

defined( 'ABSPATH' ) || exit;

// ═══════════════════════════════════════════════════════════
//  0. MEDIA RESOLVER — Detecta provider, video_id, url
// ═══════════════════════════════════════════════════════════

/**
 * Resolve provider e IDs de um VOD.
 * Detecta YouTube, Google Drive, Facebook, Instagram e retorna normalizado.
 *
 * @param array $vod Item de VOD com possíveis chaves:
 *                   - provider (string: 'youtube', 'drive', 'facebook', 'instagram')
 *                   - video_id (string: ID do vídeo)
 *                   - url (string: URL completa do vídeo)
 * @return array {
 *          'provider'  => 'youtube'|'drive'|'facebook'|'instagram'|''
 *          'video_id'  => string (para YouTube)
 *          'url'       => string (URL ou drive_id para Drive)
 *        }
 */
function vana_stage_resolve_media( array $vod ): array {

    $provider  = (string) ( $vod['provider']  ?? '' );
    $video_id  = (string) ( $vod['video_id']  ?? '' );
    $url       = (string) ( $vod['url']       ?? '' );

    // ── Se já tem provider normalizado, retorna ───────────────
    if ( ! empty( $provider ) && in_array( $provider, [ 'youtube', 'drive', 'facebook', 'instagram' ], true ) ) {
        return [
            'provider'  => $provider,
            'video_id'  => $video_id,
            'url'       => $url,
        ];
    }

    // ── Detecta por URL ───────────────────────────────────────
    if ( ! empty( $url ) ) {

        // YouTube
        if ( preg_match( '/(youtu\.be|youtube\.com).*(?:v=|\/videos\/)([a-zA-Z0-9_-]{11})/', $url, $m ) ) {
            return [
                'provider'  => 'youtube',
                'video_id'  => $m[2] ?? $video_id,
                'url'       => $url,
            ];
        }

        // Google Drive
        if ( preg_match( '/drive\.google\.com.*[/d/]([a-zA-Z0-9-_]+)/', $url, $m ) ) {
            return [
                'provider'  => 'drive',
                'video_id'  => $m[1] ?? $video_id,
                'url'       => $url,
            ];
        }

        // Facebook
        if ( preg_match( '/(facebook\.com|fb\.com)/', $url ) ) {
            return [
                'provider'  => 'facebook',
                'video_id'  => $video_id,
                'url'       => $url,
            ];
        }

        // Instagram
        if ( preg_match( '/(instagram\.com|instagr\.am)/', $url ) ) {
            return [
                'provider'  => 'instagram',
                'video_id'  => $video_id,
                'url'       => $url,
            ];
        }
    }

    // ── Se só tem video_id, assume YouTube ───────────────────
    if ( ! empty( $video_id ) && strlen( $video_id ) === 11 ) {
        return [
            'provider'  => 'youtube',
            'video_id'  => $video_id,
            'url'       => 'https://www.youtube.com/watch?v=' . $video_id,
        ];
    }

    // ── Vazio / unknown ──────────────────────────────────────
    return [
        'provider'  => '',
        'video_id'  => $video_id,
        'url'       => $url,
    ];
}

// ═══════════════════════════════════════════════════════════
//  1. NORMALIZAÇÃO: schema flat → schema 5.1
// ═══════════════════════════════════════════════════════════

/**
 * Converte variáveis flat do _bootstrap.php para schema 5.1.
 *
 * Uso no template:
 *   $current_event = vana_normalize_event([
 *       'active_vod'   => $active_vod,
 *       'vod_list'     => $vod_list,
 *       'hero'         => $active_day['hero'] ?? [],
 *       'gallery'      => $active_day['gallery'] ?? [],
 *       'sangha'       => $active_day['sangha_moments'] ?? [],
 *       'event_key'    => $active_day['date_local'] ?? '',
 *       'title_pt'     => Vana_Utils::pick_i18n_key($active_vod ?? [], 'title', 'pt'),
 *       'title_en'     => Vana_Utils::pick_i18n_key($active_vod ?? [], 'title', 'en'),
 *       'time_start'   => $active_day['date_local'] ?? '',
 *       'status'       => $visit_status ?? '',
 *   ]);
 *
 * @param array $flat Variáveis flat do bootstrap.
 * @return array Schema 5.1 normalizado.
 */
function vana_normalize_event( array $flat ): array {

    // ── VODs ──────────────────────────────────────────────
    $vods = [];

    // VOD ativo vai para posição [0] (prioridade máxima)
    $active_vod = is_array( $flat['active_vod'] ?? null )
                  ? $flat['active_vod']
                  : [];

    if ( ! empty( $active_vod['provider'] ) ) {
        $vods[] = $active_vod;
    }

    // Restante da vod_list (sem duplicar o ativo)
    $vod_list = is_array( $flat['vod_list'] ?? null )
                ? $flat['vod_list']
                : [];

    foreach ( $vod_list as $vod ) {
        if ( ! is_array( $vod ) ) {
            continue;
        }
        // Evita duplicata do ativo (compara video_id ou url)
        $is_active = ! empty( $active_vod['video_id'] )
                     && ( ( $vod['video_id'] ?? '' ) === $active_vod['video_id'] );
        if ( ! $is_active ) {
            $vods[] = $vod;
        }
    }

    // ── Gallery ───────────────────────────────────────────
    $gallery = is_array( $flat['gallery'] ?? null )
               ? $flat['gallery']
               : [];

    // ── Sangha Moments ────────────────────────────────────
    $sangha = is_array( $flat['sangha'] ?? null )
              ? $flat['sangha']
              : [];

    // ── Retorna schema 5.1 ────────────────────────────────
    return [
        'event_key'  => (string) ( $flat['event_key']  ?? '' ),
        'title_pt'   => (string) ( $flat['title_pt']   ?? '' ),
        'title_en'   => (string) ( $flat['title_en']   ?? '' ),
        'time_start' => (string) ( $flat['time_start'] ?? '' ),
        'status'     => (string) ( $flat['status']     ?? '' ),
        'media'      => [
            'vods'           => $vods,
            'gallery'        => $gallery,
            'sangha_moments' => $sangha,
        ],
    ];
}


// ═══════════════════════════════════════════════════════════
//  2. HIERARQUIA DO STAGE
// ═══════════════════════════════════════════════════════════

/**
 * Resolve o conteúdo do Stage seguindo hierarquia:
 *   VOD → Gallery → Sangha → Placeholder
 *
 * @param array $event Schema 5.1 (output de vana_normalize_event).
 * @return array {
 *     type: 'vod'|'gallery'|'sangha'|'placeholder'
 *     data: mixed
 *     live: bool        (apenas type=vod)
 *     count: int        (apenas type=gallery)
 *     event: array      (apenas type=placeholder)
 * }
 */
function vana_get_stage_content( array $event ): array {

    // ── 1. VOD ────────────────────────────────────────────
    $vods = $event['media']['vods'] ?? [];
    if ( ! empty( $vods[0] ) && is_array( $vods[0] ) ) {
        return [
            'type' => 'vod',
            'data' => $vods[0],
            'live' => ( $event['status'] ?? '' ) === 'live',
        ];
    }

    // ── 2. Gallery ────────────────────────────────────────
    $gallery = $event['media']['gallery'] ?? [];
    if ( ! empty( $gallery[0] ) && is_array( $gallery[0] ) ) {
        return [
            'type'  => 'gallery',
            'data'  => $gallery,
            'count' => count( $gallery ),
        ];
    }

    // ── 3. Sangha Moment ──────────────────────────────────
    $sangha = $event['media']['sangha_moments'] ?? [];
    if ( ! empty( $sangha[0] ) && is_array( $sangha[0] ) ) {
        return [
            'type' => 'sangha',
            'data' => $sangha[0],
        ];
    }

    // ── 4. Placeholder ────────────────────────────────────
    return [
        'type'  => 'placeholder',
        'event' => [
            'title'  => $event['title_pt'] ?? $event['title_en'] ?? '',
            'time'   => $event['time_start'] ?? '',
            'status' => $event['status'] ?? '',
        ],
    ];
}


// ═══════════════════════════════════════════════════════════
//  3. RENDER DO PLAYER — delega para vana_stage_resolve_media()
// ═══════════════════════════════════════════════════════════

/**
 * Renderiza o player de um VOD.
 * Reutiliza a lógica já existente em vana_stage_resolve_media().
 *
 * @param array  $vod  Item de VOD (schema 5.1 media.vods[n]).
 * @param string $lang 'pt'|'en'
 * @return string HTML do player.
 */
function vana_render_vod_player( array $vod, string $lang = 'pt' ): string {

    // Reutiliza o resolver já testado em produção
    $resolved = vana_stage_resolve_media( $vod );

    $provider   = (string) ( $resolved['provider'] ?? '' );
    $video_id   = (string) ( $resolved['video_id'] ?? '' );
    $url        = (string) ( $resolved['url']      ?? '' );
    $title_attr = esc_attr( Vana_Utils::pick_i18n_key( $vod, 'title', $lang ) ?: 'Hari-katha' );

    ob_start();

    switch ( $provider ) {

        // ── YouTube ───────────────────────────────────────
        case 'youtube':
            if ( $video_id ) : ?>
                <iframe
                    id="vanaStageIframe"
                    src="https://www.youtube-nocookie.com/embed/<?php echo esc_attr( $video_id ); ?>?rel=0"
                    title="<?php echo $title_attr; ?>"
                    style="position:absolute;inset:0;width:100%;height:100%;border:0;"
                    allowfullscreen
                    loading="lazy"
                ></iframe>
            <?php endif;
            break;

        // ── Google Drive ──────────────────────────────────
        case 'drive':
            $fid = function_exists( 'vana_drive_file_id' )
                   ? vana_drive_file_id( $url )
                   : '';
            if ( $fid ) : ?>
                <iframe
                    id="vanaStageIframe"
                    src="https://drive.google.com/file/d/<?php echo esc_attr( $fid ); ?>/preview"
                    title="<?php echo $title_attr; ?>"
                    style="position:absolute;inset:0;width:100%;height:100%;border:0;"
                    allow="autoplay"
                    loading="lazy"
                ></iframe>
            <?php else : ?>
                <div class="vana-stage-placeholder">
                    <a href="<?php echo esc_url( $url ); ?>"
                       target="_blank" rel="noopener"
                       class="vana-stage-cta">
                        <?php echo esc_html( vana_t( 'stage.drive_cta', $lang ) ); ?>
                    </a>
                </div>
            <?php endif;
            break;

        // ── Facebook ──────────────────────────────────────
        case 'facebook':
            $fb_embed = 'https://www.facebook.com/plugins/video.php?href='
                        . rawurlencode( $url ) . '&show_text=0&width=1200';
            ?>
            <iframe
                id="vanaFbIframe"
                src="<?php echo esc_url( $fb_embed ); ?>"
                title="<?php echo esc_attr( vana_t( 'stage.fb_title', $lang ) ); ?>"
                style="position:absolute;inset:0;width:100%;height:100%;border:0;"
                scrolling="no"
                allow="autoplay;clipboard-write;encrypted-media;picture-in-picture;web-share"
                allowfullscreen="1"
                referrerpolicy="origin-when-cross-origin"
            ></iframe>
            <?php
            break;

        // ── Instagram ─────────────────────────────────────
        case 'instagram': ?>
            <div class="vana-stage-placeholder" style="background:var(--vana-bg);">
                <div style="font-weight:900;color:var(--vana-text);font-size:1.3rem;
                            margin-bottom:10px;font-family:'Syne',sans-serif;">
                    <?php echo esc_html( vana_t( 'stage.ig_title', $lang ) ); ?>
                </div>
                <div style="color:var(--vana-muted);margin-bottom:20px;">
                    <?php echo esc_html( vana_t( 'stage.ig_sub', $lang ) ); ?>
                </div>
                <a href="<?php echo esc_url( $url ); ?>"
                   target="_blank" rel="noopener"
                   style="display:inline-block;background:var(--vana-pink);color:#fff;
                          padding:12px 24px;border-radius:8px;font-weight:900;
                          text-decoration:none;font-size:1.1rem;">
                    <?php echo esc_html( vana_t( 'stage.ig_open', $lang ) ); ?>
                </a>
            </div>
            <?php
            break;

        // ── Genérico / fallback ───────────────────────────
        default:
            if ( $url ) : ?>
                <div class="vana-stage-placeholder">
                    <a href="<?php echo esc_url( $url ); ?>"
                       target="_blank" rel="noopener"
                       class="vana-stage-cta"
                       style="background:var(--vana-line);">
                        <?php echo esc_html( vana_t( 'stage.generic_cta', $lang ) ); ?>
                    </a>
                </div>
            <?php endif;
            break;
    }

    return (string) ob_get_clean();
}
