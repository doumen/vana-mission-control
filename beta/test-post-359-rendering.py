#!/usr/bin/env python3
import paramiko
import re

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect('149.62.37.117', port=65002, username='u419701790', password='Mga@4455', timeout=30)

print("=" * 80)
print("TEST 1: GET /beta_html/?p=359 (Direct Post)")
print("=" * 80)

cmd1 = """wget -q -O - 'http://149.62.37.117/beta_html/?p=359' 2>/dev/null | head -100"""
_, stdout, _ = ssh.exec_command(cmd1, timeout=30)
html = stdout.read().decode('utf-8', 'ignore')
print(html[:1500])
if 'fatal' in html.lower() or 'undefined' in html.lower():
    print("\n❌ FATAL ERRORS DETECTED")
else:
    print("\n✅ No obvious fatals in first 100 lines")

print("\n" + "=" * 80)
print("TEST 2: Check for JavaScript errors or specific error patterns")
print("=" * 80)

cmd2 = """wget -q -O - 'http://149.62.37.117/beta_html/?p=359' 2>/dev/null | grep -i 'fatal\\|notice:\\|warning:\\|undefined'"""
_, stdout, _ = ssh.exec_command(cmd2, timeout=30)
errors = stdout.read().decode('utf-8', 'ignore')
if errors.strip():
    print("⚠️  Errors found:")
    print(errors[:1000])
else:
    print("✅ No error patterns found")

print("\n" + "=" * 80)
print("TEST 3: Check for vana-stage div presence")
print("=" * 80)

cmd3 = """wget -q -O - 'http://149.62.37.117/beta_html/?p=359' 2>/dev/null | grep -o 'id="vana-stage"' | head -2"""
_, stdout, _ = ssh.exec_command(cmd3, timeout=30)
stage = stdout.read().decode('utf-8', 'ignore')
if stage.strip():
    print(f"✅ Stage div found: {stage.count('vana-stage')} times")
else:
    print("❌ No vana-stage div found")

print("\n" + "=" * 80)
print("TEST 4: Check for video player elements")
print("=" * 80)

cmd4 = """wget -q -O - 'http://149.62.37.117/beta_html/?p=359' 2>/dev/null | grep -o 'video_id\\|dQw4w9WgXcQ\\|iframe' | head -5"""
_, stdout, _ = ssh.exec_command(cmd4, timeout=30)
videos = stdout.read().decode('utf-8', 'ignore')
if videos.strip():
    print(f"✅ Video elements found:\n{videos}")
else:
    print("⚠️  No video elements detected")

ssh.close()
