#!/usr/bin/env python3
import paramiko

HOST = "149.62.37.117"
PORT = 65002
USER = "u419701790"
PASSWORD = "Mga@4455"

BASE = "/home/u419701790/domains/vanamadhuryamdaily.com/public_html/beta_html/wp-content/plugins/vana-mission-control/templates"

CMDS = [
    (
        "1) WHO REQUIRES _bootstrap_shim.php",
        "grep -rn \"_bootstrap_shim\\|bootstrap_shim\" " + BASE + " 2>/dev/null | grep -v \".bak\"",
    ),
    (
        "2) WHO REQUIRES _bootstrap.php",
        "grep -rn \"_bootstrap\\b\\|require.*_bootstrap\\|include.*_bootstrap\" " + BASE + " 2>/dev/null | grep -v \".bak\"",
    ),
    (
        "3) FULL templates/visit/_bootstrap.php",
        "cat " + BASE + "/visit/_bootstrap.php",
    ),
    (
        "4) FIRST 30 LINES templates/visit/visit-template.php",
        "sed -n '1,30p' " + BASE + "/visit/visit-template.php",
    ),
]


def main():
    ssh = paramiko.SSHClient()
    ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    ssh.connect(HOST, port=PORT, username=USER, password=PASSWORD, timeout=30)

    try:
        for title, cmd in CMDS:
            print("\n" + "=" * 80)
            print(title)
            print("=" * 80)
            _, stdout, stderr = ssh.exec_command(cmd, timeout=120)
            out = stdout.read().decode("utf-8", errors="replace")
            err = stderr.read().decode("utf-8", errors="replace")
            print(out if out else "(sem stdout)")
            if err:
                print("--- STDERR ---")
                print(err)
    finally:
        ssh.close()


if __name__ == "__main__":
    main()
