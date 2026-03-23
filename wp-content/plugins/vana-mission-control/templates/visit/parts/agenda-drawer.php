<?php
/**
 * Agenda Drawer — Gaveta de agenda lateral
 * Template Part: templates/visit/parts/agenda-drawer.php
 *
 * Seção 10 do spec v1:
 * - Lista de eventos por dia
 * - Controle de idioma (PT/EN)
 * - Estados: vazio, carregando, populado
 *
 * Variáveis consumidas:
 *   $data       array  — timeline JSON (dias + eventos)
 *   $lang       string — 'pt' | 'en'
 *   $visit_id   int    — ID da visita
 *
 * JS Controller: VanaVisitController.js (Fase E)
 * Seletores críticos:
 *   #vana-agenda-drawer       → container (role=dialog)
 *   #vana-agenda-day-selector → tabs de dias
 *   #vana-agenda-event-list   → lista de eventos
 *   .vana-agenda-drawer__close → botão fechar
 */
defined('ABSPATH') || exit;
?>

<!-- ════════════════════════════════════════════════════════
     AGENDA DRAWER
     ════════════════════════════════════════════════════════ -->
<div
    id="vana-agenda-drawer"
    class="vana-drawer vana-drawer--agenda"
    data-vana-agenda-drawer
    role="dialog"
    aria-modal="true"
    aria-label="<?php echo esc_attr('Agenda de eventos'); ?>"
    hidden
>
    <div class="vana-drawer__header">
        <span class="vana-drawer__header-title">
            📅 <?php echo esc_html( vana_t( 'agenda.title', $lang ) ?: 'Agenda' ); ?>
        </span>
        <button class="vana-drawer__close" data-vana-agenda-close aria-label="Fechar">
            <svg width="14" height="14" viewBox="0 0 14 14" fill="none">
                <path d="M1 1L13 13M13 1L1 13" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
            </svg>
        </button>
    </div>

    <div class="vana-drawer__body">

        <!-- Day Selector dentro da agenda -->
        <?php
        $days = $data['days'] ?? [];
        if (!empty($days)):
        ?>
        <div class="vana-agenda-day-selector" id="vana-agenda-day-selector" role="tablist">
            <?php foreach ($days as $idx => $day): ?>
                <?php
                $day_date  = $day['date'] ?? '';
                $day_label = $day['label_' . $lang] ?? $day['label_pt'] ?? 'Dia ' . ($idx + 1);
                $is_first  = ($idx === 0);
                ?>
                <button
                    id="vana-agenda-day-tab-<?php echo esc_attr($idx); ?>"
                    role="tab"
                    class="vana-agenda-day-tab <?php echo $is_first ? 'vana-agenda-day-tab--active' : ''; ?>"
                    data-day-date="<?php echo esc_attr($day_date); ?>"
                    data-vana-day-tab="<?php echo esc_attr($day_date); ?>"
                    aria-selected="<?php echo $is_first ? 'true' : 'false'; ?>"
                    aria-controls="vana-agenda-day-<?php echo esc_attr($idx); ?>"
                >
                    <?php echo esc_html($day_label); ?>
                </button>
            <?php endforeach; ?>
        </div>

        <!-- Event List por dia -->
        <?php foreach ($days as $idx => $day): ?>
            <?php
            $events = $day['schedule'] ?? [];
            $is_first = ($idx === 0);
            ?>
            <div
                id="vana-agenda-day-<?php echo esc_attr($idx); ?>"
                role="tabpanel"
                class="vana-agenda-events <?php echo $is_first ? '' : 'hidden'; ?>"
                aria-labelledby="vana-agenda-day-tab-<?php echo esc_attr($idx); ?>"
            >
                <?php if (!empty($events)): ?>
                    <ul class="vana-agenda-event-list">
                        <?php foreach ($events as $event): ?>
                            <?php
                            if (!is_array($event)) {
                                continue;
                            }
                            $event_key = $event['event_key'] ?? '';
                            $event_title = $event['title_' . $lang] ?? $event['title_pt'] ?? '';
                            $event_time = $event['time_local'] ?? $event['time'] ?? '';
                            $event_status = $event['status'] ?? '';
                            ?>
                            <li class="vana-agenda-event-item">
                                <button
                                    type="button"
                                    class="vana-agenda-event-btn"
                                    data-event-key="<?php echo esc_attr($event_key); ?>"
                                    data-vana-event="<?php echo esc_attr($event_key); ?>"
                                    aria-label="<?php echo esc_attr($event_time . ' — ' . $event_title); ?>"
                                >
                                    <div class="vana-agenda-event-time">
                                        <strong><?php echo esc_html($event_time); ?></strong>
                                    </div>
                                    <div class="vana-agenda-event-title">
                                        <?php echo esc_html($event_title); ?>
                                        <?php if ($event_status === 'live'): ?>
                                            <span class="vana-agenda-event-badge" aria-label="ao vivo">🔴</span>
                                        <?php endif; ?>
                                    </div>
                                </button>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <div class="vana-agenda-empty" role="status">
                        <p><?php echo esc_html( vana_t( 'agenda.empty', $lang ) ?: 'Sem eventos para este dia' ); ?></p>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>

        <?php else: ?>
            <!-- Sem dias disponíveis -->
            <div class="vana-agenda-empty" role="status">
                <p><?php echo esc_html( vana_t( 'agenda.no_days', $lang ) ?: 'Nenhum dia disponível' ); ?></p>
            </div>
        <?php endif; ?>

    </div>
</div>

<div class="vana-drawer__overlay" id="vana-agenda-overlay" data-vana-agenda-overlay hidden></div>
