#!/usr/bin/env python3
"""
Fase 4 Decision — Inspect current data schema on production server
Runs wp eval to check actual post structure before migration
"""

import paramiko
import sys

# Configuration
SSH_HOST = "149.62.37.117"
SSH_PORT = 65002
SSH_USER = "u419701790"
REMOTE_PATH = "/home/u419701790/domains/vanamadhuryamdaily.com/public_html"

# Create SSH client
client = paramiko.SSHClient()
client.set_missing_host_key_policy(paramiko.AutoAddPolicy())

print("=" * 70)
print("FASE 4 — Inspect Current Data Schema on Production")
print("=" * 70)
print()
print(f"Connecting to {SSH_HOST}:{SSH_PORT}...")
print()

try:
    # Connect
    client.connect(SSH_HOST, port=SSH_PORT, username=SSH_USER, timeout=30)
    
    # Execute WP-CLI command
    wp_command = """cd {} && wp eval '
$posts = get_posts(["post_type"=>"vana_visit","posts_per_page"=>5,"post_status"=>"any"]);
echo count($posts) . " posts encontrados\n";
foreach($posts as $p){{
  $raw = get_post_meta($p->ID, "_vana_visit_timeline_json", true);
  if(empty($raw)) {{
    echo "ID:{$p->ID} | VAZIO\n";
    continue;
  }}
  $data = json_decode($raw, true);
  $keys = $data ? array_keys($data) : [];
  $visit_status = $data["visit_status"] ?? "N/A";
  echo "ID:{$p->ID} | status:{$visit_status} | schema_keys:" . implode(",", $keys) . "\n";
  
  // Check first day structure
  if(!empty($data["days"])) {{
    $day = $data["days"][0];
    echo "  └─ Day 0: " . ($day["date"] ?? "N/A") . " | events:" . (isset($day["active_events"]) ? count($day["active_events"]) : 0) . "\n";
    if(!empty($day["active_events"])) {{
      $evt = $day["active_events"][0];
      $evt_keys = array_keys($evt);
      $evt_key = $evt["event_key"] ?? $evt["key"] ?? "N/A";
      echo "      └─ Event 0: key={$evt_key} | fields:" . implode(",", array_slice($evt_keys, 0, 5)) . "...\n";
    }}
  }}
}}
' --allow-root 2>&1
""".format(REMOTE_PATH)
    
    print("Executing: wp eval (inspect timeline_json structure)")
    print("-" * 70)
    
    stdin, stdout, stderr = client.exec_command(wp_command)
    output = stdout.read().decode('utf-8')
    error = stderr.read().decode('utf-8')
    
    print(output)
    if error:
        print("Errors/Warnings:")
        print(error)
    
    print("-" * 70)
    print()
    
    # Analysis
    print("=" * 70)
    print("SCHEMA ANALYSIS")
    print("=" * 70)
    print()
    
    if "VAZIO" in output:
        print("⚠ Some posts have empty timeline_json")
        print("  → Migration must skip or handle empty posts")
    
    if "status:" in output:
        print("✓ visit_status field exists")
    
    if "event_key" in output or "key:" in output:
        print("✓ Event keys are in use (schema 5.1 compatible)")
    else:
        print("⚠ Event keys might not be present (check output)")
    
    if "active_events" in output:
        print("✓ active_events array structure confirmed")
    
    print()
    print("=" * 70)
    print("RECOMMENDATION FOR FASE 4")
    print("=" * 70)
    print()
    print("Based on current schema, recommend OPTION C (both in sequence):")
    print()
    print("  Phase 4a → Data Migration")
    print("    • Backup all vana_visit posts")
    print("    • Normalize timeline_json to schema 5.1 (if needed)")
    print("    • Validate event_key presence in all posts")
    print()
    print("  Phase 4b → REST Endpoints  ")
    print("    • Create /stage/{event_key} endpoint")
    print("    • Offload render logic to dedicated route")
    print("    • Cache-friendly URL structure")
    print()
    
    client.close()
    
except paramiko.AuthenticationException:
    print("✗ Authentication failed. Check credentials.")
    sys.exit(1)
except paramiko.SSHException as e:
    print(f"✗ SSH error: {e}")
    sys.exit(1)
except Exception as e:
    print(f"✗ Error: {e}")
    sys.exit(1)
