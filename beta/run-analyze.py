#!/usr/bin/env python3
import paramiko
import sys

HOST = '149.62.37.117'
PORT = 65002
USER = 'u419701790'
PASS = 'Mga@4455'
REMOTE_BASE = '/home/u419701790/domains/vanamadhuryamdaily.com/public_html/beta_html'

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect(HOST, port=PORT, username=USER, password=PASS)

# Upload script
sftp = ssh.open_sftp()
sftp.put(r'C:\Users\marce\Desktop\vanamadhuryamdaily\vmc-vscode\beta\analyze-visit-meta.php', 
         '/tmp/analyze-visit-meta.php')
sftp.close()

# Execute
cmd = f"cd {REMOTE_BASE} && php /tmp/analyze-visit-meta.php"
stdin, stdout, stderr = ssh.exec_command(cmd)

output = stdout.read().decode('utf-8')
errors = stderr.read().decode('utf-8')

ssh.close()

print(output)
if errors:
    print("ERROS:", errors, file=sys.stderr)
