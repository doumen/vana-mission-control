#!/usr/bin/env python3
"""
Coleta 3 grupos de diagnóstico para bugs do stage.php.
"""
import paramiko
import sys

HOST = "149.62.37.117"
PORT = 65002
USER = "u419701790"
PASSWORD = "Mga@4455"
PLUGIN = "/home/u419701790/domains/vanamadhuryamdaily.com/public_html/beta_html/wp-content/plugins/vana-mission-control"
OUTPUT = r"c:\Users\marce\Desktop\vanamadhuryamdaily\vmc-vscode\beta\diag-stage-bugs.txt"

CMDS = [
    (
        "BUG1 — _bootstrap.php: active_vod / active_event / vod_list",
        f"grep -n 'active_vod\\|active_event\\|vod_list' {PLUGIN}/templates/visit/_bootstrap.php | head -30",
    ),
    (
        "BUG2 — visit-template.php: active_vod / vod_list / extract / compact",
        f"grep -n 'active_vod\\|vod_list\\|extract\\|compact' {PLUGIN}/templates/visit/visit-template.php | head -20",
    ),
    (
        "BUG3 — functions-visit-helpers.php: vana_render_vod_player / vana_get_stage_content / vana_normalize_event",
        f"grep -rn 'function vana_render_vod_player\\|function vana_get_stage_content\\|function vana_normalize_event' {PLUGIN}/includes/ | head -20",
    ),
    (
        "BONUS — stage.php completo (primeiras 80 linhas)",
        f"head -80 {PLUGIN}/templates/visit/parts/stage.php",
    ),
]


def main():
    lines = []
    transport = paramiko.Transport((HOST, PORT))
    try:
        transport.connect(username=USER, password=PASSWORD)
    except Exception as e:
        print(f"CONNECT_ERROR: {e}")
        sys.exit(1)

    client = paramiko.SSHClient()
    client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    client._transport = transport

    for label, cmd in CMDS:
        lines.append(f"\n{'='*60}\n{label}\n{'='*60}\n")
        try:
            _, stdout, stderr = client.exec_command(cmd, timeout=60)
            out = stdout.read().decode("utf-8", errors="replace").strip()
            err = stderr.read().decode("utf-8", errors="replace").strip()
            lines.append((out or "(sem output)") + "\n")
            if err:
                lines.append(f"--- STDERR ---\n{err}\n")
        except Exception as e:
            lines.append(f"ERROR: {e}\n")

    transport.close()

    with open(OUTPUT, "w", encoding="utf-8") as f:
        f.writelines(lines)

    for l in lines:
        print(l, end="")


if __name__ == "__main__":
    main()
