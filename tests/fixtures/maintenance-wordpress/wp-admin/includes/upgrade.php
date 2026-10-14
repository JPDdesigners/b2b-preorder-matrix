<?php
// Only the isolated MariaDB maintenance fixture uses this installer double.
function dbDelta( $sql ) {
    foreach ( explode( ';', $sql ) as $statement ) {
        // This release adds a table without changing existing table columns.
        // Preserve existing tables as WordPress dbDelta does for an unchanged schema.
        $statement = preg_replace('/\bCREATE TABLE\s+/i', 'CREATE TABLE IF NOT EXISTS ', $statement);
        if ( trim( $statement ) && false === $GLOBALS['wpdb']->query( $statement ) ) { throw new RuntimeException( $GLOBALS['wpdb']->last_error ); }
    }
}
