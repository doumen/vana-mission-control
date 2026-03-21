#!/usr/bin/env python3
"""
Fase 2 Test Runner — VanaEventController + Event Selector
Upload test-phase2.php e roda via wp eval-file
"""
import paramiko
import sys

HOST       = "149.62.37.117"
PORT       = 65002
USER       = "u419701790"
PASSWORD   = "Mga@4455"
LOCAL_FILE = r"c:\Users\marce\Desktop\vanamadhuryamdaily\vmc-vscode\beta\test-phase2.php"
REMOTE_DIR = "/home/u419701790/domains/vanamadhuryamdaily.com/public_html/beta_html"
OUTPUT_FILE = r"c:\Users\marce\Desktop\vanamadhuryamdaily\vmc-vscode\beta\test-result-phase2.txt"

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

    lines.append("Conexao OK.\n\n")

    # ── Upload via SFTP ───────────────────────────────────
    sftp = paramiko.SFTPClient.from_transport(transport)
    remote_path = f"{REMOTE_DIR}/test-phase2.php"
    lines.append(f"Enviando test-phase2.php para {remote_path}...\n")
    
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
    ssh = paramiko.SSHClient()
    ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    ssh.connect(HOST, port=PORT, username=USER, password=PASSWORD)
    
    cmd = f"cd {REMOTE_DIR} && wp eval-file test-phase2.php --allow-root 2>&1"
    lines.append(f"Executando: wp eval-file test-phase2.php\n")
    lines.append("=" * 60 + "\n\n")
    
    try:
        stdin, stdout, stderr = ssh.exec_command(cmd)
        output = stdout.read().decode('utf-8', errors='ignore')
        lines.append(output)
    except Exception as e:
        lines.append(f"ERRO na execução: {e}\n")
    
    ssh.close()
    transport.close()
    
    lines.append("\n" + "=" * 60 + "\n")
    _save(OUTPUT_FILE, lines)
    print("✅ Teste completo!")
    print(f"📁 Resultado salvo em: {OUTPUT_FILE}")
    
    # Lê e exibe o resultado
    with open(OUTPUT_FILE, 'r', encoding='utf-8') as f:
        print("\n" + f.read())

def _save(filepath, lines):
    with open(filepath, 'w', encoding='utf-8') as f:
        f.writelines(lines)

if __name__ == '__main__':
    main()
