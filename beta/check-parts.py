import os

base = r'c:\Users\marce\Desktop\vanamadhuryamdaily\vmc-vscode\wp-content\plugins\vana-mission-control\templates\visit\parts'

parts = [
    'hero-header.php',
    'day-tabs.php',
    'stage.php',
    'schedule.php',
    'hari-katha.php',
]

print("Status dos parts:\n")
for part in parts:
    path = os.path.join(base, part)
    if os.path.exists(path):
        size = os.path.getsize(path)
        status = "✓" if size > 100 else "⚠️ VAZIO"
        print(f"  {status} {part} ({size} bytes)")
    else:
        print(f"  ✗ {part} (NÃO ENCONTRADO)")
