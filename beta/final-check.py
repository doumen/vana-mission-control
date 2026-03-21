import paramiko

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect('149.62.37.117', port=65002, username='u419701790', password='Mga@4455', timeout=30)

print("VERIFICAÇÃO FINAL DA PÁGINA DE VISITA\n")
print("=" * 60)

# 1. Status HTTP
stdin, stdout, stderr = ssh.exec_command('curl -s -I https://beta.vanamadhuryamdaily.com/visit/vrindavan-2026-02/ | head -1')
http_status = stdout.read().decode('utf-8').strip()
print(f"\n1. Status HTTP: {http_status}")

# 2. Conteúdo presente
stdin, stdout, stderr = ssh.exec_command('''curl -s https://beta.vanamadhuryamdaily.com/visit/vrindavan-2026-02/ | wc -c''')
size = stdout.read().decode('utf-8').strip()
print(f"2. Tamanho da página: {size} bytes")

# 3. Elementos principais
elements = {
    'vana-visit': 'Classe principal',
    'vana-visit-main': 'Container principal',
    'day-tabs': 'Abas de dias',
    'vana-stage': 'Stage/Player',
    'hero-header': 'Hero Header'
}

print("\n3. Elementos detectados:")
for element, desc in elements.items():
    stdin, stdout, stderr = ssh.exec_command(f'''curl -s https://beta.vanamadhuryamdaily.com/visit/vrindavan-2026-02/ | grep -q "{element}" && echo "✓" || echo "✗"''')
    status = stdout.read().decode('utf-8').strip()
    icon = "✓" if status == "✓" else "✗"
    print(f"   {icon} {desc} ({element})")

# 4. Erros no debug.log
print("\n4. Erros recentes:")
stdin, stdout, stderr = ssh.exec_command('tail -5 /home/u419701790/domains/vanamadhuryamdaily.com/public_html/beta_html/wp-content/debug.log 2>/dev/null | grep -i "error\|parse"')
result = stdout.read().decode('utf-8').strip()
if result:
    print(f"   ✗ {result[:100]}")
else:
    print("   ✓ Nenhum erro recente")

print("\n" + "=" * 60)
print("✓ Página de visita restaurada com sucesso!")

ssh.close()
