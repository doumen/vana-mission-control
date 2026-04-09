<?php if ( ! empty( $days ) ) : ?>

<div class="vana-hero__day-strip" role="group"
     aria-label="<?php echo esc_attr( $is_en ? 'Days of this visit' : 'Dias desta visita' ); ?>">

    <div class="vana-hero__day-pills">
        <?php foreach ( $days as $day ) :
            $dk        = $day['day_key'] ?? '';
            $label     = $is_en ? ( $day['label_en'] ?? $dk ) : ( $day['label_pt'] ?? $dk );
            $is_active = ( $dk === ( $active_day['day_key'] ?? '' ) );
            $has_hk    = ! empty( $day['_has_hk'] );
            $has_live  = ! empty( $day['_has_live'] );
        ?>
        <button
            class="vana-day-pill <?php echo $is_active ? 'is-active' : ''; ?>"
            data-action="open-agenda-day"
            data-day-key="<?php echo esc_attr( $dk ); ?>"
            aria-label="<?php echo esc_attr( $label ); ?>"
            aria-pressed="<?php echo $is_active ? 'true' : 'false'; ?>"
        >
            <?php echo esc_html( $label ); ?>
            <?php if ( $has_live ) : ?>
                <span class="vana-day-pill__dot vana-day-pill__dot--live" aria-hidden="true"></span>
            <?php elseif ( $has_hk ) : ?>
                <span class="vana-day-pill__dot vana-day-pill__dot--hk" aria-hidden="true"></span>
            <?php endif; ?>
        </button>
        <?php endforeach; ?>
    </div>

    <?php
    $active_tithi = $is_en
        ? ( $active_day['tithi_name_en'] ?? '' )
        : ( $active_day['tithi_name_pt'] ?? '' );
    if ( $active_tithi ) :
    ?>
    <p class="vana-hero__day-tithi">🌙 <?php echo esc_html( $active_tithi ); ?></p>
    <?php endif; ?>

</div>

<?php endif; ?>
