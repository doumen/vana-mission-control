#!/usr/bin/env python3
"""
Deploy stage.php com patch de active_event.
"""
import paramiko
import sys

HOST = "149.62.37.117"
PORT = 65002
USER = "u419701790"
PASSWORD = "Mga@4455"
REMOTE_PLUGIN = "/home/u419701790/domains/vanamadhuryamdaily.com/public_html/beta_html/wp-content/plugins/vana-mission-control"
LOCAL_STAGE = r"c:\Users\marce\Desktop\vanamadhuryamdaily\vmc-vscode\wp-content\plugins\vana-mission-control\templates\visit\parts\stage.php"
OUTPUT = r"c:\Users\marce\Desktop\vanamadhuryamdaily\vmc-vscode\beta\deploy-stage-patch-result.txt"


def main():
    lines = []
    transport = paramiko.Transport((HOST, PORT))
    try:
        transport.connect(username=USER, password=PASSWORD)
    except Exception as e:
        print(f"CONNECT_ERROR: {e}")
        sys.exit(1)

    sftp = paramiko.SFTPClient.from_transport(transport)
    remote_stage = f"{REMOTE_PLUGIN}/templates/visit/parts/stage.php"
    try:
        sftp.put(LOCAL_STAGE, remote_stage)
        lines.append(f"UPLOAD OK: {remote_stage}\n")
    except Exception as e:
        lines.append(f"UPLOAD_ERROR: {e}\n")
    sftp.close()

    client = paramiko.SSHClient()
    client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    client._transport = transport

    # Verify patch landed
    verify = f"grep -n 'active_event\\|_vods\\|_vod_first\\|active_vod' {remote_stage} | head -20"
    _, stdout, stderr = client.exec_command(verify, timeout=30)
    out = stdout.read().decode("utf-8", errors="replace").strip()
    lines.append(f"\n===== VERIFY =====\n{out}\n")

    transport.close()

    with open(OUTPUT, "w", encoding="utf-8") as f:
        f.writelines(lines)
    for l in lines:
        print(l, end="")


if __name__ == "__main__":
    main()
