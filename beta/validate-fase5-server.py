#!/usr/bin/env python3
import paramiko

HOST = "149.62.37.117"
PORT = 65002
USER = "u419701790"
PASSWORD = "Mga@4455"
REMOTE_DIR = "/home/u419701790/domains/vanamadhuryamdaily.com/public_html/beta_html"

WP_EVAL = r'''$posts = get_posts([
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
    if ($k !== "SEM KEY" && $k !== "") { $all_keys[] = $k; }
  }
}

// Probe endpoint novo com chave fake
$probe = wp_remote_get(home_url('/wp-json/vana/v1/stage/__probe__?visit_id=' . $id . '&lang=pt'));
if (is_wp_error($probe)) {
  echo "NEW probe error: " . $probe->get_error_message() . "\n";
} else {
  echo "NEW probe status: " . wp_remote_retrieve_response_code($probe) . "\n";
}

// Endpoint legado restore
$old = wp_remote_get(home_url('/wp-json/vana/v1/stage-fragment?visit_id=' . $id . '&item_type=restore&lang=pt'));
if (is_wp_error($old)) {
  echo "OLD restore error: " . $old->get_error_message() . "\n";
} else {
  echo "OLD restore status: " . wp_remote_retrieve_response_code($old) . "\n";
}

// Se existir key real, testa endpoint novo real
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
'''


def main():
    transport = paramiko.Transport((HOST, PORT))
    transport.connect(username=USER, password=PASSWORD)

    client = paramiko.SSHClient()
    client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    client._transport = transport

    cmd = f"cd {REMOTE_DIR} && wp eval '{WP_EVAL}' --allow-root 2>&1"
    stdin, stdout, stderr = client.exec_command(cmd, timeout=120)
    out = stdout.read().decode("utf-8", errors="replace")
    err = stderr.read().decode("utf-8", errors="replace")

    print(out)
    if err.strip():
        print("STDERR:\n" + err)

    transport.close()


if __name__ == "__main__":
    main()
