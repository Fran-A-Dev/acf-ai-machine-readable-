<?php
/**
 * Import Austin properties from austin-properties.csv into the `property` CPT.
 * Run via: ./.local-wp.sh --path=... eval-file import-properties.php
 *
 * Idempotent: skips rows whose post_title already exists as a `property`.
 */

global $wpdb;

$csv_path = __DIR__ . '/austin-properties.csv';
if ( ! file_exists( $csv_path ) ) {
	WP_CLI::error( "CSV not found at {$csv_path}" );
}

$handle = fopen( $csv_path, 'r' );
$header = fgetcsv( $handle );

$created = [];
$skipped = [];
$errors  = [];

$get_or_create_term = function ( $name, $taxonomy ) {
	$slug = sanitize_title( $name );
	$term = get_term_by( 'slug', $slug, $taxonomy );
	if ( ! $term ) {
		$term = get_term_by( 'name', $name, $taxonomy );
	}
	if ( ! $term ) {
		$r = wp_insert_term( $name, $taxonomy );
		if ( is_wp_error( $r ) ) {
			return null;
		}
		return (int) $r['term_id'];
	}
	return (int) $term->term_id;
};

while ( ( $row = fgetcsv( $handle ) ) !== false ) {
	$data  = array_combine( $header, $row );
	$title = $data['name'];

	$existing_id = $wpdb->get_var( $wpdb->prepare(
		"SELECT ID FROM {$wpdb->posts} WHERE post_type='property' AND post_title=%s LIMIT 1",
		$title
	) );

	if ( $existing_id ) {
		$skipped[] = "{$title} (ID {$existing_id})";
		continue;
	}

	$post_id = wp_insert_post( [
		'post_type'    => 'property',
		'post_status'  => 'publish',
		'post_title'   => $title,
		'post_content' => $data['description'],
	], true );

	if ( is_wp_error( $post_id ) ) {
		$errors[] = "{$title}: " . $post_id->get_error_message();
		continue;
	}

	$pt_id = $get_or_create_term( $data['property_type'], 'property_type' );
	if ( $pt_id ) {
		wp_set_object_terms( $post_id, [ $pt_id ], 'property_type' );
	}

	$rg_id = $get_or_create_term( $data['region'], 'region' );
	if ( $rg_id ) {
		wp_set_object_terms( $post_id, [ $rg_id ], 'region' );
	}

	$amenity_ids = [];
	foreach ( explode( '|', $data['amenities'] ) as $a ) {
		$a = trim( $a );
		if ( ! $a ) {
			continue;
		}
		$id = $get_or_create_term( $a, 'amenity' );
		if ( $id ) {
			$amenity_ids[] = $id;
		}
	}
	if ( $amenity_ids ) {
		wp_set_object_terms( $post_id, $amenity_ids, 'amenity' );
	}

	update_field( 'price_per_night', (float) $data['price_per_night'], $post_id );
	update_field( 'bedrooms',        (int)   $data['bedrooms'],        $post_id );
	update_field( 'bathrooms',       (float) $data['bathrooms'],       $post_id );
	update_field( 'max_guests',      (int)   $data['max_guests'],      $post_id );
	update_field( 'address',         $data['address'],                 $post_id );
	update_field( 'city',            $data['city'],                    $post_id );
	update_field( 'location', [
		'address' => $data['address'] . ', ' . $data['city'] . ', TX',
		'lat'     => (float) $data['lat'],
		'lng'     => (float) $data['lng'],
		'zoom'    => 14,
	], $post_id );
	update_field( 'checkin_time',  $data['checkin_time']  . ':00', $post_id );
	update_field( 'checkout_time', $data['checkout_time'] . ':00', $post_id );
	update_field( 'rating',        (float) $data['rating'],        $post_id );

	$created[] = "{$title} (ID {$post_id})";
}
fclose( $handle );

WP_CLI::log( 'Created: ' . count( $created ) );
foreach ( $created as $c ) {
	WP_CLI::log( "  + {$c}" );
}
if ( $skipped ) {
	WP_CLI::log( 'Skipped (already existed): ' . count( $skipped ) );
	foreach ( $skipped as $s ) {
		WP_CLI::log( "  - {$s}" );
	}
}
if ( $errors ) {
	WP_CLI::warning( 'Errors: ' . count( $errors ) );
	foreach ( $errors as $e ) {
		WP_CLI::warning( "  ! {$e}" );
	}
}
