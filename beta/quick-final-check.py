import paramiko

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect('149.62.37.117', port=65002, username='u419701790', password='Mga@4455', timeout=30)

# Quick test
stdin, stdout, stderr = ssh.exec_command('curl -s https://beta.vanamadhuryamdaily.com/visit/vrindavan-2026-02/ 2>&1 | head -20')
result = stdout.read().decode('utf-8')

if '<html' in result or '<!DOCTYPE' in result:
    print("✓ Página com HTML valido")
    if 'vana' in result.lower():
        print("✓ Classes vana encontradas")
else:
    print("Status:")
    print(result[:300])

ssh.close()
