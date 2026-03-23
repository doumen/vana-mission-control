import subprocess
import sys

result = subprocess.run([sys.executable, 'deploy_smart_git.py'], 
                       input='s\n', 
                       text=True,
                       cwd=r'C:\Users\marce\Desktop\vanamadhuryamdaily\vmc-vscode\beta')
sys.exit(result.returncode)
