<?php
// Inject a test VOD into visit timeline post meta (non-destructive backup)
$visit_id = 353;
$raw = get_post_meta($visit_id, '_vana_visit_timeline_json', true);
if (empty($raw)) {
    echo "NO_VISIT_JSON\n";
    return;
}
$timeline = json_decode($raw, true);
if (!is_array($timeline)) {
    echo "INVALID_JSON\n";
    return;
}
$injected = 0;
$vod = [
    'vod_key' => 'test-vod-1',
    'provider' => 'youtube',
    'video_id' => 'M7lc1UVf-VE',
    'title' => ['pt' => 'Teste VOD (inject)', 'en' => 'Test VOD (inject)'],
];

foreach ($timeline['days'] as &$day) {
    if (!empty($day['active_events'])) {
        foreach ($day['active_events'] as &$ev) {
            if (!isset($ev['media']) || !is_array($ev['media'])) $ev['media'] = [];
            if (!isset($ev['media']['vods']) || !is_array($ev['media']['vods'])) $ev['media']['vods'] = [];
            // avoid duplicate
            $exists = false;
            foreach ($ev['media']['vods'] as $existing) {
                if (isset($existing['video_id']) && $existing['video_id'] === $vod['video_id']) { $exists = true; break; }
            }
            if (!$exists) {
                array_unshift($ev['media']['vods'], $vod); // put first
                $injected++;
            }
        }
    }
}

if ($injected === 0) {
    echo "NO_TARGET_EVENT_OR_ALREADY_PRESENT\n";
    return;
}

// Backup old meta
$backup_key = '_vana_visit_timeline_json_backup_inject_' . time();
update_post_meta($visit_id, $backup_key, $raw);

$new_json = wp_json_encode($timeline);
if ($new_json === false) {
    echo "JSON_ENCODE_ERROR\n";
    return;
}
update_post_meta($visit_id, '_vana_visit_timeline_json', $new_json);
// Clear post meta cache
wp_cache_delete($visit_id, 'post_meta');

echo "INJECTED:$injected\n";
echo "BACKUP_KEY:$backup_key\n";
?>