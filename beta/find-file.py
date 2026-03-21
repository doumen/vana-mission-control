import paramiko

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect('149.62.37.117', port=65002, username='u419701790', password='Mga@4455', timeout=30)

print("Checking directory structure...")
stdin, stdout, stderr = ssh.exec_command('find /home/u419701790/domains -name "vana-mission-control.php" 2>/dev/null')
result = stdout.read().decode('utf-8').strip()

print("Files found:")
for line in result.split('\n'):
    if line:
        print(f"  {line}")

ssh.close()
