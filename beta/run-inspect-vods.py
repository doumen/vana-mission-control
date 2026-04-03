#!/usr/bin/env python3
import paramiko
import sys

HOST = "149.62.37.117"
PORT = 65002
USER = "u419701790"
PASSWORD = "Mga@4455"
REMOTE_DIR = "/home/u419701790/domains/vanamadhuryamdaily.com/public_html/beta_html"
LOCAL_FILE = r"c:\Users\marce\Desktop\vanamadhuryamdaily\vmc-vscode\beta\inspect-vods.php"
REMOTE_FILE = "inspect-vods.php"

transport = paramiko.Transport((HOST, PORT))
try:
    transport.connect(username=USER, password=PASSWORD)
except Exception as e:
    print('CONNECT_ERROR', e)
    sys.exit(1)

sftp = paramiko.SFTPClient.from_transport(transport)
try:
    sftp.put(LOCAL_FILE, f"{REMOTE_DIR}/{REMOTE_FILE}")
    print('UPLOAD OK')
except Exception as e:
    print('UPLOAD_ERROR', e)
    transport.close()
    sys.exit(1)

sftp.close()
client = paramiko.SSHClient()
client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
client._transport = transport

cmd = f"cd {REMOTE_DIR} && wp eval-file {REMOTE_FILE} --allow-root 2>&1"
_, stdout, stderr = client.exec_command(cmd, timeout=60)
out = stdout.read().decode('utf-8', errors='replace')
err = stderr.read().decode('utf-8', errors='replace')
print(out)
if err.strip():
    print('STDERR:\n' + err)

transport.close()
