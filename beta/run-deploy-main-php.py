#!/usr/bin/env python3
"""
Deploy do vana-mission-control.php (enqueue atualizado) para beta_html.
"""
import paramiko
import sys

HOST = "149.62.37.117"
PORT = 65002
USER = "u419701790"
PASSWORD = "Mga@4455"
REMOTE_PLUGIN = "/home/u419701790/domains/vanamadhuryamdaily.com/public_html/beta_html/wp-content/plugins/vana-mission-control"
LOCAL_MAIN   = r"c:\Users\marce\Desktop\vanamadhuryamdaily\vmc-vscode\wp-content\plugins\vana-mission-control\vana-mission-control.php"
OUTPUT_FILE  = r"c:\Users\marce\Desktop\vanamadhuryamdaily\vmc-vscode\beta\deploy-main-php-result.txt"


def main():
    lines = []

    transport = paramiko.Transport((HOST, PORT))
    try:
        transport.connect(username=USER, password=PASSWORD)
    except Exception as e:
        lines.append(f"CONNECT_ERROR: {e}\n")
        _save(OUTPUT_FILE, lines)
        sys.exit(1)

    # Upload vana-mission-control.php
    sftp = paramiko.SFTPClient.from_transport(transport)
    remote_main = f"{REMOTE_PLUGIN}/vana-mission-control.php"
    try:
        sftp.put(LOCAL_MAIN, remote_main)
        lines.append(f"UPLOAD OK: {remote_main}\n")
    except Exception as e:
        lines.append(f"UPLOAD_ERROR: {e}\n")
    sftp.close()

    # Verify the new enqueue is in place
    client = paramiko.SSHClient()
    client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    client._transport = transport

    verify_cmd = f"grep -n 'VanaEventController\\|vana-event-controller\\|htmx' {remote_main}"
    try:
        _, stdout, stderr = client.exec_command(verify_cmd, timeout=30)
        out = stdout.read().decode("utf-8", errors="replace")
        lines.append(f"===== VERIFY ENQUEUE =====\n{out}\n")
    except Exception as e:
        lines.append(f"VERIFY_ERROR: {e}\n")

    transport.close()
    _save(OUTPUT_FILE, lines)
    for l in lines:
        print(l, end="")


def _save(path, lines):
    with open(path, "w", encoding="utf-8") as f:
        f.writelines(lines)


if __name__ == "__main__":
    main()
