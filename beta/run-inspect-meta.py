#!/usr/bin/env python3
import paramiko

HOST = "149.62.37.117"
PORT = 65002
USER = "u419701790"
PASSWORD = "Mga@4455"
REMOTE_DIR = "/home/u419701790/domains/vanamadhuryamdaily.com/public_html/beta_html"
LOCAL_FILE = r"c:\Users\marce\Desktop\vanamadhuryamdaily\vmc-vscode\beta\inspect-post-359-meta.php"

transport = paramiko.Transport((HOST, PORT))
transport.connect(username=USER, password=PASSWORD)

# Upload
sftp = paramiko.SFTPClient.from_transport(transport)
remote_path = f"{REMOTE_DIR}/inspect-post-359-meta.php"
sftp.put(LOCAL_FILE, remote_path)
sftp.close()

# Execute
client = paramiko.SSHClient()
client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
client._transport = transport

cmd = f"cd {REMOTE_DIR} && wp eval-file inspect-post-359-meta.php --allow-root"
_, stdout, stderr = client.exec_command(cmd, timeout=120)
out = stdout.read().decode("utf-8", errors="replace")
err = stderr.read().decode("utf-8", errors="replace")
print(out if out else "(no stdout)")
if err:
    print("STDERR:\n", err)

transport.close()
