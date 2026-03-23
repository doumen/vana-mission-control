#!/usr/bin/env python3
"""
Deploy and test Phase 3 on remote server
Uploads test-stage-fragment.php and executes via wp eval-file
"""

import subprocess
import sys
import os

# Configuration
SSH_HOST = "149.62.37.117"
SSH_PORT = 65002
SSH_USER = "u419701790"
REMOTE_PATH = "/home/u419701790/domains/vanamadhuryamdaily.com/public_html/beta_html"
LOCAL_TEST_FILE = r"c:\Users\marce\Desktop\vanamadhuryamdaily\vmc-vscode\beta\test-stage-fragment.php"

print("=" * 60)
print("FASE 3 — Deploy and Test on Remote Server")
print("=" * 60)
print()

# Step 1: Upload test file
print("Step 1: Upload test-stage-fragment.php to server")
print(f"  From: {LOCAL_TEST_FILE}")
print(f"  To: {SSH_HOST}:{REMOTE_PATH}/")

scp_cmd = [
    "scp",
    "-P", str(SSH_PORT),
    "-o", "StrictHostKeyChecking=no",
    LOCAL_TEST_FILE,
    f"{SSH_USER}@{SSH_HOST}:{REMOTE_PATH}/test-stage-fragment.php"
]

try:
    result = subprocess.run(scp_cmd, capture_output=True, text=True, timeout=30)
    if result.returncode == 0:
        print("  ✓ Upload successful\n")
    else:
        print(f"  ✗ Upload failed: {result.stderr}")
        sys.exit(1)
except Exception as e:
    print(f"  ✗ Error: {e}")
    sys.exit(1)

# Step 2: Execute test via wp eval-file
print("Step 2: Execute test via WP-CLI")
print(f"  Server: {SSH_HOST}")
print(f"  Command: wp eval-file test-stage-fragment.php\n")

ssh_cmd = [
    "ssh",
    "-p", str(SSH_PORT),
    "-o", "StrictHostKeyChecking=no",
    f"{SSH_USER}@{SSH_HOST}",
    f"cd {REMOTE_PATH} && wp eval-file test-stage-fragment.php --allow-root 2>&1"
]

try:
    result = subprocess.run(ssh_cmd, capture_output=True, text=True, timeout=60)
    if result.returncode == 0:
        print("Test Output:")
        print("-" * 60)
        print(result.stdout)
        print("-" * 60)
        print("\n✓ Test execution successful\n")
    else:
        print("Error Output:")
        print("-" * 60)
        print(result.stderr)
        print(result.stdout)
        print("-" * 60)
        sys.exit(1)
except Exception as e:
    print(f"✗ Error: {e}")
    sys.exit(1)

# Step 3: Summary
print("=" * 60)
print("PHASE 3 VALIDATION COMPLETE")
print("=" * 60)
print()
print("Summary:")
print("  ✓ Test file uploaded to server")
print("  ✓ WP-CLI test executed successfully")
print("  ✓ All validations passed")
print()
print("Next step: Browser integration test on production")
print()
