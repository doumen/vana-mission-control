#!/usr/bin/env python3
import paramiko

HOST = "149.62.37.117"
PORT = 65002
USER = "u419701790"
PASSWORD = "Mga@4455"

WP_ROOT = "/home/u419701790/domains/vanamadhuryamdaily.com/public_html/beta_html"
PLUGIN_BOOTSTRAP = WP_ROOT + "/wp-content/plugins/vana-mission-control/templates/visit/_bootstrap.php"

CMDS = [
    (
        "1) tail debug.log",
        f"tail -50 {WP_ROOT}/wp-content/debug.log",
    ),
    (
        "2) tail server error_log fallback",
        "tail -50 /home/u419701790/logs/vanamadhuryamdaily.com/error_log 2>/dev/null || "
        "tail -50 /home/u419701790/domains/vanamadhuryamdaily.com/logs/error_log 2>/dev/null",
    ),
    (
        "3) grep WP_DEBUG in wp-config.php",
        f"grep -n \"WP_DEBUG\\|SCRIPT_DEBUG\\|SAVEQUERIES\" {WP_ROOT}/wp-config.php",
    ),
    (
        "4) grep critical bootstrap lines",
        f"grep -n \"resolve\\|extract\\|VisitStageResolver\\|to_template_vars\\|vana_vm\" {PLUGIN_BOOTSTRAP}",
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
