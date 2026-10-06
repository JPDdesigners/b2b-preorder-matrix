<?php
// Only the isolated MariaDB maintenance fixture uses this installer double.
function dbDelta( $sql ) {
    foreach ( explode( ';', $sql ) as $statement ) {
        if ( trim( $statement ) && false === $GLOBALS['wpdb']->query( $statement ) ) { throw new RuntimeException( $GLOBALS['wpdb']->last_error ); }
    }
}
