#!/usr/bin/env python3
import paramiko
import sys

HOST = "149.62.37.117"
PORT = 65002
USER = "u419701790"
PASSWORD = "Mga@4455"
REMOTE_DIR = "/home/u419701790/domains/vanamadhuryamdaily.com/public_html/beta_html"
TEST_FILE = "inspect-event1.php"
LOCAL_FILE = r"c:\Users\marce\Desktop\vanamadhuryamdaily\vmc-vscode\beta\inspect-event1.php"


def main():
    transport = paramiko.Transport((HOST, PORT))
    try:
        transport.connect(username=USER, password=PASSWORD)
    except Exception as e:
        print(f"CONNECT_ERROR: {e}")
        sys.exit(1)

    sftp = paramiko.SFTPClient.from_transport(transport)
    remote_path = f"{REMOTE_DIR}/{TEST_FILE}"
    sftp.put(LOCAL_FILE, remote_path)
    sftp.close()

    client = paramiko.SSHClient()
    client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    client._transport = transport

    cmd = f"cd {REMOTE_DIR} && wp eval-file {TEST_FILE} 2>&1"
    stdin, stdout, stderr = client.exec_command(cmd, timeout=120)
    output = stdout.read().decode("utf-8", errors="replace")
    errors = stderr.read().decode("utf-8", errors="replace")
    print(output)
    if errors:
        print("--- STDERR ---")
        print(errors)

    transport.close()


if __name__ == "__main__":
    main()
