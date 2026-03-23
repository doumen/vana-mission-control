<?php
$post_id = 359;
$metas = get_post_meta( $post_id );
echo "=== ALL POST METAS FOR POST 359 ===\n";
print_r( $metas );

echo "\n=== META KEYS ONLY ===\n";
echo implode( "\n", array_keys( $metas ) ) . "\n";

echo "\n=== VANA-RELATED METAS ===\n";
foreach ( $metas as $key => $values ) {
    if ( stripos( $key, 'vana' ) !== false || stripos( $key, 'visit' ) !== false || stripos( $key, 'timeline' ) !== false ) {
        echo $key . " => ";
        if ( is_array( $values ) && ! empty( $values ) ) {
            $val = $values[0];
            echo "(" . strlen( $val ) . " bytes) " . substr( $val, 0, 100 ) . "...\n";
        } else {
            echo "(empty)\n";
        }
    }
}
