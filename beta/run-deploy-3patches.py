#!/usr/bin/env python3
import paramiko

HOST = "149.62.37.117"
PORT = 65002
USER = "u419701790"
PASSWORD = "Mga@4455"
REMOTE_PLUGIN = "/home/u419701790/domains/vanamadhuryamdaily.com/public_html/beta_html/wp-content/plugins/vana-mission-control"

FILES = [
    (r"c:\Users\marce\Desktop\vanamadhuryamdaily\vmc-vscode\wp-content\plugins\vana-mission-control\templates\visit\_bootstrap.php", 
     "templates/visit/_bootstrap.php"),
    (r"c:\Users\marce\Desktop\vanamadhuryamdaily\vmc-vscode\wp-content\plugins\vana-mission-control\templates\visit\parts\schedule.php",
     "templates/visit/parts/schedule.php"),
    (r"c:\Users\marce\Desktop\vanamadhuryamdaily\vmc-vscode\wp-content\plugins\vana-mission-control\templates\visit\parts\day-tabs.php",
     "templates/visit/parts/day-tabs.php"),
]

def main():
    transport = paramiko.Transport((HOST, PORT))
    transport.connect(username=USER, password=PASSWORD)
    sftp = paramiko.SFTPClient.from_transport(transport)

    for local, remote_rel in FILES:
        remote = f"{REMOTE_PLUGIN}/{remote_rel}"
        print(f"Uploading: {remote_rel}...", end=" ")
        sftp.put(local, remote)
        print("OK")

    sftp.close()
    
    # Verify uploads with grep
    client = paramiko.SSHClient()
    client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    client._transport = transport

    print("\n=== VERIFICATION ===")
    
    # Check bootstrap guard
    cmd = f"grep -n 'vana_make_event_key\\|vana_visit_url' {REMOTE_PLUGIN}/templates/visit/_bootstrap.php | head -3"
    _, stdout, _ = client.exec_command(cmd, timeout=60)
    print("Bootstrap guard lines:")
    print(stdout.read().decode("utf-8", errors="replace"))

    # Check schedule fallback
    cmd = f"grep -n 'Fallback defensivo' {REMOTE_PLUGIN}/templates/visit/parts/schedule.php"
    _, stdout, _ = client.exec_command(cmd, timeout=60)
    print("Schedule fallback lines:")
    print(stdout.read().decode("utf-8", errors="replace"))

    # Check day-tabs fallback
    cmd = f"grep -n 'Fallback defensivo' {REMOTE_PLUGIN}/templates/visit/parts/day-tabs.php"
    _, stdout, _ = client.exec_command(cmd, timeout=60)
    print("Day-tabs fallback lines:")
    print(stdout.read().decode("utf-8", errors="replace"))

    transport.close()


if __name__ == "__main__":
    main()
