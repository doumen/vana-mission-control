#!/usr/bin/env python3
import paramiko

client = paramiko.SSHClient()
client.set_missing_host_key_policy(paramiko.AutoAddPolicy())

try:
    client.connect('149.62.37.117', port=65002, username='u419701790', password='Mga@4455', timeout=10)
    
    # Find the post with slug "dia-1-vrindavan"
    stdin, stdout, stderr = client.exec_command(
        '''
        cd /home/u419701790/domains/vanamadhuryamdaily.com/public_html/beta_html && \
        wp post list --post_type=vana_visit --name='dia-1-vrindavan' --field=ID,title,name 2>/dev/null
        '''
    )
    
    output = stdout.read().decode().strip()
    print("Found post:")
    print(output)
    
    # Extract ID from first column
    if output:
        post_id = output.split()[0]
        
        # Now get its timeline meta
        stdin2, stdout2, stderr2 = client.exec_command(
            f'''
            cd /home/u419701790/domains/vanamadhuryamdaily.com/public_html/beta_html && \
            wp post meta get {post_id} _vana_visit_timeline_json 2>/dev/null | head -500
            '''
        )
        
        timeline = stdout2.read().decode().strip()
        if timeline and timeline != '':
            print(f"\nTimeline for post {post_id}:")
            # Parse and pretty print
            import json
            try:
                data = json.loads(timeline)
                print(f"Schema: {data.get('schema_version')}")
                print(f"Days count: {len(data.get('days', []))}")
                if 'days' in data:
                    print(f"First day date: {data['days'][0].get('date_local') if data['days'] else 'N/A'}")
            except json.JSONDecodeError as e:
                print(f"JSON parse error: {e}")
                print(f"First 500 chars: {timeline[:500]}")
        else:
            print(f"\nNo _vana_visit_timeline_json for post {post_id}")
            # Check legacy _vana_visit_data
            stdin3, stdout3, stderr3 = client.exec_command(
                f'''
                cd /home/u419701790/domains/vanamadhuryamdaily.com/public_html/beta_html && \
                wp post meta get {post_id} _vana_visit_data 2>/dev/null | head -500
                '''
            )
            legacy = stdout3.read().decode().strip()
            if legacy:
                print(f"\nFound legacy _vana_visit_data")
                print(f"First 500 chars: {legacy[:500]}")
    
except Exception as e:
    print(f"Error: {e}")
finally:
    client.close()
