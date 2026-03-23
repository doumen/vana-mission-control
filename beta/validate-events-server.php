<?php
$posts = get_posts([
  "post_type"   => "vana_visit",
  "name"        => "dia-1-vrindavan",
  "post_status" => "any",
]);
if (empty($posts)) {
  echo "Post nao encontrado\n";
  exit;
}

$id  = (int) $posts[0]->ID;
$raw = get_post_meta($id, "_vana_visit_timeline_json", true);
$data = json_decode($raw, true);

echo "Post ID: {$id}\n";
echo "visit_status: " . ($data["visit_status"] ?? "N/A") . "\n";
$days = is_array($data["days"] ?? null) ? $data["days"] : [];
echo "Total days: " . count($days) . "\n";

$total_events = 0;
foreach ($days as $i => $day) {
  $evts = is_array($day["events"] ?? null) ? $day["events"] : (is_array($day["active_events"] ?? null) ? $day["active_events"] : []);
  $count = count($evts);
  $total_events += $count;
  echo "Day {$i} -> {$count} eventos\n";
  foreach ($evts as $ev) {
    echo "  event_key: " . ($ev["event_key"] ?? $ev["key"] ?? "SEM KEY") . "\n";
  }
}

echo "Total events geral: {$total_events}\n";
