#!/usr/bin/env python3
import paramiko

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect('149.62.37.117', port=65002, username='u419701790', password='Mga@4455', timeout=30)

print("=" * 80)
print("CHECK 1: Post 359 status in database")
print("=" * 80)

cmd1 = """mysql -uu419701790_beta_user -pMga@4455 -h127.0.0.1 u419701790_beta -e \
"SELECT ID, post_title, post_type, post_status, post_name FROM wp_posts WHERE ID = 359;" 2>/dev/null"""
_, stdout, _ = ssh.exec_command(cmd1, timeout=30)
print(stdout.read().decode('utf-8', 'ignore'))

print("\n" + "=" * 80)
print("CHECK 2: Check if post is in trash")
print("=" * 80)

cmd2 = """mysql -uu419701790_beta_user -pMga@4455 -h127.0.0.1 u419701790_beta -e \
"SELECT COUNT(*) as trash_count FROM wp_posts WHERE post_type = 'vana_visit' AND post_status IN ('draft', 'pending', 'trash');" 2>/dev/null"""
_, stdout, _ = ssh.exec_command(cmd2, timeout=30)
print(stdout.read().decode('utf-8', 'ignore'))

print("\n" + "=" * 80)
print("CHECK 3: Flush rewrite rules via WP")
print("=" * 80)

cmd3 = """cd /home/u419701790/domains/vanamadhuryamdaily.com/public_html/beta_html && \
wp rewrite flush --hard --allow-root 2>&1 | head -20"""
_, stdout, stderr = ssh.exec_command(cmd3, timeout=30)
out = stdout.read().decode('utf-8', 'ignore')
err = stderr.read().decode('utf-8', 'ignore')
print("Output:", out)
if err:
    print("Errors:", err[:300])

print("\n" + "=" * 80)
print("CHECK 4: Try again with /beta_html/index.php?p=359")
print("=" * 80)

cmd4 = """curl -L -s 'http://149.62.37.117/beta_html/index.php?p=359' | head -50"""
_, stdout, _ = ssh.exec_command(cmd4, timeout=30)
out = stdout.read().decode('utf-8', 'ignore')
if 'This Page Does Not Exist' in out:
    print("❌ Still 404 with direct index.php")
elif 'vana-page-root' in out:
    print("✅ Got vana-page-root div!")
else:
    print("Page output (first 500 chars):")
    print(out[:500])

ssh.close()
