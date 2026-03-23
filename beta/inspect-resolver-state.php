<?php
$visit_id = 353;
$raw = get_post_meta($visit_id, '_vana_visit_timeline_json', true);
$timeline = $raw ? json_decode($raw, true) : [];
$day = is_array($timeline['days'][0] ?? null) ? $timeline['days'][0] : [];
echo 'day.events=' . (is_array($day['events'] ?? null) ? count($day['events']) : 0) . "\n";
echo 'day.active_events=' . (is_array($day['active_events'] ?? null) ? count($day['active_events']) : 0) . "\n";
if (class_exists('VisitStageResolver')) {
    $vm = VisitStageResolver::resolve($visit_id);
    $vars = $vm->to_template_vars();
    $active_events = is_array($vars['active_events'] ?? null) ? $vars['active_events'] : [];
    echo 'vm.active_events=' . count($active_events) . "\n";
    foreach ($active_events as $event) {
        echo 'event_key=' . ($event['event_key'] ?? $event['key'] ?? 'SEM_KEY') . "\n";
    }
} else {
    echo "VisitStageResolver missing\n";
}
