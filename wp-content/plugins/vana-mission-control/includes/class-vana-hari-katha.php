<?php
defined('ABSPATH') || exit;

final class Vana_Hari_Katha {

    public static function visit_day_has_kathas(int $visit_id, string $day_key): bool {
        if ($visit_id <= 0 || $day_key === '') {
            return false;
        }

        $ids = get_posts([
            'post_type'      => 'vana_katha',
            'post_status'    => 'publish',
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'no_found_rows'  => true,
            'meta_query'     => [
                [
                    'key'     => '_vana_katha_visit_id',
                    'value'   => $visit_id,
                    'compare' => '=',
                ],
                [
                    'key'     => '_vana_katha_day_key',
                    'value'   => $day_key,
                    'compare' => '=',
                ],
            ],
        ]);

        return !empty($ids);
    }

    public static function get_kathas_for_day(int $visit_id, string $day_key): array {
        if ($visit_id <= 0 || $day_key === '') {
            return [];
        }

        $q = new WP_Query([
            'post_type'      => 'vana_katha',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'orderby'        => 'date',
            'order'          => 'ASC',
            'meta_query'     => [
                [
                    'key'     => '_vana_katha_visit_id',
                    'value'   => $visit_id,
                    'compare' => '=',
                ],
                [
                    'key'     => '_vana_katha_day_key',
                    'value'   => $day_key,
                    'compare' => '=',
                ],
            ],
        ]);

        return $q->posts ?: [];
    }
}

function vana_visit_day_has_kathas(int $visit_id, string $day_key): bool {
    return Vana_Hari_Katha::visit_day_has_kathas($visit_id, $day_key);
}
