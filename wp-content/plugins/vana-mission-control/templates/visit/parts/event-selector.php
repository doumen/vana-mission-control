<?php
/**
 * Partial: Event Selector
 * Arquivo: templates/visit/parts/event-selector.php
 *
 * Renderiza os botões de seleção de evento do dia ativo.
 * Cada botão dispara VanaEventController.js via [data-vana-event-key].
 *
 * Variáveis esperadas (do _bootstrap.php):
 *   $visit_id        int
 *   $active_events   array   Eventos do dia ativo
 *   $active_event    array   Evento atualmente ativo
 *   $lang            string  'pt'|'en'
 *
 * @since 5.2.0
 */
defined( 'ABSPATH' ) || exit;

// Guard: sem eventos, sem seletor
if ( empty( $active_events ) || ! is_array( $active_events ) ) {
    return;
}

// Só renderiza seletor se houver mais de 1 evento no dia
if ( count( $active_events ) < 2 ) {
    return;
}

// Event key ativo (para aria-current e classe ativa no SSR)
$active_key = (string) ( $active_event['event_key'] ?? $active_event['key'] ?? '' );
?>

<nav
    class="vana-event-selector"
    aria-label="<?php echo esc_attr( vana_t( 'event_selector.aria', $lang ) ); ?>"
    role="tablist"
>
  <div class="vana-event-selector__list">

    <?php foreach ( $active_events as $ev ) : 
        if ( ! is_array( $ev ) ) continue;

        $ev_key    = (string) ( $ev['event_key'] ?? $ev['key'] ?? '' );
        $ev_title  = Vana_Utils::pick_i18n_key( $ev, 'title', $lang );
        $ev_time   = (string) ( $ev['time_start'] ?? '' );
        $ev_status = (string) ( $ev['status']     ?? '' );
        $is_active = ( $ev_key === $active_key );

        // Badge de status
        $badge_map = [
            'live'      => [ 'label' => '🔴 '  . vana_t( 'status.live', $lang ),      'class' => 'badge--live' ],
            'completed' => [ 'label' => '✅ '  . vana_t( 'status.completed', $lang ),  'class' => 'badge--done' ],
            'scheduled' => [ 'label' => '🕐 '  . vana_t( 'status.scheduled', $lang ),  'class' => 'badge--soon' ],
            'cancelled' => [ 'label' => '❌ '  . vana_t( 'status.cancelled', $lang ),  'class' => 'badge--off'  ],
        ];
        $badge = $badge_map[ $ev_status ] ?? null;
    ?>

      <button
          type="button"
          class="vana-event-btn<?php echo $is_active ? ' vana-event-btn--active' : ''; ?>"
          role="tab"
          aria-selected="<?php echo $is_active ? 'true' : 'false'; ?>"
          aria-current="<?php echo $is_active ? 'true' : 'false'; ?>"
          data-vana-event-key="<?php echo esc_attr( $ev_key ); ?>"
          data-vana-visit-id="<?php echo esc_attr( $visit_id ); ?>"
          data-vana-lang="<?php echo esc_attr( $lang ); ?>"
      >

        <?php if ( $ev_time ) : ?>
          <span class="vana-event-btn__time">
            <?php echo esc_html( $ev_time ); ?>
          </span>
        <?php endif; ?>

        <span class="vana-event-btn__title">
          <?php echo esc_html( $ev_title ?: vana_t( 'event_selector.unnamed', $lang ) ); ?>
        </span>

        <?php if ( $badge ) : ?>
          <span class="vana-event-btn__badge <?php echo esc_attr( $badge['class'] ); ?>">
            <?php echo esc_html( $badge['label'] ); ?>
          </span>
        <?php endif; ?>

      </button>

    <?php endforeach; ?>

  </div>
</nav>