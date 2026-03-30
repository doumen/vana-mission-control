<?php
/**
 * _hero-day-selector.php — Schema 6.1
 * Seletor de dias no Hero. Ao clicar → reload com ?day={day_key} (SSR nativo).
 *
 * Variáveis consumidas:
 *   $days   array   — do _bootstrap.php
 *   $lang   string  — 'pt' | 'en'
 */
defined( 'ABSPATH' ) || exit;

// DEBUG TEMPORÁRIO — remover após confirmar
if ( defined('WP_DEBUG') && WP_DEBUG ) {
    $__dbg_first = $_hds_days[0] ?? [];
    error_log( '[HDS-DEBUG] keys do day[0]: ' . implode( ', ', array_keys( $__dbg_first ) ) );
    error_log( '[HDS-DEBUG] day[0] day_key: "' . ( $__dbg_first['day_key'] ?? 'AUSENTE' ) . '"' );
    error_log( '[HDS-DEBUG] day[0] date_local: "' . ( $__dbg_first['date_local'] ?? 'AUSENTE' ) . '"' );
    error_log( '[HDS-DEBUG] day[0] date: "' . ( $__dbg_first['date'] ?? 'AUSENTE' ) . '"' );
    unset( $__dbg_first );
}


// ── Fonte de dados ────────────────────────────────────────────────────────────
$_hds_days = is_array( $days ?? null )
    ? $days
    : ( is_array( $tour['days'] ?? null ) ? $tour['days'] : [] );

// Regra: 1 dia ou nenhum → sem seletor
if ( count( $_hds_days ) <= 1 ) return;

// ── Agrupa por mês (suporte à virada de mês/ano) ──────────────────────────────
$_hds_groups = [];
foreach ( $_hds_days as $i => $day ) {
    $_hds_raw_date = $day['day_key'] ?? $day['date_local'] ?? $day['date'] ?? '';
    $ts = $_hds_raw_date ? strtotime( $_hds_raw_date . ' 12:00:00' ) : 0;
    $key = $ts ? date( 'Y-m', $ts ) : 'unknown';
    $_hds_groups[ $key ][] = [ 'index' => $i, 'day' => $day, 'ts' => $ts ];
}

// Detecta virada de ano
$_hds_years      = array_unique( array_map( fn( $k ) => substr( $k, 0, 4 ), array_keys( $_hds_groups ) ) );
$_hds_multi_year = count( $_hds_years ) > 1;

// Dia ativo: GET param → primeiro dia
$_hds_active = sanitize_text_field( $_GET['v_day'] ?? '' );
if ( ! $_hds_active && ! empty( $_hds_days ) ) {
    $_hds_raw_date = $_hds_days[0]['day_key']    ??
                     $_hds_days[0]['date_local'] ??
                     $_hds_days[0]['date']       ?? '';
    $_hds_active = $_hds_raw_date;
}

// URL base (preserva ?lang= etc, remove ?day=)
$_hds_base_url = remove_query_arg( 'v_day' );
?>

<div
    class="vana-day-selector"
    role="tablist"
    data-vana-day-selector
    aria-label="<?php echo esc_attr( $lang === 'en' ? 'Select day' : 'Selecionar dia' ); ?>"
>

    <?php foreach ( $_hds_groups as $_hds_month_key => $_hds_group_items ) :
        $_hds_ts_first   = $_hds_group_items[0]['ts'];
        $_hds_month_lbl  = wp_date( $_hds_multi_year ? 'M Y' : 'F', $_hds_ts_first );
    ?>

    <div class="vana-day-selector__group">

        <span class="vana-day-selector__month-label" aria-hidden="true">
            <?php echo esc_html( $_hds_month_lbl ); ?>
        </span>

        <div class="vana-day-selector__pills" role="presentation">

            <?php foreach ( $_hds_group_items as $_hds_item ) :
                $_hds_day     = $_hds_item['day'];
                $_hds_dk      = (string) (
                                $_hds_day['day_key']    ??
                                $_hds_day['date_local'] ??
                                $_hds_day['date']       ??
                                '');
                $_hds_ts      = $_hds_item['ts'];
                $_hds_is_act  = ( $_hds_dk === $_hds_active );
                $_hds_is_today = ( $_hds_ts && date( 'Y-m-d', $_hds_ts ) === date( 'Y-m-d' ) );
                $_hds_num     = $_hds_ts ? wp_date( 'j',   $_hds_ts ) : $_hds_dk;
                $_hds_wday    = $_hds_ts ? wp_date( 'D',   $_hds_ts ) : '';
                $_hds_url     = add_query_arg( 'v_day', $_hds_dk, $_hds_base_url );
                $_hds_cls     = 'vana-day-selector__tab'
                              . ( $_hds_is_act   ? ' vana-day-selector__tab--active' : '' )
                              . ( $_hds_is_today ? ' vana-day-selector__tab--today'  : '' );
                $_hds_aria    = trim( $_hds_wday . ' ' . $_hds_num )
                              . ( $_hds_is_today ? ( $lang === 'en' ? ' — Today' : ' — Hoje' ) : '' );
            ?>

            <a
                href="<?php echo esc_url( $_hds_url ); ?>"
                class="<?php echo esc_attr( $_hds_cls ); ?>"
                role="tab"
                aria-selected="<?php echo $_hds_is_act ? 'true' : 'false'; ?>"
                data-day-key="<?php echo esc_attr( $_hds_dk ); ?>"
                aria-label="<?php echo esc_attr( $_hds_aria ); ?>"
            >
                <span class="vana-day-selector__weekday" aria-hidden="true">
                    <?php echo esc_html( $_hds_wday ); ?>
                </span>
                <span class="vana-day-selector__num">
                    <?php echo $_hds_is_today ? '<span aria-hidden="true">●</span>' : ''; ?>
                    <?php echo esc_html( $_hds_num ); ?>
                </span>
            </a>

            <?php endforeach; // _hds_group_items ?>

        </div><!-- /.vana-day-selector__pills -->
    </div><!-- /.vana-day-selector__group -->

    <?php endforeach; // _hds_groups ?>

</div><!-- /.vana-day-selector -->

<?php
unset(
    $_hds_days, $_hds_groups, $_hds_years, $_hds_multi_year,
    $_hds_active, $_hds_base_url,
    $_hds_month_key, $_hds_group_items, $_hds_ts_first, $_hds_month_lbl,
    $_hds_item, $_hds_day, $_hds_dk, $_hds_ts,
    $_hds_is_act, $_hds_is_today, $_hds_num, $_hds_wday,
    $_hds_url, $_hds_cls, $_hds_aria
);
?>
