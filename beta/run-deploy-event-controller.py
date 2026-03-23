#!/usr/bin/env python3
"""
1. Faz upload do VanaEventController.js via SFTP
2. Roda grep para mostrar onde outros JS são enfileirados no plugin
"""
import paramiko
import sys

HOST = "149.62.37.117"
PORT = 65002
USER = "u419701790"
PASSWORD = "Mga@4455"
REMOTE_PLUGIN = "/home/u419701790/domains/vanamadhuryamdaily.com/public_html/beta_html/wp-content/plugins/vana-mission-control"
LOCAL_JS = r"c:\Users\marce\Desktop\vanamadhuryamdaily\vmc-vscode\wp-content\plugins\vana-mission-control\assets\js\VanaEventController.js"
OUTPUT_FILE = r"c:\Users\marce\Desktop\vanamadhuryamdaily\vmc-vscode\beta\deploy-event-controller-result.txt"


def main():
    lines = []

    transport = paramiko.Transport((HOST, PORT))
    lines.append(f"Conectando {HOST}:{PORT}...\n")
    try:
        transport.connect(username=USER, password=PASSWORD)
    except Exception as e:
        lines.append(f"CONNECT_ERROR: {e}\n")
        _save(OUTPUT_FILE, lines)
        sys.exit(1)

    lines.append("Conexão OK.\n")

    # Upload VanaEventController.js
    sftp = paramiko.SFTPClient.from_transport(transport)
    remote_js = f"{REMOTE_PLUGIN}/assets/js/VanaEventController.js"
    lines.append(f"Upload: {LOCAL_JS} -> {remote_js}\n")
    try:
        sftp.put(LOCAL_JS, remote_js)
        lines.append("Upload OK.\n")
    except Exception as e:
        lines.append(f"UPLOAD_ERROR: {e}\n")
    sftp.close()

    # Grep enqueue hooks
    client = paramiko.SSHClient()
    client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    client._transport = transport

    grep_cmd = (
        "grep -n 'wp_enqueue\\|wp_register\\|visit-script\\|assets/js\\|vana-event\\|vana_visit_script' "
        f"{REMOTE_PLUGIN}/vana-mission-control.php | head -30"
    )
    lines.append(f"\n===== GREP ENQUEUE =====\n{grep_cmd}\n")
    try:
        _, stdout, stderr = client.exec_command(grep_cmd, timeout=60)
        out = stdout.read().decode("utf-8", errors="replace")
        err = stderr.read().decode("utf-8", errors="replace")
        lines.append(out if out else "(sem output)\n")
        if err:
            lines.append(f"STDERR: {err}\n")
    except Exception as e:
        lines.append(f"CMD_ERROR: {e}\n")

    # Verify file exists on server
    verify_cmd = f"ls -la {REMOTE_PLUGIN}/assets/js/VanaEventController.js"
    lines.append(f"\n===== VERIFY UPLOAD =====\n")
    try:
        _, stdout, stderr = client.exec_command(verify_cmd, timeout=30)
        out = stdout.read().decode("utf-8", errors="replace")
        lines.append(out if out else "(nao encontrado)\n")
    except Exception as e:
        lines.append(f"VERIFY_ERROR: {e}\n")

    transport.close()
    lines.append("\nDONE\n")
    _save(OUTPUT_FILE, lines)
    print(f"Resultado salvo em: {OUTPUT_FILE}")
    for l in lines:
        print(l, end="")


def _save(path, lines):
    with open(path, "w", encoding="utf-8") as f:
        f.writelines(lines)


if __name__ == "__main__":
    main()
