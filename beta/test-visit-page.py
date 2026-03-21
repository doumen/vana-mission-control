import paramiko

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect('149.62.37.117', port=65002, username='u419701790', password='Mga@4455', timeout=30)

print("Testando acesso à página de visita...\n")

# Get the page HTML
stdin, stdout, stderr = ssh.exec_command('''curl -s https://beta.vanamadhuryamdaily.com/visit/vrindavan-2026-02/ | head -100''')
result = stdout.read().decode('utf-8')

if '<!DOCTYPE' in result or '<html' in result:
    print("✓ Página em HTML carregando!")
    
    # Check if has content
    if '<body' in result:
        print("✓ Body tag presente")
    
    if 'vana-visit' in result or 'visit-hub' in result:
        print("✓ Classes de visita detectadas")
    else:
        print("⚠ Classes de visita não encontradas - pode estar vazia")
    
    # Look for specific content
    lines = result.split('\n')
    print(f"\nPrimeiras linhas do HTML ({len(lines)} linhas):")
    for i, line in enumerate(lines[:30]):
        if line.strip():
            print(f"  {line[:80]}")
else:
    print("✗ HTML não carregou")
    print(result[:300])

ssh.close()
