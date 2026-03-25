<?php
/**
 * Class Vana_Utils
 * Helpers centrais do tema Vana Madhuryam.
 *
 * Métodos públicos:
 *   ::t()               → tradução i18n
 *   ::pick_i18n()       → extrai string no idioma correto de um array ['pt'=>..,'en'=>..]
 *   ::pick_i18n_key()   → mesmo que pick_i18n, mas aceita chave dot-notation
 *   ::safe_https_url()  → garante HTTPS e sanitiza URL
 *   ::maybe_embed_url() → converte URL de vídeo YouTube em URL de embed
 */
if (!defined('ABSPATH')) exit;

class Vana_Utils
{
    // =========================================================================
    // i18n
    // =========================================================================

    /**
     * Retorna string traduzida do dicionário central.
     *
     * @param string $key
     * @param string $lang 'pt' | 'en'
     * @return string
     */
    public static function t(string $key, string $lang = 'pt'): string
    {
        static $strings = null;

        if ($strings === null) {
            $path = get_template_directory() . '/i18n/strings.php';

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
    // i18n helpers
    // =========================================================================

    /**
     * Extrai string localizada de um array ['pt' => '...', 'en' => '...'].
     *
     * @param array  $map  Ex: ['pt' => 'Título', 'en' => 'Title']
     * @param string $lang 'pt' | 'en'
     * @return string
     */
    public static function pick_i18n(array $map, string $lang = 'pt'): string
    {
        if (empty($map)) return '';

        return (string) (
            $map[$lang]  ??
            $map['en']   ??
            $map['pt']   ??
            reset($map)  ??  // primeiro valor disponível
            ''
        );
    }

    /**
     * Mesmo que pick_i18n(), mas aceita dot-notation para acessar
     * arrays aninhados.
     *
     * Ex: pick_i18n_key($data, 'hero.title', 'pt')
     *
     * @param array  $data
     * @param string $dot_key
     * @param string $lang
     * @return string
     */
    public static function pick_i18n_key(array $data, string $dot_key, string $lang = 'pt'): string
    {
        $keys    = explode('.', $dot_key);
        $current = $data;

        foreach ($keys as $k) {
            if (!is_array($current) || !array_key_exists($k, $current)) {
                return '';
            }
            $current = $current[$k];
        }

        if (is_array($current)) {
            return self::pick_i18n($current, $lang);
        }

        return (string) $current;
    }

    // =========================================================================
    // URL helpers
    // =========================================================================

    /**
     * Sanitiza URL e força esquema HTTPS.
     * Retorna string vazia se a URL for inválida.
     *
     * @param string $url
     * @return string
     */
    public static function safe_https_url(string $url): string
    {
        $url = trim($url);

        if ($url === '') return '';

        // Força HTTPS
        $url = preg_replace('#^http://#i', 'https://', $url);

        // Sanitiza com WordPress
        $sanitized = esc_url_raw($url, ['https']);

        return $sanitized ?: '';
    }

    /**
     * Converte URL de vídeo YouTube (watch, share, shorts)
     * em URL de embed adequada para <iframe>.
     *
     * Retorna string vazia se não for YouTube ou ID inválido.
     *
     * Formatos suportados:
     *   https://www.youtube.com/watch?v=VIDEO_ID
     *   https://youtu.be/VIDEO_ID
     *   https://www.youtube.com/shorts/VIDEO_ID
     *   https://www.youtube.com/embed/VIDEO_ID  ← já é embed, retorna limpo
     *
     * @param string $url
     * @return string URL de embed | string vazia
     */
    public static function maybe_embed_url(string $url): string
    {
        $url = trim($url);

        if ($url === '') return '';

        $video_id = '';

        // Já é embed
        if (preg_match('#youtube\.com/embed/([a-zA-Z0-9_-]{11})#i', $url, $m)) {
            $video_id = $m[1];

        // watch?v=
        } elseif (preg_match('#[?&]v=([a-zA-Z0-9_-]{11})#i', $url, $m)) {
            $video_id = $m[1];

        // youtu.be/
        } elseif (preg_match('#youtu\.be/([a-zA-Z0-9_-]{11})#i', $url, $m)) {
            $video_id = $m[1];

        // shorts/
        } elseif (preg_match('#youtube\.com/shorts/([a-zA-Z0-9_-]{11})#i', $url, $m)) {
            $video_id = $m[1];
        }

        if ($video_id === '') return '';

        return 'https://www.youtube-nocookie.com/embed/' . $video_id
             . '?rel=0&modestbranding=1';
    }
}
