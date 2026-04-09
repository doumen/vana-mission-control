<?php
/**
 * Hero Day Strip — spec v3.2 §4
 * Apresentação dos dias da visita, agrupados por mês.
 * SEM navegação própria — clique delega à Agenda.
 */

if ( empty( $days ) || count( $days ) <= 1 ) return; // CASO 4

// Agrupa dias por mês (e ano, se virada de ano)
$groups = [];
foreach ( $days as $day ) {
    $dk = $day['day_key'] ?? ''; // YYYY-MM-DD
    if ( ! $dk ) continue;

    list( $y, $m, $d ) = explode( '-', $dk );

    $group_key = $y . '-' . $m; // ex: "2026-02"
    $groups[ $group_key ]['year']  = $y;
    $groups[ $group_key ]['month'] = $m;
    $groups[ $group_key ]['days'][] = $day;
}

// Detecta virada de ano
$years = array_unique( array_map( fn( $g ) => $g['year'], $groups ) );
$show_year = count( $years ) > 1;

// Meses em PT e EN
$month_names = [
    'pt' => ['01'=>'jan','02'=>'fev','03'=>'mar','04'=>'abr',
             '05'=>'mai','06'=>'jun','07'=>'jul','08'=>'ago',
             '09'=>'set','10'=>'out','11'=>'nov','12'=>'dez'],
    'en' => ['01'=>'jan','02'=>'feb','03'=>'mar','04'=>'apr',
             '05'=>'may','06'=>'jun','07'=>'jul','08'=>'aug',
             '09'=>'sep','10'=>'oct','11'=>'nov','12'=>'dec'],
];
$lang_key = $is_en ? 'en' : 'pt';

// Dia de hoje para CASO 5
$today = current_time( 'Y-m-d' );
?>

<div class="vana-hero__day-strip" role="group"
     aria-label="<?php echo esc_attr( $is_en ? 'Days of this visit' : 'Dias desta visita' ); ?>">

    <?php foreach ( $groups as $gk => $group ) :
        $m_label = $month_names[ $lang_key ][ $group['month'] ] ?? $group['month'];
        $label   = $show_year
            ? $m_label . ' ' . $group['year']
            : $m_label;
    ?>

    <div class="vana-hero__day-group">

        <!-- Mês (e ano se virada de ano) -->
        <span class="vana-hero__day-month">
            <?php echo esc_html( $label ); ?>
        </span>

        <!-- Pills dos dias -->
        <div class="vana-hero__day-pills">
            <?php foreach ( $group['days'] as $day ) :
                $dk        = $day['day_key'] ?? '';
                $d_num     = ltrim( explode( '-', $dk )[2] ?? '', '0' ) ?: '1';
                $full_label = $is_en
                    ? ( $day['label_en'] ?? $dk )
                    : ( $day['label_pt'] ?? $dk );

                $is_active  = ( $dk === ( $active_day['day_key'] ?? '' ) );
                $is_today   = ( $dk === $today ); // CASO 5
                $has_hk     = ! empty( $day['_has_hk'] );
                $has_live   = ! empty( $day['_has_live'] );
            ?>
            <button
                class="vana-day-pill <?php echo $is_active ? 'is-active' : ''; ?> <?php echo $is_today ? 'is-today' : ''; ?>"
                data-action="open-agenda-day"
                data-day-key="<?php echo esc_attr( $dk ); ?>"
                aria-label="<?php echo esc_attr( $full_label ); ?>"
                aria-pressed="<?php echo $is_active ? 'true' : 'false'; ?>"
            >
                <?php if ( $is_today ) : ?>
                    <span class="vana-day-pill__today" aria-hidden="true">●</span>
                <?php endif; ?>

                <?php echo esc_html( $d_num ); ?>

                <?php if ( $has_live ) : ?>
                    <span class="vana-day-pill__dot vana-day-pill__dot--live" aria-hidden="true"></span>
                <?php elseif ( $has_hk ) : ?>
                    <span class="vana-day-pill__dot vana-day-pill__dot--hk" aria-hidden="true"></span>
                <?php endif; ?>
            </button>
            <?php endforeach; ?>
        </div>

    </div><!-- /day-group -->
    <?php endforeach; ?>

    <!-- Tithi do dia ativo -->
    <?php
    $active_tithi = $is_en
        ? ( $active_day['tithi_name_en'] ?? '' )
        : ( $active_day['tithi_name_pt'] ?? '' );
    if ( $active_tithi ) :
    ?>
    <p class="vana-hero__day-tithi">🌙 <?php echo esc_html( $active_tithi ); ?></p>
    <?php endif; ?>

</div>

