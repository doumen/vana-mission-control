import paramiko

HOST = "149.62.37.117"
PORT = 65002
USER = "u419701790"
PASSWORD = "Mga@4455"
URL = 'https://beta.vanamadhuryamdaily.com/visit/vrindavan-2026-02-2/?v_day=2026-02-22&event=20260221-1703-programa'

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect(HOST, port=PORT, username=USER, password=PASSWORD, timeout=30)

cmd = f"curl -s '{URL}' | sed -n '1,400p'"
stdin, stdout, stderr = ssh.exec_command(cmd, timeout=60)
html = stdout.read().decode('utf-8', errors='replace')
err = stderr.read().decode('utf-8', errors='replace')

print('=== ERR ===')
print(err.strip())
print('\n=== SNIPPET (first 400 lines) ===\n')
print(html[:16000])

checks = {
    'CFG': 'CFG' in html,
    'getVodIndex': 'function getVodIndex' in html or 'getVodIndex()' in html,
    'swapStageYouTube': 'function swapStageYouTube' in html,
    'vanaStageIframe': 'vanaStageIframe' in html,
    'VanaAgenda': 'VanaAgenda' in html,
}
print('\n=== CHECKS ===')
for k, v in checks.items():
    print(f'{k}: {v}')

# Try fetching stage fragment via REST if possible (use visitor visit id 353 inferred)
frag_cmd = "curl -s 'https://beta.vanamadhuryamdaily.com/wp-json/vana/v1/stage-fragment?visit_id=353&item_id=353&item_type=vod&lang=pt' | head -n 40"
stdin, stdout, stderr = ssh.exec_command(frag_cmd, timeout=30)
frag = stdout.read().decode('utf-8', errors='replace')
print('\n=== STAGE FRAGMENT SNIPPET ===\n')
print(frag[:8000])

ssh.close()
