<?php
/**
 * Tests for B2B Pre-Order List and Batched Order creation.
 */

// Basic WordPress / WooCommerce mocks if not defined
if ( ! defined( 'ABSPATH' ) ) { define( 'ABSPATH', __DIR__ . '/../' ); }
if ( ! defined( 'ARRAY_A' ) ) { define( 'ARRAY_A', 'ARRAY_A' ); }

require_once __DIR__ . '/../includes/class-b2b-matrix-db.php';
require_once __DIR__ . '/../includes/class-b2b-preorder-list.php';

function test_assert( $cond, $msg ) {
    if ( ! $cond ) {
        echo "FAIL: {$msg}\n";
        exit( 1 );
    }
    echo "PASS {$msg}\n";
}

// 1. Test brand key normalization
test_assert( B2B_Preorder_List::normalize_brand_key( 'Joyce' ) === 'joyce', 'Brand normalization for Joyce' );
test_assert( B2B_Preorder_List::normalize_brand_key( 'joyce-kids' ) === 'joyce', 'Brand normalization for joyce-kids' );
test_assert( B2B_Preorder_List::normalize_brand_key( 'Paco' ) === 'paco', 'Brand normalization for Paco' );
test_assert( B2B_Preorder_List::normalize_brand_key( 'paco-and-co' ) === 'paco', 'Brand normalization for paco-and-co' );
test_assert( B2B_Preorder_List::normalize_brand_key( 'dreams' ) === 'dreams', 'Brand normalization for dreams' );
test_assert( B2B_Preorder_List::normalize_brand_key( 'other-brand' ) === 'other-brand', 'Brand normalization for custom brand' );

echo "All Preorder List unit tests passed!\n";
