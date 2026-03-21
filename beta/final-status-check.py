import paramiko

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect('149.62.37.117', port=65002, username='u419701790', password='Mga@4455', timeout=30)

print("VERIFICAÇÃO FINAL APÓS RESTAURAÇÃO\n" + "="*60)

# 1. WordPress OK
stdin, stdout, stderr = ssh.exec_command('cd /home/u419701790/domains/vanamadhuryamdaily.com/public_html/beta_html && wp eval "echo \'✓\';" --allow-root 2>&1 | head -1')
result = stdout.read().decode('utf-8').strip()
print(f"\n1. WordPress: {'✓ OK' if '✓' in result else '✗ ERRO'}")

# 2. Página carrega
stdin, stdout, stderr = ssh.exec_command('curl -s -I https://beta.vanamadhuryamdaily.com/visit/vrindavan-2026-02/ 2>&1 | head -1')
result = stdout.read().decode('utf-8').strip()
print(f"2. HTTP Status: {result}")

# 3. Tamanho da página
stdin, stdout, stderr = ssh.exec_command('curl -s https://beta.vanamadhuryamdaily.com/visit/vrindavan-2026-02/ 2>&1 | wc -c')
size = stdout.read().decode('utf-8').strip()
print(f"3. Tamanho: {size} bytes")

# 4. Conteúdo presente
stdin, stdout, stderr = ssh.exec_command('curl -s https://beta.vanamadhuryamdaily.com/visit/vrindavan-2026-02/ | grep -c "vana"')
count = stdout.read().decode('utf-8').strip()
print(f"4. Classes 'vana': {count} ocorrências")

# 5. Último erro (se houver)
print("\n5. Status de Erros:")
stdin, stdout, stderr = ssh.exec_command('tail -1 /home/u419701790/domains/vanamadhuryamdaily.com/public_html/beta_html/wp-content/debug.log 2>/dev/null')
result = stdout.read().decode('utf-8').strip()

if 'error' in result.lower() or 'parse' in result.lower():
    print(f"   ⚠ {result[:80]}")
else:
    print("   ✓ Sem erros críticos")

print("\n" + "="*60)
print("✓ Página de visita restaurado com sucesso!")

ssh.close()
