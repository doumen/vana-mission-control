#!/usr/bin/env python3
import paramiko

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect('149.62.37.117', port=65002, username='u419701790', password='Mga@4455', timeout=30)

# Try curl with follow-location
print("Downloading post 359 page with curl...")
cmd = """curl -L -s 'http://149.62.37.117/beta_html/?p=359' -o /tmp/post-359-page.html 2>&1 && wc -c /tmp/post-359-page.html"""
_, stdout, stderr = ssh.exec_command(cmd, timeout=30)
out = stdout.read().decode('utf-8', 'ignore')
err = stderr.read().decode('utf-8', 'ignore')
print(f"Download result: {out}")
if err:
    print(f"Errors: {err}")

# Try to get file
try:
    sftp = ssh.open_sftp()
    sftp.get('/tmp/post-359-page.html', 'beta/post-359-page-raw.html')
    sftp.close()
    
    with open('beta/post-359-page-raw.html', 'r', encoding='utf-8', errors='ignore') as f:
        html = f.read()
    
    print(f"\n✓ File read. Size: {len(html)} bytes")
    
    if len(html) > 0:
        # Show first 3000 chars
        print("\n--- FIRST 3000 CHARS ---")
        print(html[:3000])
        print("\n--- SEARCH PATTERNS ---")
        
        patterns = ['vana-page-root', 'vana-stage', '<body', '<div id=', 'VANA-PLUGIN-TEMPLATE', 'error', 'Fatal']
        for p in patterns:
            if p in html:
                idx = html.find(p)
                print(f"\n✓ Found '{p}' at position {idx}")
                print(f"  Context: ...{html[max(0,idx-50):idx+100]}...")
    else:
        print("❌ File is empty - wget might have failed")
except Exception as e:
    print(f"❌ Error: {e}")

ssh.close()
