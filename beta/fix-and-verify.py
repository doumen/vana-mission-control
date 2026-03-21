#!/usr/bin/env python3
"""Upload arquivo corrigido e verifica site"""
import paramiko
import time

HOST = "149.62.37.117"
PORT = 65002
USER = "u419701790"
PASSWORD = "Mga@4455"
LOCAL_FILE = r"c:\Users\marce\Desktop\vanamadhuryamdaily\vmc-vscode\wp-content\plugins\vana-mission-control\vana-mission-control.php"
REMOTE_FILE = "/home/u419701790/domains/vanamadhuryamdaily.com/public_html/wp-content/plugins/vana-mission-control/vana-mission-control.php"

print("=" * 60)
print("UPLOAD E VERIFICAÇÃO DO FIX")
print("=" * 60 + "\n")

# 1. Conecta e faz upload
print("[1/3] Conectando ao servidor...")
ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect(HOST, port=PORT, username=USER, password=PASSWORD, timeout=30)
print("✅ Conectado\n")

print("[2/3] Fazer upload do arquivo corrigido...")
sftp = ssh.open_sftp()
sftp.put(LOCAL_FILE, REMOTE_FILE)
sftp.close()
print("✅ Upload completo\n")

# 2. Aguarda 2 segundos para PHP fazer cache
time.sleep(2)

# 3. Verifica se funciona
print("[3/3] Verificando se WordPress funciona...")
stdin, stdout, stderr = ssh.exec_command(
    "cd /home/u419701790/domains/vanamadhuryamdaily.com/public_html && " +
    "wp eval 'echo \"WordPress OK\";' --allow-root 2>&1"
)
output = stdout.read().decode('utf-8', errors='ignore')
errors = stderr.read().decode('utf-8', errors='ignore')

if "WordPress OK" in output:
    print("✅ WordPress está funcionando!\n")
elif "error" not in errors.lower() and "fatal" not in errors.lower():
    print("✅ Sem erros detectados!\n")
    print("Output:", output[:100])
else:
    print("⚠️  Checar erros:")
    print("STDOUT:", output[:200])
    print("STDERR:", errors[:200])

ssh.close()

print("=" * 60)
print("✅ PROCESSO CONCLUÍDO!")
print("=" * 60)
