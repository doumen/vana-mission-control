<?php
$visit_id = 353;
$event_key = 'vrindavan-353-a';

// Novo endpoint v2
$req_new = new WP_REST_Request('GET', '/vana/v1/stage/' . $event_key);
$req_new->set_param('visit_id', $visit_id);
$req_new->set_param('lang', 'pt');
$res_new = rest_do_request($req_new);

echo "NEW status: " . $res_new->get_status() . "\n";
echo "NEW x-vana-endpoint: " . ($res_new->get_headers()['X-Vana-Endpoint'] ?? 'N/A') . "\n";
$body_new = (string) $res_new->get_data();
echo "NEW html length: " . strlen($body_new) . "\n";
echo "NEW preview: " . substr($body_new, 0, 120) . "\n\n";

// Endpoint legado (event)
$req_old = new WP_REST_Request('GET', '/vana/v1/stage-fragment');
$req_old->set_param('visit_id', $visit_id);
$req_old->set_param('item_id', $event_key);
$req_old->set_param('item_type', 'event');
$req_old->set_param('lang', 'pt');
$res_old = rest_do_request($req_old);

echo "OLD status: " . $res_old->get_status() . "\n";
echo "OLD x-vana-fragment: " . ($res_old->get_headers()['X-Vana-Fragment'] ?? 'N/A') . "\n";
$body_old = (string) $res_old->get_data();
echo "OLD html length: " . strlen($body_old) . "\n";
echo "OLD preview: " . substr($body_old, 0, 120) . "\n";
