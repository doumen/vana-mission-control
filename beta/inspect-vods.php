<?php
$visit_id = 353;
if (!class_exists('VisitStageResolver')) {
    echo "VisitStageResolver missing\n";
    return;
}
$vm = VisitStageResolver::resolve($visit_id);
$vars = $vm->to_template_vars();
$active_events = is_array($vars['active_events'] ?? null) ? $vars['active_events'] : [];
foreach ($active_events as $ev) {
    $ek = $ev['event_key'] ?? ($ev['key'] ?? '(no_key)');
    echo "EVENT: $ek\n";
    $media = $ev['media'] ?? [];
    $vods = [];
    if (is_array($media['vods'] ?? null)) $vods = $media['vods'];
    elseif (is_array($ev['vods'] ?? null)) $vods = $ev['vods'];
    echo " vods_count: " . count($vods) . "\n";
    foreach ($vods as $v) {
        $vid = $v['video_id'] ?? ($v['url'] ?? '');
        $vk = $v['vod_key'] ?? ($v['id'] ?? ($v['vod_id'] ?? '')); 
        $prov = $v['provider'] ?? '';
        $title = is_array($v['title'] ?? null) ? json_encode($v['title']) : ($v['title'] ?? '');
        echo "  - vod_key=" . $vk . " provider=" . $prov . " video_id=" . $vid . " title=" . substr($title,0,120) . "\n";
    }
}
?>