#!/usr/bin/env python3
"""
1. Faz upload do test-stage-states.php via SFTP
2. Executa wp eval-file via SSH
3. Salva resultado em beta/test-result.txt
"""
import paramiko
import sys
import os

HOST       = "149.62.37.117"
PORT       = 65002
USER       = "u419701790"
PASSWORD   = "Mga@4455"
REMOTE_DIR = "/home/u419701790/domains/vanamadhuryamdaily.com/public_html/beta_html"
TEST_FILE  = "test-stage-states.php"
LOCAL_FILE = r"c:\Users\marce\Desktop\vanamadhuryamdaily\vmc-vscode\beta\test-stage-states.php"
OUTPUT_FILE = r"c:\Users\marce\Desktop\vanamadhuryamdaily\vmc-vscode\beta\test-result.txt"

def main():
    lines = []

    # ── Conecta ──────────────────────────────────────────
    transport = paramiko.Transport((HOST, PORT))
    lines.append(f"Conectando em {HOST}:{PORT}...\n")
    try:
        transport.connect(username=USER, password=PASSWORD)
    except Exception as e:
        lines.append(f"ERRO ao conectar: {e}\n")
        _save(OUTPUT_FILE, lines)
        sys.exit(1)

    lines.append("Conexao OK.\n")

    # ── Upload via SFTP ───────────────────────────────────
    sftp = paramiko.SFTPClient.from_transport(transport)
    remote_path = f"{REMOTE_DIR}/{TEST_FILE}"
    lines.append(f"Fazendo upload: {LOCAL_FILE} -> {remote_path}\n")
    try:
        sftp.put(LOCAL_FILE, remote_path)
        lines.append("Upload OK.\n\n")
    except Exception as e:
        lines.append(f"ERRO no upload: {e}\n")
        sftp.close()
        transport.close()
        _save(OUTPUT_FILE, lines)
        sys.exit(1)
    sftp.close()

    # ── Executa WP CLI via SSH ────────────────────────────
    client = paramiko.SSHClient()
    client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    client._transport = transport

    cmd = f"cd {REMOTE_DIR} && wp eval-file {TEST_FILE} 2>&1"
    lines.append(f"Executando: {cmd}\n")
    lines.append("=" * 60 + "\n")
    lines.append("OUTPUT:\n")
    lines.append("=" * 60 + "\n")

    try:
        stdin, stdout, stderr = client.exec_command(cmd, timeout=60)
        output = stdout.read().decode("utf-8", errors="replace")
        errors = stderr.read().decode("utf-8", errors="replace")
        lines.append(output + "\n")
        if errors:
            lines.append("=" * 60 + "\n")
            lines.append("STDERR:\n")
            lines.append("=" * 60 + "\n")
            lines.append(errors + "\n")
    except Exception as e:
        lines.append(f"ERRO ao executar: {e}\n")

    transport.close()

    lines.append("=" * 60 + "\n")
    lines.append("DONE\n")

    _save(OUTPUT_FILE, lines)
    print(f"Resultado salvo em: {OUTPUT_FILE}")

def _save(path, lines):
    with open(path, "w", encoding="utf-8") as f:
        f.writelines(lines)

if __name__ == "__main__":
    main()
