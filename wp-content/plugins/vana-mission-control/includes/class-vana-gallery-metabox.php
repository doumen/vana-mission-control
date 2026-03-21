<?php
/**
 * Metabox: Galeria de Gurudeva
 * Campos: _vana_gallery_ids (JSON array de attachment IDs)
 *         _vana_gallery_type ('photos'|'videos'|'mixed')
 */
defined('ABSPATH') || exit;

class Vana_Gallery_Metabox {

    public static function init(): void {
        add_action('add_meta_boxes', [__CLASS__, 'register']);
        add_action('save_post_vana_visit', [__CLASS__, 'save'], 10, 2);
    }

    public static function register(): void {
        add_meta_box(
            'vana_gallery',
            '📸 Galeria de Gurudeva',
            [__CLASS__, 'render'],
            'vana_visit',
            'normal',
            'default'
        );
    }

    public static function render(\WP_Post $post): void {
        wp_nonce_field('vana_gallery_save', 'vana_gallery_nonce');

        $ids  = (string) get_post_meta($post->ID, '_vana_gallery_ids', true);
        $type = (string) get_post_meta($post->ID, '_vana_gallery_type', true) ?: 'photos';
        ?>
        <table class="form-table">
          <tr>
            <th><label for="vana_gallery_type">Tipo</label></th>
            <td>
              <select name="vana_gallery_type" id="vana_gallery_type">
                <?php foreach (['photos' => 'Fotos', 'videos' => 'Vídeos', 'mixed' => 'Misto'] as $v => $l): ?>
                  <option value="<?= esc_attr($v) ?>" <?= selected($type, $v, false) ?>><?= esc_html($l) ?></option>
                <?php endforeach; ?>
              </select>
            </td>
          </tr>
          <tr>
            <th><label>Mídia</label></th>
            <td>
              <div id="vana-gallery-preview" style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:8px;">
                <?php
                $id_arr = json_decode($ids, true) ?: [];
                foreach ($id_arr as $att_id):
                    echo wp_get_attachment_image((int)$att_id, [80, 80], false, [
                        'data-id' => $att_id,
                        'style'   => 'border-radius:4px;cursor:pointer;'
                    ]);
                endforeach;
                ?>
              </div>
              <input type="hidden" name="vana_gallery_ids" id="vana_gallery_ids"
                     value="<?= esc_attr($ids) ?>">
              <button type="button" class="button" id="vana-gallery-open">
                Adicionar / Editar Mídia
              </button>
            </td>
          </tr>
        </table>

        <script>
        (function($){
            var frame;
            $('#vana-gallery-open').on('click', function(){
                if (frame) { frame.open(); return; }
                frame = wp.media({
                    title: 'Galeria de Gurudeva',
                    multiple: true,
                    library: { type: ['image','video'] }
                });
                frame.on('select', function(){
                    var ids = frame.state().get('selection').map(function(a){ return a.id; });
                    $('#vana_gallery_ids').val(JSON.stringify(ids));
                    var html = '';
                    frame.state().get('selection').each(function(a){
                        html += '<img src="' + (a.attributes.sizes?.thumbnail?.url || a.attributes.url) +
                                '" style="width:80px;height:80px;object-fit:cover;border-radius:4px;" data-id="' + a.id + '">';
                    });
                    $('#vana-gallery-preview').html(html);
                });
                frame.open();
            });
        })(jQuery);
        </script>
        <?php
    }

    public static function save(int $post_id, \WP_Post $post): void {
        if (
            ! isset($_POST['vana_gallery_nonce']) ||
            ! wp_verify_nonce($_POST['vana_gallery_nonce'], 'vana_gallery_save') ||
            ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) ||
            ! current_user_can('edit_post', $post_id)
        ) return;

        // Sanitizar IDs (array de inteiros)
        $raw_ids = json_decode(stripslashes($_POST['vana_gallery_ids'] ?? '[]'), true);
        $clean_ids = array_map('absint', is_array($raw_ids) ? $raw_ids : []);
        update_post_meta($post_id, '_vana_gallery_ids', wp_json_encode($clean_ids));

        $type = sanitize_key($_POST['vana_gallery_type'] ?? 'photos');
        update_post_meta($post_id, '_vana_gallery_type', $type);
    }
}
