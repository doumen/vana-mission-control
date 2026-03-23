import paramiko

HOST = "149.62.37.117"
PORT = 65002
USER = "u419701790"
PASSWORD = "Mga@4455"

REMOTE_CMD_2 = r'''grep -n "event\|GET\|active_event\|active_vod\|event_key" /home/u419701790/domains/vanamadhuryamdaily.com/public_html/beta_html/wp-content/plugins/vana-mission-control/includes/class-visit-stage-resolver.php | head -60'''

REMOTE_CMD_3 = r'''wp eval '$posts = get_posts(["post_type"=>"vana_visit","name"=>"dia-1-vrindavan","post_status"=>"any"]); $id = $posts[0]->ID; $data = json_decode(get_post_meta($id,"_vana_visit_timeline_json",true), true); $evts = $data["days"][0]["events"] ?? []; echo "=== EVENTO [1] COMPLETO ===\\n"; print_r($evts[1] ?? "EVENTO[1] NAO EXISTE");' --allow-root --path=/home/u419701790/domains/vanamadhuryamdaily.com/public_html/beta_html'''


def run_cmd(ssh, label, cmd, timeout=180):
    print(f"\n==================== {label} ====================")
    _, stdout, stderr = ssh.exec_command(cmd, timeout=timeout)
    out = stdout.read().decode("utf-8", "ignore").strip()
    err = stderr.read().decode("utf-8", "ignore").strip()
    print(out if out else "(sem stdout)")
    if err:
        print("\n--- STDERR ---")
        print(err)


def main():
    ssh = paramiko.SSHClient()
    ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    ssh.connect(HOST, port=PORT, username=USER, password=PASSWORD, timeout=30)
    try:
        run_cmd(ssh, "RESOLVER_GREP", REMOTE_CMD_2, timeout=120)
        run_cmd(ssh, "EVENT_1_PAYLOAD", REMOTE_CMD_3, timeout=180)
    finally:
        ssh.close()


if __name__ == "__main__":
    main()
