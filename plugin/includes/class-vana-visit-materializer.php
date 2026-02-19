<?php
defined('ABSPATH') || exit;

final class Vana_Visit_Materializer {
    public static function derive_from_timeline_json(string $timeline_json): array {
        $out = ['start_date' => '', 'tz' => ''];
        $data = json_decode($timeline_json, true);
        if (!is_array($data)) return $out;

        $tz_raw = (string)($data['location_meta']['tz'] ?? '');
        if ($tz_raw !== '' && strlen($tz_raw) <= 64) {
            $out['tz'] = sanitize_text_field($tz_raw);
        }

        $days = $data['days'] ?? null;
        if (!is_array($days)) return $out;

        $dates = [];
        foreach ($days as $day) {
            if (!is_array($day)) continue;
            $d = (string)($day['date_local'] ?? '');
            if ($d !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) {
                $dates[] = $d;
            }
        }
        if ($dates) {
            sort($dates);
            $out['start_date'] = $dates[0];
        }
        return $out;
    }

    public static function apply_to_post(int $visit_id, array $derived): void {
        $start_date = (string)($derived['start_date'] ?? '');
        $tz = (string)($derived['tz'] ?? '');

        if ($start_date !== '') update_post_meta($visit_id, '_vana_start_date', $start_date);
        else delete_post_meta($visit_id, '_vana_start_date');

        if ($tz !== '') update_post_meta($visit_id, '_vana_tz', $tz);
        else delete_post_meta($visit_id, '_vana_tz');
    }
}