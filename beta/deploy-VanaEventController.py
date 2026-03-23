#!/usr/bin/env python3
import paramiko
import sys

HOST = "149.62.37.117"
PORT = 65002
USER = "u419701790"
PASSWORD = "Mga@4455"
REMOTE_PLUGIN = "/home/u419701790/domains/vanamadhuryamdaily.com/public_html/beta_html/wp-content/plugins/vana-mission-control"
LOCAL_JS = r"c:\Users\marce\Desktop\vanamadhuryamdaily\vmc-vscode\wp-content\plugins\vana-mission-control\assets\js\VanaEventController.js"


def main():
    transport = paramiko.Transport((HOST, PORT))
    try:
        transport.connect(username=USER, password=PASSWORD)
    except Exception as e:
        print(f"CONNECT_ERROR: {e}")
        sys.exit(1)

    sftp = paramiko.SFTPClient.from_transport(transport)
    remote_path = f"{REMOTE_PLUGIN}/assets/js/VanaEventController.js"
    sftp.put(LOCAL_JS, remote_path)
    sftp.close()
    print(f"UPLOAD OK: {remote_path}")
    transport.close()


if __name__ == "__main__":
    main()
