<?php
/**
 * Processador de Imagens — Vana Submission
 * Arquivo: includes/class-vana-image-processor.php
 * Version: 1.0.0
 *
 * Responsabilidades:
 *  1. Valida MIME real (magic bytes)
 *  2. Valida tamanho bruto
 *  3. Resize proporcional pelo perfil do subtype
 *  4. Converte para WebP
 *  5. Remove metadados EXIF (exceto orientação)
 *  6. Retorna path do arquivo temporário otimizado
 *
 * Uso:
 *   $result = Vana_Image_Processor::process(
 *       $tmp_path,    // path do arquivo $_FILES['foto']['tmp_name']
 *       'gurudeva'    // subtype: 'devotee' | 'gurudeva'
 *   );
 *
 *   if (is_wp_error($result)) { ... }
 *
 *   // $result = [
 *   //   'path'     => '/tmp/vana_abc123.webp',
 *   //   'size'     => 243871,   // bytes após otimização
 *   //   'width'    => 1200,
 *   //   'height'   => 900,
 *   //   'subtype'  => 'devotee',
 *   //   'profile'  => ['max_px' => 1200, 'quality' => 80],
 *   //   'engine'   => 'imagick' | 'gd',
 *   // ]
 */
defined('ABSPATH') || exit;

final class Vana_Image_Processor {

    // ── MIME types aceitos → extensão interna ─────────────────
    private const MIME_MAP = [
        'image/jpeg' => 'jpeg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
        'image/gif'  => 'gif',
    ];

    // ── Magic bytes para validação real do arquivo ────────────
    private const MAGIC_BYTES = [
        'image/jpeg' => ["\xFF\xD8\xFF"],
        'image/png'  => ["\x89PNG\r\n\x1A\n"],
        'image/webp' => ['RIFF'],           // valida RIFF + WEBP logo abaixo
        'image/gif'  => ['GIF87a', 'GIF89a'],
    ];

    // ── Tamanho máximo bruto (antes do resize) ────────────────
    private const MAX_BYTES = Vana_Submission_CPT::MAX_IMAGE_BYTES; // 5MB

    // ════════════════════════════════════════════════════════════
    //  ENTRY POINT
    // ════════════════════════════════════════════════════════════

    /**
     * Processa um arquivo de imagem recebido via upload.
     *
     * @param  string          $source_path  Path do arquivo temporário ($_FILES[…]['tmp_name'])
     * @param  string          $subtype      'devotee' | 'gurudeva'
     * @return array|WP_Error  Array com dados do arquivo processado, ou WP_Error
     */
    public static function process(
        string $source_path,
        string $subtype = 'devotee'
    ): array|WP_Error {

        // 1. Arquivo existe e é legível?
        if (!is_readable($source_path)) {
            return new WP_Error(
                'file_not_readable',
                'O arquivo enviado não pôde ser lido.'
            );
        }

        // 2. Valida tamanho bruto
        $raw_size = filesize($source_path);
        if ($raw_size === false || $raw_size > self::MAX_BYTES) {
            return new WP_Error(
                'file_too_large',
                sprintf(
                    'O arquivo excede o limite de %sMB.',
                    number_format(self::MAX_BYTES / 1024 / 1024, 0)
                )
            );
        }

        // 3. Valida MIME real via magic bytes
        $mime = self::detect_mime($source_path);
        if (is_wp_error($mime)) return $mime;

        // 4. Resolve perfil pelo subtype
        $subtype  = in_array($subtype, ['devotee', 'gurudeva'], true)
            ? $subtype
            : 'devotee';
        $profile  = Vana_Submission_CPT::get_image_profile($subtype);

        // 5. Processa — Imagick preferencial, GD como fallback
        if (class_exists('Imagick')) {
            $result = self::process_imagick($source_path, $mime, $profile);
        } else {
            $result = self::process_gd($source_path, $mime, $profile);
        }

        if (is_wp_error($result)) return $result;

        // 6. Anota subtype e perfil no resultado
        $result['subtype']  = $subtype;
        $result['profile']  = $profile;

        return $result;
    }

