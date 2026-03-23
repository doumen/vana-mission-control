#!/usr/bin/env python3
import paramiko

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect('149.62.37.117', port=65002, username='u419701790', password='Mga@4455', timeout=30)

# Create a simple test script on the server
test_script = r"""<?php
// Test script to check post 359 rendering
require_once '/home/u419701790/domains/vanamadhuryamdaily.com/public_html/beta_html/wp-load.php';

echo "=== POST 359 TEST ===\n";
echo "WP Loaded\n";

$post = get_post(359);
if ($post) {
    echo "Post found: " . $post->post_title . "\n";
    echo "Post type: " . $post->post_type . "\n";
    echo "Post status: " . $post->post_status . "\n";
} else {
    echo "Post NOT found\n";
}

// Check if CPT is registered
$post_type_obj = get_post_type_object('vana_visit');
if ($post_type_obj) {
    echo "CPT registered: vana_visit\n";
    echo "  public: " . ($post_type_obj->public ? 'yes' : 'no') . "\n";
} else {
    echo "CPT NOT registered\n";
}

// Try to get the template
$template = get_singular_template();
echo "Template returned: $template\n";

// Try applying template_include filter directly
$template_include_func = function($template) {
    if (is_singular("vana_visit")) {
        $t = '/home/u419701790/domains/vanamadhuryamdaily.com/public_html/beta_html/wp-content/plugins/vana-mission-control/templates/single-vana_visit.php';
        if (file_exists($t)) {
            return $t;
        }
    }
    return $template;
};

$new_template = call_user_func($template_include_func, $template);
echo "After filter: " . ($new_template === $template ? 'unchanged' : 'changed') . "\n";
if (file_exists($new_template)) {
    echo "Template file exists: $new_template\n";
} else {
    echo "Template file NOT found: $new_template\n";
}
?>"""

# Write to server
remote_script = '/tmp/test-post-359.php'
cmd = f"cat > {remote_script} << 'ENDSCRIPT'\n{test_script}\nENDSCRIPT"
ssh.exec_command(cmd, timeout=30)

print("Testing post 359 rendering...")

cmd_run = f"php {remote_script} 2>&1"
_, stdout, _ = ssh.exec_command(cmd_run, timeout=30)
out = stdout.read().decode('utf-8', 'ignore')
print(out)

ssh.close()
