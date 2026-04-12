#!/usr/bin/env python3
import paramiko, sys
HOST = "149.62.37.117"
PORT = 65002
USER = "u419701790"
PASSWORD = "Mga@4455"
REMOTE_PLUGIN = "/home/u419701790/domains/vanamadhuryamdaily.com/public_html/beta_html/wp-content/plugins/vana-mission-control"
LOCAL_FILES = [
    r"c:\Users\marce\Desktop\vanamadhuryamdaily\vmc-vscode\wp-content\plugins\vana-mission-control\templates\visit\assets\visit-scripts.php",
    r"c:\Users\marce\Desktop\vanamadhuryamdaily\vmc-vscode\wp-content\plugins\vana-mission-control\templates\visit\_bootstrap.php",
]

try:
    transport = paramiko.Transport((HOST, PORT))
    transport.connect(username=USER, password=PASSWORD)
except Exception as e:
    print('CONNECT_ERROR', e); sys.exit(1)

sftp = paramiko.SFTPClient.from_transport(transport)
for lf in LOCAL_FILES:
    remote = REMOTE_PLUGIN + lf.split('templates\\visit')[-1].replace('\\','/')
    print('Uploading', lf, '->', remote)
    try:
        sftp.put(lf, remote)
        print('  OK')
    except Exception as e:
        print('  ERROR', e)
sftp.close()

client = paramiko.SSHClient()
client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
client._transport = transport

verify_cmd = f"grep -n \"swapStageYouTube\|CFG\.\|legacy query param\|inject-test-vod\" {REMOTE_PLUGIN}/templates/visit/assets/visit-scripts.php {REMOTE_PLUGIN}/templates/visit/_bootstrap.php 2>/dev/null | head -n 40"
_, stdout, stderr = client.exec_command(verify_cmd, timeout=30)
print('\n===== VERIFY =====')
print(stdout.read().decode('utf-8', errors='replace'))

transport.close()
