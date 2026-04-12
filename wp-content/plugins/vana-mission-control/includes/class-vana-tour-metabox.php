<?php
defined('ABSPATH') || exit;

class Vana_Tour_Metabox {

    public static function init(): void {
        add_action('add_meta_boxes', [__CLASS__, 'register']);
        add_action('save_post_vana_tour', [__CLASS__, 'save'], 10, 2);
    }

    public static function register(): void {
        add_meta_box(
            'vana_tour_i18n',
            'Títulos PT / EN',
            [__CLASS__, 'render'],
            'vana_tour',
            'normal',
            'default'
        );
    }

    public static function render(\WP_Post $post): void {
        wp_nonce_field('vana_tour_i18n_save', 'vana_tour_i18n_nonce');

        $title_pt = (string) get_post_meta($post->ID, '_vana_title_pt', true);
        $title_en = (string) get_post_meta($post->ID, '_vana_title_en', true);
        $dates    = (string) get_post_meta($post->ID, '_tour_dates_label', true);
        ?>
        <table class="form-table">
          <tr>
            <th><label for="vana_title_pt">Título (PT)</label></th>
            <td>
              <input type="text" name="vana_title_pt" id="vana_title_pt" value="<?= esc_attr($title_pt) ?>" style="width:100%;" />
              <p class="description">Título editorial em Português. Fallbacks aplicam-se se vazio.</p>
            </td>
          </tr>
          <tr>
            <th><label for="vana_title_en">Title (EN)</label></th>
            <td>
              <input type="text" name="vana_title_en" id="vana_title_en" value="<?= esc_attr($title_en) ?>" style="width:100%;" />
              <p class="description">Editorial title in English. Used when lang=en.</p>
            </td>
          </tr>
          <tr>
            <th><label for="vana_tour_dates">Rótulo de datas</label></th>
            <td>
              <input type="text" name="vana_tour_dates" id="vana_tour_dates" value="<?= esc_attr($dates) ?>" style="width:100%;" />
              <p class="description">Label de datas exibido no archive/teasers (opcional).</p>
            </td>
          </tr>
        </table>
        <?php
    }

    public static function save(int $post_id, \WP_Post $post): void {
        if (
            ! isset($_POST['vana_tour_i18n_nonce']) ||
            ! wp_verify_nonce($_POST['vana_tour_i18n_nonce'], 'vana_tour_i18n_save') ||
            ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) ||
            ! current_user_can('edit_post', $post_id)
        ) return;

        $pt = sanitize_text_field($_POST['vana_title_pt'] ?? '');
        $en = sanitize_text_field($_POST['vana_title_en'] ?? '');
        $dt = sanitize_text_field($_POST['vana_tour_dates'] ?? '');

        update_post_meta($post_id, '_vana_title_pt', $pt);
        update_post_meta($post_id, '_vana_title_en', $en);
        update_post_meta($post_id, '_tour_dates_label', $dt);
    }
}
