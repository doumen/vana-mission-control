import paramiko

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect('149.62.37.117', port=65002, username='u419701790', password='Mga@4455', timeout=30)

print("DIAGNÓSTICO DE ERRO\n" + "="*60 + "\n")

# 1. Erros no debug log
print("1. ÚLTIMOS ERROS:")
stdin, stdout, stderr = ssh.exec_command('tail -30 /home/u419701790/domains/vanamadhuryamdaily.com/public_html/beta_html/wp-content/debug.log 2>/dev/null | tail -15')
result = stdout.read().decode('utf-8')
print(result if result.strip() else "Nenhum erro")

# 2. Status HTTP
print("\n2. STATUS HTTP:")
stdin, stdout, stderr = ssh.exec_command('curl -s -I https://beta.vanamadhuryamdaily.com/visit/vrindavan-2026-02/ | head -1')
result = stdout.read().decode('utf-8').strip()
print(result)

# 3. Conteúdo retornado
print("\n3. CONTEÚDO RETORNADO (primeiras 500 chars):")
stdin, stdout, stderr = ssh.exec_command('curl -s https://beta.vanamadhuryamdaily.com/visit/vrindavan-2026-02/ | head -200')
result = stdout.read().decode('utf-8')
if '<html' in result or '<!DOCTYPE' in result:
    print("✓ HTML presente")
    # Show first meaningful content
    lines = result.split('\n')
    for i, line in enumerate(lines):
        if '<body' in line or 'vana' in line.lower():
            print("  " + lines[i:min(i+5, len(lines))])
            break
else:
    print("✗ Sem HTML")
    print(result[:300])

ssh.close()
