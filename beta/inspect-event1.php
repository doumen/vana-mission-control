<?php
$posts = get_posts([
    'post_type' => 'vana_visit',
    'name' => 'dia-1-vrindavan',
    'post_status' => 'any',
]);

if (empty($posts)) {
    echo "VISIT_NOT_FOUND\n";
    return;
}

$id = (int) $posts[0]->ID;
$raw = get_post_meta($id, '_vana_visit_timeline_json', true);
$data = $raw ? json_decode($raw, true) : [];
$events = $data['days'][0]['events'] ?? [];

echo "=== EVENTO [1] COMPLETO ===\n";
print_r($events[1] ?? 'EVENTO[1] NAO EXISTE');
