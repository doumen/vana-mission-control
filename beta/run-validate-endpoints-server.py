#!/usr/bin/env python3
import paramiko

HOST = "149.62.37.117"
PORT = 65002
USER = "u419701790"
PASSWORD = "Mga@4455"
REMOTE_DIR = "/home/u419701790/domains/vanamadhuryamdaily.com/public_html/beta_html"
LOCAL_FILE = r"c:\Users\marce\Desktop\vanamadhuryamdaily\vmc-vscode\beta\validate-endpoints-server.php"
REMOTE_FILE = "validate-endpoints-server.php"

transport = paramiko.Transport((HOST, PORT))
transport.connect(username=USER, password=PASSWORD)

sftp = paramiko.SFTPClient.from_transport(transport)
sftp.put(LOCAL_FILE, f"{REMOTE_DIR}/{REMOTE_FILE}")
sftp.close()

client = paramiko.SSHClient()
client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
client._transport = transport

stdin, stdout, stderr = client.exec_command(
    f"cd {REMOTE_DIR} && wp eval-file {REMOTE_FILE} --allow-root 2>&1",
    timeout=60,
)
out = stdout.read().decode("utf-8", errors="replace")
err = stderr.read().decode("utf-8", errors="replace")
print(out)
if err.strip():
    print("STDERR:\n" + err)

transport.close()