    // ════════════════════════════════════════════════════════════
    //  ENGINE — IMAGICK
    // ════════════════════════════════════════════════════════════

    /**
     * @return array|WP_Error
     */
    private static function process_imagick(
        string $source_path,
        string $mime,
        array  $profile
    ): array|WP_Error {

        try {
            $im = new Imagick($source_path);

            // Resolve orientação EXIF antes de qualquer operação
            $im->autoOrient();

            // Strip de todos os metadados
            // Mantém só o perfil de cor ICC para fidelidade visual
            $icc = null;
            try {
                $icc = $im->getImageProfile('icc');
            } catch (ImagickException $e) {
                // Sem perfil ICC — ok
            }
            $im->stripImage();
            if ($icc !== null && $icc !== '') {
                try {
                    $im->setImageProfile('icc', $icc);
                } catch (ImagickException $e) {
                    // Ignora se não conseguir restaurar
                }
            }

            // Dimensões atuais
            $orig_w = $im->getImageWidth();
            $orig_h = $im->getImageHeight();

            // Calcula novas dimensões
            [$new_w, $new_h] = self::calc_dimensions(
                $orig_w,
                $orig_h,
                $profile['max_px']
            );

            // Resize (só se necessário)
            if ($new_w !== $orig_w || $new_h !== $orig_h) {
                $im->resizeImage(
                    $new_w,
                    $new_h,
                    Imagick::FILTER_LANCZOS,  // melhor qualidade para downscale
                    1.0
                );
            }

            // Converte para WebP
            $im->setImageFormat('webp');
            $im->setImageCompressionQuality($profile['quality']);

            // Configurações extras WebP
            $im->setOption('webp:method',          '6');  // melhor compressão
            $im->setOption('webp:lossless',        'false');
            $im->setOption('webp:auto-filter',     'true');

            // GIF animado — mantém só primeiro frame
            if ($mime === 'image/gif') {
                $im = $im->coalesceImages();
                $im->setIteratorIndex(0);
            }

            // Salva em arquivo temporário
            $out_path = self::tmp_path('webp');
            $im->writeImage($out_path);
            $im->clear();
            $im->destroy();

            return [
                'path'   => $out_path,
                'size'   => filesize($out_path),
                'width'  => $new_w,
                'height' => $new_h,
                'engine' => 'imagick',
            ];

        } catch (ImagickException $e) {
            // Tenta fallback para GD
            if (function_exists('imagecreatefromjpeg')) {
                return self::process_gd($source_path, $mime, $profile);
            }
            return new WP_Error(
                'imagick_failed',
                'Erro ao processar imagem: ' . $e->getMessage()
            );
        }
    }

    // ════════════════════════════════════════════════════════════
    //  ENGINE — GD (fallback)
    // ════════════════════════════════════════════════════════════

    /**
     * @return array|WP_Error
     */
    private static function process_gd(
        string $source_path,
        string $mime,
        array  $profile
    ): array|WP_Error {

        // Carrega imagem fonte
        $src = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($source_path),
            'image/png'  => @imagecreatefrompng($source_path),
            'image/webp' => @imagecreatefromwebp($source_path),
            'image/gif'  => @imagecreatefromgif($source_path),
            default      => false,
        };

        if ($src === false) {
            return new WP_Error(
                'gd_load_failed',
                'GD não conseguiu abrir o arquivo de imagem.'
            );
        }

        // Corrige orientação EXIF (JPEG)
        if ($mime === 'image/jpeg' && function_exists('exif_read_data')) {
            $src = self::gd_fix_orientation($src, $source_path);
        }

        $orig_w = imagesx($src);
        $orig_h = imagesy($src);

        [$new_w, $new_h] = self::calc_dimensions(
            $orig_w,
            $orig_h,
            $profile['max_px']
        );

        // Cria canvas destino
        $dst = imagecreatetruecolor($new_w, $new_h);
        if ($dst === false) {
            imagedestroy($src);
            return new WP_Error('gd_canvas_failed', 'Erro ao criar canvas de destino.');
        }

