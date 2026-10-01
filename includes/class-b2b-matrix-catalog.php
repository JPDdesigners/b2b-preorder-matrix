<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Shared catalog structure only. Never cache a nonce, user ID, price or quantity here. */
class B2B_Matrix_Catalog {
    const GENERATION = 'b2b_matrix_catalog_generation';

    public static function init() {
        foreach ( [ 'woocommerce_new_product', 'woocommerce_update_product', 'woocommerce_delete_product', 'woocommerce_new_product_variation', 'woocommerce_update_product_variation', 'woocommerce_delete_product_variation', 'save_post_product', 'save_post_product_variation', 'edited_term', 'delete_term', 'edit_attachment', 'delete_attachment' ] as $hook ) {
            add_action( $hook, [ __CLASS__, 'invalidate' ] );
        }
        add_action( 'transition_post_status', [ __CLASS__, 'status_changed' ], 10, 3 );
    }

    public static function invalidate( $unused = null ) {
        // A generation avoids stale copies across locales and parent/variation updates.
        update_option( self::GENERATION, wp_generate_uuid4(), false );
    }

    public static function status_changed( $new, $old, $post ) {
        if ( $new !== $old && in_array( $post->post_type, [ 'product', 'product_variation' ], true ) ) { self::invalidate(); }
    }

    private static function label( $taxonomy, $slug ) {
        if ( taxonomy_exists( $taxonomy ) ) {
            $term = get_term_by( 'slug', $slug, $taxonomy );
            if ( $term && ! is_wp_error( $term ) ) { return $term->name; }
        }
        return $slug;
    }

    public static function resolve_color_size_keys( $attributes, $product = null ) {
        if ( empty( $attributes ) || ! is_array( $attributes ) ) {
            return [ null, null ];
        }

        $color_patterns = [
            'pa_color', 'color', 'pa_colour', 'colour',
            'pa_chroma', 'pa_xroma', 'pa_xromata', 'pa_xrōma',
            'chroma', 'xroma', 'xromata',
            'pa_χρώμα', 'pa_χρωμα', 'χρώμα', 'χρωμα', 'χρωματα', 'χρώματα',
        ];
        $size_patterns = [
            'pa_size', 'size', 'pa_sizes', 'sizes',
            'pa_megethos', 'pa_noumero', 'megethos', 'noumero',
            'pa_μέγεθος', 'pa_μεγεθος', 'μέγεθος', 'μεγεθος',
            'pa_νούμερο', 'pa_νουμερο', 'νούμερο', 'νουμερο',
            'νούμερα', 'νουμερα', 'μεγέθη', 'μεγεθη',
        ];

        $color_key = null;
        $size_key  = null;

        foreach ( array_keys( $attributes ) as $attr_name ) {
            $lower = function_exists( 'mb_strtolower' ) ? mb_strtolower( (string) $attr_name, 'UTF-8' ) : strtolower( (string) $attr_name );
            $stripped = str_replace( 'pa_', '', $lower );
            if ( ! $color_key && ( in_array( $lower, $color_patterns, true ) || in_array( $stripped, [ 'color', 'colour', 'chroma', 'xroma', 'χρωμα', 'χρώμα' ], true ) ) ) {
                $color_key = $attr_name;
            }
            if ( ! $size_key && ( in_array( $lower, $size_patterns, true ) || in_array( $stripped, [ 'size', 'sizes', 'megethos', 'noumero', 'μεγεθος', 'μέγεθος', 'νουμερο', 'νούμερο' ], true ) ) ) {
                $size_key = $attr_name;
            }
        }

        // Fallback: If 2 attributes exist and one or both couldn't be matched by dictionary
        if ( count( $attributes ) === 2 && ( ! $color_key || ! $size_key ) ) {
            $keys = array_keys( $attributes );
            if ( ! $color_key && ! $size_key ) {
                $color_key = $keys[0];
                $size_key  = $keys[1];
            } elseif ( ! $color_key ) {
                $color_key = ( $keys[0] === $size_key ) ? $keys[1] : $keys[0];
            } elseif ( ! $size_key ) {
                $size_key = ( $keys[0] === $color_key ) ? $keys[1] : $keys[0];
            }
        }

        if ( function_exists( 'apply_filters' ) ) {
            $color_key = apply_filters( 'b2b_matrix_color_attribute_key', $color_key, $attributes, $product );
            $size_key  = apply_filters( 'b2b_matrix_size_attribute_key', $size_key, $attributes, $product );
        }

        return [ $color_key, $size_key ];
    }

