<?php
add_action('wp_enqueue_scripts', function () {
  // carrega o CSS do tema pai
  wp_enqueue_style(
    'astra-parent',
    get_template_directory_uri() . '/style.css',
    [],
    wp_get_theme(get_template())->get('Version')
  );

  // (opcional) CSS do child (este style.css) se você quiser adicionar regras
  wp_enqueue_style(
    'astra-child',
    get_stylesheet_uri(),
    ['astra-parent'],
    wp_get_theme()->get('Version')
  );
}, 20);
/**
 * VANA MISSION CONTROL: Bússola Cronológica
 * Retorna um array de posts ordenados pela data real contida no JSON.
 */
function vana_get_chronological_visits() {
    $cache_key = 'vana_chronological_sequence';
    $sequence = get_transient($cache_key);

    if (false === $sequence) {
        $posts = get_posts([
            'post_type'      => 'vana_visit',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
        ]);

        $temp_map = [];
        foreach ($posts as $p) {
            $raw = get_post_meta($p->ID, '_vana_visit_timeline_json', true);
            $data = is_string($raw) ? json_decode($raw, true) : null;
            
            // Pega a primeira data do cronograma daquela visita
            $date = $data['days'][0]['date_local'] ?? get_post_field('post_date', $p->ID);
            $temp_map[] = [
                'id'   => $p->ID,
                'date' => $date
            ];
        }

        // Ordena por data real (texto: YYYY-MM-DD)
        usort($temp_map, fn($a, $b) => strcmp($a['date'], $b['date']));
        
        $sequence = $temp_map;
        set_transient($cache_key, $sequence, HOUR_IN_SECONDS);
    }
    return $sequence;
}

/**
 * HOME INTELIGENTE: Redireciona para Hoje ou para a mais próxima que já passou.
 */
add_action('template_redirect', function() {
    if (!is_front_page() && !is_home()) return;

    $sequence = vana_get_chronological_visits();
    if (empty($sequence)) return;

    $today = wp_date('Y-m-d');
    $target_id = 0;

    // Procura o melhor match
    foreach ($sequence as $item) {
        if ($item['date'] <= $today) {
            $target_id = $item['id']; // Vai atualizando até encontrar a última que passou ou a de hoje
        }
    }

    // Se a missão ainda nem começou (todas as datas são futuras), pega a primeira do futuro
    if ($target_id === 0) {
        $target_id = $sequence[0]['id'];
    }

    if ($target_id) {
        $url = get_permalink($target_id);
        if (isset($_GET['lang'])) $url = add_query_arg('lang', $_GET['lang'], $url);
        
        // Se a visita que encontrámos tem HOJE no cronograma, forçamos a aba
        $url = add_query_arg('v_day', $today, $url);

        wp_redirect($url);
        exit;
    }
}, 5);

/**
 * RECEPTOR VANA MISSION CONTROL
 * Adiciona a rota: /wp-json/vana/v1/schedule-live-update
 */
add_action('rest_api_init', function () {
    register_rest_route('vana/v1', '/schedule-live-update', array(
        'methods' => 'POST',
        'callback' => 'vana_handle_live_update',
        'permission_callback' => '__return_true', // Validaremos via HMAC
    ));
});

function vana_handle_live_update(WP_REST_Request $request) {
    // 1. CONFIGURAÇÃO (Ajuste a senha igual ao .env do Bot)
    $secret = '3708fe96095c12b3e45e2461b26178e6a19e9f62e5f8667db829dd2dc5ae5860'; 

    // 2. VALIDAÇÃO DE SEGURANÇA (HMAC)
    $sig = $request->get_header('X-Vana-Signature');
    $ts = $request->get_header('X-Vana-Timestamp');
    $body = $request->get_body();

    if (!$sig || !$ts) {
        return new WP_Error('no_auth', 'Faltam headers de segurança', array('status' => 401));
    }

    $expected_sig = hash_hmac('sha256', $ts . '.' . $body, $secret);

    if (!hash_equals($expected_sig, $sig)) {
        return new WP_Error('forbidden', 'Assinatura HMAC inválida', array('status' => 403));
    }

    // 3. PROCESSAMENTO DOS DADOS
    $params = json_decode($body, true);
    
    // Aqui o WordPress recebeu: visit_id, action, value, etc.
    // Por enquanto, vamos apenas confirmar que chegou!
    
    return array(
        'success' => true, 
        'message' => 'O WordPress recebeu seu comando!',
        'received' => $params['action']
    );
}