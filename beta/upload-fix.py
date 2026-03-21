#!/usr/bin/env python3
import paramiko
import sys

HOST = "149.62.37.117"
PORT = 65002
USER = "u419701790"
PASSWORD = "Mga@4455"
LOCAL_FILE = r"c:\Users\marce\Desktop\vanamadhuryamdaily\vmc-vscode\wp-content\plugins\vana-mission-control\vana-mission-control.php"
REMOTE_PATH = "/home/u419701790/domains/vanamadhuryamdaily.com/public_html/wp-content/plugins/vana-mission-control/vana-mission-control.php"

print("🔗 Conectando ao servidor...")
try:
    transport = paramiko.Transport((HOST, PORT))
    transport.connect(username=USER, password=PASSWORD)
    print("✅ Conectado!")
except Exception as e:
    print(f"❌ Erro ao conectar: {e}")
    sys.exit(1)

try:
    sftp = paramiko.SFTPClient.from_transport(transport)
    print(f"📤 Enviando vana-mission-control.php...")
    sftp.put(LOCAL_FILE, REMOTE_PATH)
    print("✅ Upload concluído!")
    sftp.close()
except Exception as e:
    print(f"❌ Erro no upload: {e}")
    transport.close()
    sys.exit(1)

# Teste rápido
try:
    ssh = paramiko.SSHClient()
    ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    ssh.connect(HOST, port=PORT, username=USER, password=PASSWORD)

    cmd = "cd /home/u419701790/domains/vanamadhuryamdaily.com/public_html && wp db size --allow-root 2>&1 | head -5"
    stdin, stdout, stderr = ssh.exec_command(cmd)
    output = stdout.read().decode('utf-8', errors='ignore')
    
    if "error" not in output.lower() and "fatal" not in output.lower():
        print("✅ WordPress está respondendo corretamente!")
        print(f"   {output.strip()}")
    else:
        print("⚠️  Verifique o site:")
        print(output)
    
    ssh.close()
except Exception as e:
    print(f"⚠️  Erro ao testar: {e}")

transport.close()
print("\n✅ Arquivo corrigido enviado para o servidor!")