    public static function structure( $product ) {
        $key = 'b2bm_catalog_5_' . $product->get_id() . '_' . md5( get_locale() . '|' . get_option( self::GENERATION, 'initial' ) );
        $cached = get_transient( $key );
        if ( is_array( $cached ) ) { return $cached; }

        $attributes = $product->get_variation_attributes();
        list( $color_key, $size_key ) = self::resolve_color_size_keys( $attributes, $product );
        $colors = $color_key ? array_values( $attributes[ $color_key ] ?? [] ) : [];
        $sizes  = $size_key  ? array_values( $attributes[ $size_key ] ?? [] ) : [];

        // Support products with only sizes (default row "Standard") or only colors (default column "One Size")
        if ( empty( $colors ) && ! empty( $sizes ) ) {
            $color_key = '_default';
            $colors = [ 'Standard' ];
        } elseif ( ! empty( $colors ) && empty( $sizes ) ) {
            $size_key = '_default';
            $sizes = [ '-' ];
        }

        if ( ! $colors || ! $sizes ) { return new WP_Error( 'unsupported_attributes', 'Το προϊόν χρειάζεται χαρακτηριστικά color και size.', [ 'status' => 422 ] ); }

        // Native WooCommerce sort order for sizes
        if ( $size_key && $size_key !== '_default' && function_exists( 'wc_get_product_terms' ) && taxonomy_exists( $size_key ) ) {
            $ordered_terms = wc_get_product_terms( $product->get_id(), $size_key, [ 'fields' => 'slugs' ] );
            if ( ! empty( $ordered_terms ) && ! is_wp_error( $ordered_terms ) ) {
                $ordered_sizes = [];
                foreach ( $ordered_terms as $s_slug ) {
                    if ( in_array( $s_slug, $sizes, true ) ) {
                        $ordered_sizes[] = $s_slug;
                    }
                }
                foreach ( $sizes as $s_slug ) {
                    if ( ! in_array( $s_slug, $ordered_sizes, true ) ) {
                        $ordered_sizes[] = $s_slug;
                    }
                }
                $sizes = $ordered_sizes;
            }
        }

        $matrix = $images = [];
        // Deliberately bypass get_available_variations(): no stock, tax, shipping or cart eligibility.
        foreach ( $product->get_children() as $id ) {
            $variation = wc_get_product( $id );
            if ( ! $variation || ! $variation->is_type( 'variation' ) || $variation->get_status( 'edit' ) !== 'publish' || (int) $variation->get_parent_id( 'edit' ) !== (int) $product->get_id() ) { continue; }
            $values = $variation->get_variation_attributes();

            if ( $color_key === '_default' ) {
                $color = 'Standard';
            } else {
                $color_slug = function_exists( 'sanitize_title' ) ? sanitize_title( $color_key ) : strtolower( (string) $color_key );
                $color = $values[ 'attribute_' . $color_key ] ?? ( $values[ 'attribute_' . $color_slug ] ?? ( method_exists( $variation, 'get_attribute' ) ? $variation->get_attribute( $color_key ) : '' ) );
                if ( $color === '' && count( $colors ) === 1 ) {
                    $color = $colors[0];
                }
            }

            if ( $size_key === '_default' ) {
                $size = '-';
            } else {
                $size_slug = function_exists( 'sanitize_title' ) ? sanitize_title( $size_key ) : strtolower( (string) $size_key );
                $size = $values[ 'attribute_' . $size_key ] ?? ( $values[ 'attribute_' . $size_slug ] ?? ( method_exists( $variation, 'get_attribute' ) ? $variation->get_attribute( $size_key ) : '' ) );
                if ( $size === '' && count( $sizes ) === 1 ) {
                    $size = $sizes[0];
                }
            }

            if ( $color === '' || $size === '' || isset( $matrix[ $color ][ $size ] ) ) {
                return new WP_Error( 'ambiguous_variations', 'Κάθε παραλλαγή χρειάζεται μοναδικό, συγκεκριμένο χρώμα και μέγεθος.', [ 'status' => 422 ] );
            }
            if ( ! in_array( $color, $colors, true ) || ! in_array( $size, $sizes, true ) ) { continue; }
            $image_id = (int) ( $variation->get_image_id( 'edit' ) ?: $product->get_image_id( 'edit' ) );
            if ( ! isset( $images[ $image_id ] ) ) {
                $thumb = $image_id ? wp_get_attachment_image_src( $image_id, 'thumbnail' ) : false;
                $full = $image_id ? wp_get_attachment_image_src( $image_id, 'full' ) : false;
                $images[ $image_id ] = [ 'img' => $thumb[0] ?? '', 'full_img' => $full[0] ?? '', 'image_width' => (int) ( $full[1] ?? 0 ), 'image_height' => (int) ( $full[2] ?? 0 ) ];
            }
            $matrix[ $color ][ $size ] = array_merge( [ 'id' => (int) $id ], $images[ $image_id ] );
        }
        $color_labels = $size_labels = [];
        foreach ( $colors as $color ) {
            $color_labels[ $color ] = ( $color === 'Standard' ) ? 'Βασικό' : self::label( $color_key, $color );
        }
        foreach ( $sizes as $size ) {
            $size_labels[ $size ] = ( $size === '-' ) ? 'One Size' : self::label( $size_key, $size );
        }
        $result = [
            'product_id' => (int) $product->get_id(), 'product_name' => $product->get_name( 'edit' ), 'parent_sku' => (string) $product->get_sku( 'edit' ),
            'colors' => $colors, 'sizes' => $sizes, 'color_labels' => $color_labels, 'size_labels' => $size_labels, 'matrix' => $matrix,
        ];
        $result['structure_version'] = hash( 'sha256', wp_json_encode( $result ) );
        set_transient( $key, $result, HOUR_IN_SECONDS );
        return $result;
    }
}
