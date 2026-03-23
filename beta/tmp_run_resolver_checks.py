import paramiko

HOST = "149.62.37.117"
PORT = 65002
USER = "u419701790"
PASSWORD = "Mga@4455"

COMMANDS = [
    (
        "FIND_VISIT_STAGE_RESOLVER",
        "find /home/u419701790/domains/vanamadhuryamdaily.com/public_html/beta_html/wp-content/plugins/vana-mission-control -name \"*.php\" | xargs grep -l \"VisitStageResolver\" | head -5",
    ),
    (
        "GREP_RESOLVER_EVENT_LOGIC",
        "grep -n \"event\\|GET\\|active_event\\|active_vod\\|event_key\" /home/u419701790/domains/vanamadhuryamdaily.com/public_html/beta_html/wp-content/plugins/vana-mission-control/includes/class-visit-stage-resolver.php | head -60",
    ),
    (
        "WP_EVAL_EVENT_1",
        "wp eval '$posts = get_posts([\"post_type\"=>\"vana_visit\",\"name\"=>\"dia-1-vrindavan\",\"post_status\"=>\"any\"]); $id = $posts[0]->ID; $data = json_decode(get_post_meta($id,\"_vana_visit_timeline_json\",true), true); $evts = $data[\"days\"][0][\"events\"] ?? []; echo \"=== EVENTO [1] COMPLETO ===\\n\"; print_r($evts[1] ?? \"EVENTO[1] NAO EXISTE\");' --allow-root --path=/home/u419701790/domains/vanamadhuryamdaily.com/public_html/beta_html",
    ),
]

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect(HOST, port=PORT, username=USER, password=PASSWORD, timeout=30)

try:
    for label, command in COMMANDS:
        print("\n" + "=" * 20 + " " + label + " " + "=" * 20)
        stdin, stdout, stderr = ssh.exec_command(command, timeout=180)
        out = stdout.read().decode("utf-8", errors="ignore")
        err = stderr.read().decode("utf-8", errors="ignore")
        if out.strip():
            print(out)
        if err.strip():
            print("[STDERR]")
            print(err)
finally:
    ssh.close()
