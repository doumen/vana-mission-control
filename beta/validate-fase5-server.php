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

$all_keys = [];
foreach ($days as $i => $day) {
  $evts = is_array($day["events"] ?? null) ? $day["events"] : (is_array($day["active_events"] ?? null) ? $day["active_events"] : []);
  echo "Day {$i} -> " . count($evts) . " eventos\n";
  foreach ($evts as $ev) {
    $k = (string)($ev["event_key"] ?? $ev["key"] ?? "SEM KEY");
    echo "  event_key: {$k}\n";
    if ($k !== "SEM KEY" && $k !== "") {
      $all_keys[] = $k;
    }
  }
}

$probe = wp_remote_get(home_url('/wp-json/vana/v1/stage/__probe__?visit_id=' . $id . '&lang=pt'));
if (is_wp_error($probe)) {
  echo "NEW probe error: " . $probe->get_error_message() . "\n";
} else {
  echo "NEW probe status: " . wp_remote_retrieve_response_code($probe) . "\n";
}

$old = wp_remote_get(home_url('/wp-json/vana/v1/stage-fragment?visit_id=' . $id . '&item_type=restore&lang=pt'));
if (is_wp_error($old)) {
  echo "OLD restore error: " . $old->get_error_message() . "\n";
} else {
  echo "OLD restore status: " . wp_remote_retrieve_response_code($old) . "\n";
}

if (!empty($all_keys)) {
  $key = $all_keys[0];
  $new = wp_remote_get(home_url('/wp-json/vana/v1/stage/' . rawurlencode($key) . '?visit_id=' . $id . '&lang=pt'));
  if (is_wp_error($new)) {
    echo "NEW real error: " . $new->get_error_message() . "\n";
  } else {
    echo "NEW real status: " . wp_remote_retrieve_response_code($new) . " (key={$key})\n";
  }
} else {
  echo "NEW real status: skipped (sem event_key real)\n";
}
