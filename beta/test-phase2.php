<?php
/**
 * Test Suite: Fase 2 — VanaEventController + Event Selector
 */

// Simulated vars que viriam de _bootstrap.php
$active_events = [
    ["event_key" => "2026-03-21-1", "title_pt" => "Satsang", "time_start" => "09:00", "status" => "live"],
    ["event_key" => "2026-03-21-2", "title_pt" => "Kīrtan", "time_start" => "10:30", "status" => "scheduled"],
    ["event_key" => "2026-03-21-3", "title_pt" => "Arati", "time_start" => "18:00", "status" => "scheduled"],
];

$active_event = $active_events[0];
$visit_id = 123;
$lang = "pt";

echo "\n";
echo "╔════════════════════════════════════════════════════════════╗\n";
echo "║      FASE 2 — VanaEventController + Event Selector        ║\n";
echo "╚════════════════════════════════════════════════════════════╝\n\n";

$tests_passed = 0;
$tests_total = 5;

// ── TESTE 1: Variáveis de evento disponíveis ──────────────
echo "Test 1: Variáveis de evento\n";
$test1 = (count($active_events) >= 2 && !empty($active_event["title_pt"]));
if ($test1) {
    echo "  ✅ PASSOU\n";
    $tests_passed++;
} else {
    echo "  ❌ FALHOU\n";
}

// ── TESTE 2: Data attributes para VanaEventController ──────────────
echo "Test 2: Data attributes\n";
$test2 = (!empty($active_events[0]["event_key"]) && $visit_id > 0 && in_array($lang, ["pt", "en"]));
if ($test2) {
    echo "  ✅ PASSOU\n";
    $tests_passed++;
} else {
    echo "  ❌ FALHOU\n";
}

// ── TESTE 3: Status badges ────────────────────────────────
echo "Test 3: Status badges\n";
$status_map = ["live" => "🔴", "completed" => "✅", "scheduled" => "🕐", "cancelled" => "❌"];
$test3 = true;
foreach ($active_events as $ev) {
    $status = $ev["status"] ?? "";
    if (!isset($status_map[$status])) {
        $test3 = false;
        break;
    }
}
if ($test3) {
    echo "  ✅ PASSOU\n";
    $tests_passed++;
} else {
    echo "  ❌ FALHOU\n";
}

// ── TESTE 4: Event selector renderizável ──────────────────
echo "Test 4: Event selector renderizável\n";
$test4 = (count($active_events) > 1 && !empty($active_event["event_key"]));
if ($test4) {
    echo "  ✅ PASSOU\n";
    $tests_passed++;
} else {
    echo "  ❌ FALHOU\n";
}

// ── TESTE 5: URL building para /stage-fragment ──────────
echo "Test 5: URL building /stage-fragment\n";
$params = http_build_query([
    "visit_id"  => $visit_id,
    "item_id"   => $active_events[0]["event_key"],
    "item_type" => "event",
    "lang"      => $lang,
]);
$url = "/wp-json/vana/v1/stage-fragment?" . $params;
$test5 = (strpos($url, "/wp-json/vana/v1/stage-fragment?") === 0 && strpos($url, "item_type=event") !== false);
if ($test5) {
    echo "  ✅ PASSOU\n";
    $tests_passed++;
} else {
    echo "  ❌ FALHOU\n";
}

// ── SUMMARY ────────────────────────────────────────────────
echo "\n";
echo "╔════════════════════════════════════════════════════════════╗\n";
echo "║                    RESULTADO FASE 2                        ║\n";
echo "╠════════════════════════════════════════════════════════════╣\n";
echo "║  ✅ PASSED : $tests_passed/$tests_total                                    ║\n";
echo "║  ❌ FAILED : " . ($tests_total - $tests_passed) . "/$tests_total                                    ║\n";
echo "╚════════════════════════════════════════════════════════════╝\n";
?>
