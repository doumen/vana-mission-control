#!/usr/bin/env python3
import paramiko

client = paramiko.SSHClient()
client.set_missing_host_key_policy(paramiko.AutoAddPolicy())

try:
    client.connect('149.62.37.117', port=65002, username='u419701790', password='Mga@4455', timeout=10)
    
    sftp = client.open_sftp()
    
    # Read the file
    with sftp.file('/home/u419701790/domains/vanamadhuryamdaily.com/public_html/beta_html/wp-content/plugins/vana-mission-control/templates/visit/visit-template.php', 'r') as f:
        content = f.read().decode('utf-8')
        
    # Check if our debug script is there
    if 'visit-template] Days available' in content:
        print("✓ Debug script IS in visit-template.php")
        # Show the section
        start = content.find('[visit-template] Days available')
        print(f"\nFound at position {start}:")
        print(content[max(0, start-100):start+200])
    else:
        print("✗ Debug script NOT found in visit-template.php")
        
    # Also check for our error_log calls
    if 'error_log' in content and 'About to include agenda drawer' in content:
        print("\n✓ Error_log calls ARE in visit-template.php")
    else:
        print("\n✗ Error_log calls NOT found in visit-template.php")
    
    sftp.close()
    
except Exception as e:
    print(f"Error: {e}")
finally:
    client.close()
