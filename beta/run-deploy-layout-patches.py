#!/usr/bin/env python3
import paramiko

HOST = "149.62.37.117"
PORT = 65002
USER = "u419701790"
PASSWORD = "Mga@4455"
REMOTE_PLUGIN = "/home/u419701790/domains/vanamadhuryamdaily.com/public_html/beta_html/wp-content/plugins/vana-mission-control"

FILES = [
    (r"c:\Users\marce\Desktop\vanamadhuryamdaily\vmc-vscode\wp-content\plugins\vana-mission-control\templates\visit\visit-template.php",
     "templates/visit/visit-template.php"),
    (r"c:\Users\marce\Desktop\vanamadhuryamdaily\vmc-vscode\wp-content\plugins\vana-mission-control\assets\css\vana-visit.css",
     "assets/css/vana-visit.css"),
]

transport = paramiko.Transport((HOST, PORT))
transport.connect(username=USER, password=PASSWORD)
sftp = paramiko.SFTPClient.from_transport(transport)

print("DEPLOYING LAYOUT PATCHES...")
for local, remote_rel in FILES:
    remote = f"{REMOTE_PLUGIN}/{remote_rel}"
    print(f"  {remote_rel}...", end=" ")
    sftp.put(local, remote)
    print("OK")

sftp.close()

# Verify
client = paramiko.SSHClient()
client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
client._transport = transport

print("\nVERIFYING PATCHES...")
cmd = f"grep -n 'vana-stage-grid' {REMOTE_PLUGIN}/templates/visit/visit-template.php"
_, stdout, _ = client.exec_command(cmd, timeout=60)
print("Grid wrapper in visit-template.php:")
print(stdout.read().decode("utf-8", errors="replace"))

cmd = f"grep -n 'vana-stage-grid' {REMOTE_PLUGIN}/assets/css/vana-visit.css"
_, stdout, _ = client.exec_command(cmd, timeout=60)
print("Grid CSS in vana-visit.css:")
print(stdout.read().decode("utf-8", errors="replace"))

transport.close()
print("\n✅ Layout patches deployed and verified")
