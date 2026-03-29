<?php
/**
 * Partial: Hero Day Selector
 * Seletor de dias da visita — navegação por abas acessíveis.
 *
 * Variáveis herdadas do hero-header.php:
 *   $tour  (array)   → dados do tour (inclui $tour['days'])
 *   $lang  (string)  → 'pt' | 'en'
 *
 * Estrutura esperada de $tour['days']:
 * [
 *   [
 *     'date'   => '2026-03-24',   // ISO 8601
 *     'label'  => ['pt' => 'Dia 1', 'en' => 'Day 1'],  // opcional
 *     'events' => [ ... ]
 *   ],
 *   ...
 * ]
 *
<?php
/**
 * _hero-day-selector.php — Schema 6.1
 * Ao clicar num dia → recarrega a página com ?day={day_key}
 */
defined( 'ABSPATH' ) || exit;

// Consome $days (do _bootstrap.php) ou $tour['days']
$_hds_days = is_array( $days ?? null ) ? $days : ( is_array( $tour['days'] ?? null ) ? $tour['days'] : [] );

if ( count( $_hds_days ) <= 1 ) return; // Regra: 1 dia = sem seletor

// Agrupa por mês (spec: virada de mês/ano)
$_hds_groups = [];
foreach ( $_hds_days as $i => $day ) {
    $ts  = strtotime( ( $day['day_key'] ?? $day['date_local'] ?? '' ) . ' 12:00:00' );
    $key = $ts ? date( 'Y-m', $ts ) : 'unknown';
    $_hds_groups[ $key ][] = [ 'index' => $i, 'day' => $day, 'ts' => $ts ];
}

// Detecta se há virada de ano entre grupos
$_hds_years = array_unique( array_map( fn($k) => substr($k, 0, 4), array_keys( $_hds_groups ) ) );
$_hds_multi_year = count( $_hds_years ) > 1;

// Day key ativo (GET param ou primeiro dia)
$_hds_active = sanitize_text_field( $_GET['day'] ?? '' );
if ( ! $_hds_active && ! empty( $_hds_days ) ) {
    $_hds_active = $_hds_days[0]['day_key'] ?? '';
}

// URL base para os links (preserva ?lang= e outros params, remove ?day=)
$_hds_base_url = remove_query_arg( 'day' );
?>

<div
    class="vana-day-selector"
    role="tablist"
    aria-label="<?php echo esc_attr( $lang === 'en' ? 'Select day' : 'Selecionar dia' ); ?>"
    data-vana-day-selector
>
    <?php foreach ( $_hds_groups as $month_key => $group_items ) :
        $ts_first = $group_items[0]['ts'];
        // Label do mês: só mês se mesmo ano, mês+ano se virada
        $month_label = $_hds_multi_year
            ? wp_date( 'M Y', $ts_first )     // "dez 2024"
            : wp_date( 'MMMM', $ts_first );   // "outubro"

        // Fallback: wp_date com 'F' dá nome completo, 'M' abreviado
        $month_label = wp_date( $_hds_multi_year ? 'M Y' : 'F', $ts_first );
    ?>

    <div class="vana-day-selector__group">
        <span class="vana-day-selector__month-label">
            <?php echo esc_html( $month_label ); ?>
        </span>

        <div class="vana-day-selector__pills" role="presentation">
            <?php foreach ( $group_items as $item ) :
                $day     = $item['day'];
                $dk      = (string) ( $day['day_key'] ?? '' );
                $ts      = $item['ts'];
                $is_act  = ( $dk === $_hds_active );

                // Hoje?
                $is_today = ( $ts && date( 'Y-m-d', $ts ) === date( 'Y-m-d' ) );

                // Texto do botão: dia numérico
                $day_num     = $ts ? wp_date( 'j', $ts )   : $dk;
                $weekday_lbl = $ts ? wp_date( 'D', $ts )   : '';

                // URL do dia
                $day_url = add_query_arg( 'day', $dk, $_hds_base_url );

                $btn_class = 'vana-day-selector__tab'
                           . ( $is_act   ? ' vana-day-selector__tab--active'  : '' )
                           . ( $is_today ? ' vana-day-selector__tab--today'   : '' );
            ?>
            <a
                href="<?php echo esc_url( $day_url ); ?>"
                class="<?php echo esc_attr( $btn_class ); ?>"
                role="tab"
                aria-selected="<?php echo $is_act ? 'true' : 'false'; ?>"
                data-day-key="<?php echo esc_attr( $dk ); ?>"
                aria-label="<?php echo esc_attr(
                    $weekday_lbl . ' ' . $day_num
                    . ( $is_today ? ( $lang === 'en' ? ' — Today' : ' — Hoje' ) : '' )
                ); ?>"
            >
                <span class="vana-day-selector__weekday" aria-hidden="true">
                    <?php echo esc_html( $weekday_lbl ); ?>
                </span>
                <span class="vana-day-selector__num">
                    <?php echo $is_today ? '●' : ''; ?><?php echo esc_html( $day_num ); ?>
                </span>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<?php unset( $_hds_days, $_hds_groups, $_hds_years, $_hds_multi_year,
             $_hds_active, $_hds_base_url, $group_items, $item,
             $day, $dk, $ts, $is_act, $is_today, $day_num,
             $weekday_lbl, $day_url, $btn_class, $month_key,
             $ts_first, $month_label ); ?>
    <div
        id="<?php echo esc_attr($panel_id); ?>"
        class="vana-day-selector__panel<?php echo $is_active ? ' vana-day-selector__panel--active' : ''; ?>"
        role="tabpanel"
        aria-labelledby="<?php echo esc_attr($tab_id); ?>"
        <?php echo !$is_active ? 'hidden' : ''; ?>
    >
        <?php if (empty($events)) : ?>
            <p class="vana-day__empty">
                <?php echo esc_html(Vana_Utils::t('day.empty', $lang)); ?>
            </p>
        <?php else : ?>
            <?php include __DIR__ . '/_hero-events.php'; ?>
        <?php endif; ?>
    </div><!-- /.vana-day-selector__panel -->
    <?php endforeach; ?>

</div><!-- /.vana-day-selector -->