        // Preserva transparência (PNG/WebP/GIF)
        if (in_array($mime, ['image/png', 'image/webp', 'image/gif'], true)) {
            imagealphablending($dst, false);
            imagesavealpha($dst, true);
            $transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127);
            imagefilledrectangle($dst, 0, 0, $new_w, $new_h, $transparent);
            imagealphablending($dst, true);
        }

        // Resize de alta qualidade
        imagecopyresampled($dst, $src, 0, 0, 0, 0,
            $new_w, $new_h, $orig_w, $orig_h);
        imagedestroy($src);

        // Salva como WebP
        $out_path = self::tmp_path('webp');
        $ok       = imagewebp($dst, $out_path, $profile['quality']);
        imagedestroy($dst);

        if (!$ok) {
            return new WP_Error(
                'gd_webp_failed',
                'GD não conseguiu salvar o arquivo WebP.'
            );
        }

        return [
            'path'   => $out_path,
            'size'   => filesize($out_path),
            'width'  => $new_w,
            'height' => $new_h,
            'engine' => 'gd',
        ];
    }

    // ════════════════════════════════════════════════════════════
    //  HELPERS
    // ════════════════════════════════════════════════════════════

    /**
     * Detecta MIME real via magic bytes.
     *
     * @return string|WP_Error  MIME string ou WP_Error
     */
    private static function detect_mime(string $path): string|WP_Error {
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            return new WP_Error('mime_read_error', 'Não foi possível ler o arquivo.');
        }
        $header = fread($handle, 12);
        fclose($handle);

        foreach (self::MAGIC_BYTES as $mime => $signatures) {
            foreach ($signatures as $sig) {
                if (str_starts_with($header, $sig)) {
                    // WebP: valida também bytes 8-11 = "WEBP"
                    if ($mime === 'image/webp') {
                        if (substr($header, 8, 4) !== 'WEBP') continue;
                    }
                    return $mime;
                }
            }
        }

        // Fallback: finfo
        if (function_exists('finfo_open')) {
            $finfo    = finfo_open(FILEINFO_MIME_TYPE);
            $detected = finfo_file($finfo, $path);
            finfo_close($finfo);

            if (isset(self::MIME_MAP[$detected])) {
                return $detected;
            }
        }

        return new WP_Error(
            'invalid_mime',
            'Formato de imagem não suportado. Use JPEG, PNG, WebP ou GIF.'
        );
    }

    /**
     * Calcula novas dimensões mantendo proporção.
     * Não amplia imagens menores que max_px.
     *
     * @return int[]  [width, height]
     */
    private static function calc_dimensions(
        int $orig_w,
        int $orig_h,
        int $max_px
    ): array {
        // Já está dentro do limite — não amplia
        if ($orig_w <= $max_px && $orig_h <= $max_px) {
            return [$orig_w, $orig_h];
        }

        // Reduz pelo lado maior
        if ($orig_w >= $orig_h) {
            $ratio = $max_px / $orig_w;
        } else {
            $ratio = $max_px / $orig_h;
        }

        return [
            max(1, (int) round($orig_w * $ratio)),
            max(1, (int) round($orig_h * $ratio)),
        ];
    }

    /**
     * Corrige orientação EXIF com GD.
     */
    private static function gd_fix_orientation(
        GdImage $image,
        string  $source_path
    ): GdImage {
        try {
            $exif = @exif_read_data($source_path);
            $orientation = (int) ($exif['Orientation'] ?? 1);
        } catch (\Throwable $e) {
            return $image;
        }

        return match ($orientation) {
            3 => imagerotate($image, 180, 0) ?: $image,
            6 => imagerotate($image, -90,  0) ?: $image,
            8 => imagerotate($image,  90,  0) ?: $image,
            default => $image,
        };
    }

    /**
     * Gera path único para arquivo temporário.
     */
    private static function tmp_path(string $ext): string {
        return sys_get_temp_dir()
            . '/vana_'
            . uniqid('', true)
            . '.' . $ext;
    }

    /**
     * Remove arquivo temporário com segurança.
     * Chamar após upload para Cloudflare.
     */
    public static function cleanup(string $path): void {
        if (
            $path !== '' &&
            str_contains($path, 'vana_') &&
            is_file($path)
        ) {
            @unlink($path);
        }
    }
}
