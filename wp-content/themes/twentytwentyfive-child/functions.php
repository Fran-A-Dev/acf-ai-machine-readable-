<?php
/**
 * Twenty Twenty-Five Child — functions.
 * Shortcodes for the `property` CPT and Google Maps JS enqueue.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Enqueue the child theme stylesheet on the frontend.
 * Block themes don't load child style.css automatically.
 */
add_action( 'wp_enqueue_scripts', function () {
	wp_enqueue_style(
		'twentytwentyfive-child',
		get_stylesheet_directory_uri() . '/style.css',
		[],
		wp_get_theme()->get( 'Version' )
	);
} );

/**
 * Helpers
 */
function ttfc_field( $name, $post_id = null ) {
	if ( ! function_exists( 'get_field' ) ) {
		return '';
	}
	return get_field( $name, $post_id );
}

function ttfc_format_amenity_label( $slug_or_name ) {
	return ucwords( str_replace( [ '_', '-' ], ' ', $slug_or_name ) );
}

/**
 * [property_quick_facts]
 */
add_shortcode( 'property_quick_facts', function () {
	$id = get_the_ID();
	if ( ! $id ) {
		return '';
	}

	$items = [
		[ 'label' => 'Price',    'value' => '$' . number_format( (float) ttfc_field( 'price_per_night', $id ) ) . ' / night' ],
		[ 'label' => 'Bedrooms', 'value' => esc_html( ttfc_field( 'bedrooms', $id ) ) ],
		[ 'label' => 'Bathrooms','value' => esc_html( ttfc_field( 'bathrooms', $id ) ) ],
		[ 'label' => 'Sleeps',   'value' => esc_html( ttfc_field( 'max_guests', $id ) ) ],
		[ 'label' => 'Rating',   'value' => esc_html( ttfc_field( 'rating', $id ) ) . ' / 5' ],
	];

	$html = '<div class="property-quick-facts">';
	foreach ( $items as $i ) {
		$html .= sprintf(
			'<div class="property-quick-facts__item"><span class="property-quick-facts__label">%s</span><span class="property-quick-facts__value">%s</span></div>',
			esc_html( $i['label'] ),
			$i['value']
		);
	}
	$html .= '</div>';
	return $html;
} );

/**
 * [property_address]
 */
add_shortcode( 'property_address', function () {
	$id      = get_the_ID();
	$address = ttfc_field( 'address', $id );
	$city    = ttfc_field( 'city', $id );
	if ( ! $address && ! $city ) {
		return '';
	}
	return sprintf(
		'<p class="property-meta-line"><strong>Address:</strong> %s</p>',
		esc_html( trim( $address . ( $city ? ', ' . $city : '' ) ) )
	);
} );

/**
 * [property_stay_details]
 */
add_shortcode( 'property_stay_details', function () {
	$id       = get_the_ID();
	$checkin  = ttfc_field( 'checkin_time', $id );
	$checkout = ttfc_field( 'checkout_time', $id );
	if ( ! $checkin && ! $checkout ) {
		return '';
	}
	$html  = '<h2>Stay Details</h2>';
	$html .= '<p class="property-meta-line"><strong>Check-in:</strong> ' . esc_html( $checkin ) . '</p>';
	$html .= '<p class="property-meta-line"><strong>Check-out:</strong> ' . esc_html( $checkout ) . '</p>';
	return $html;
} );

/**
 * [property_type_region]
 */
add_shortcode( 'property_type_region', function () {
	$id    = get_the_ID();
	$types = wp_get_post_terms( $id, 'property_type', [ 'fields' => 'names' ] );
	$regs  = wp_get_post_terms( $id, 'region', [ 'fields' => 'names' ] );
	if ( is_wp_error( $types ) ) $types = [];
	if ( is_wp_error( $regs ) )  $regs  = [];

	$parts = [];
	if ( $types ) $parts[] = '<strong>Type:</strong> ' . esc_html( implode( ', ', $types ) );
	if ( $regs )  $parts[] = '<strong>Region:</strong> ' . esc_html( implode( ', ', $regs ) );
	if ( ! $parts ) return '';

	return '<p class="property-meta-line">' . implode( ' &middot; ', $parts ) . '</p>';
} );

/**
 * [property_amenities]
 */
add_shortcode( 'property_amenities', function () {
	$id    = get_the_ID();
	$terms = wp_get_post_terms( $id, 'amenity' );
	if ( is_wp_error( $terms ) || ! $terms ) {
		return '';
	}
	$html = '<h2>Amenities</h2><ul class="property-amenities">';
	foreach ( $terms as $t ) {
		$html .= '<li>' . esc_html( ttfc_format_amenity_label( $t->name ) ) . '</li>';
	}
	$html .= '</ul>';
	return $html;
} );

/**
 * [property_map]
 * Outputs a container div; the Google Maps JS (enqueued below) initializes it.
 */
add_shortcode( 'property_map', function () {
	$id  = get_the_ID();
	$loc = ttfc_field( 'location', $id );
	if ( ! is_array( $loc ) || empty( $loc['lat'] ) || empty( $loc['lng'] ) ) {
		return '';
	}
	return sprintf(
		'<h2>Location</h2><div id="property-map" class="property-map" data-lat="%s" data-lng="%s" data-zoom="%s" aria-label="Map of %s"></div>',
		esc_attr( $loc['lat'] ),
		esc_attr( $loc['lng'] ),
		esc_attr( ! empty( $loc['zoom'] ) ? $loc['zoom'] : 14 ),
		esc_attr( get_the_title( $id ) )
	);
} );

/**
 * Enqueue Google Maps JS on single property pages.
 */
add_action( 'wp_enqueue_scripts', function () {
	if ( ! is_singular( 'property' ) ) {
		return;
	}
	if ( ! defined( 'ACF_GOOGLE_MAPS_API_KEY' ) || ! ACF_GOOGLE_MAPS_API_KEY ) {
		return;
	}
	$src = 'https://maps.googleapis.com/maps/api/js?key=' . rawurlencode( ACF_GOOGLE_MAPS_API_KEY );
	wp_enqueue_script( 'ttfc-google-maps', $src, [], null, true );
	wp_add_inline_script( 'ttfc-google-maps', <<<JS
function ttfcInitPropertyMap() {
	var el = document.getElementById('property-map');
	if (!el || typeof google === 'undefined') return;
	var pos  = { lat: parseFloat(el.dataset.lat), lng: parseFloat(el.dataset.lng) };
	var zoom = parseInt(el.dataset.zoom, 10) || 14;
	var map  = new google.maps.Map(el, { center: pos, zoom: zoom });
	new google.maps.Marker({ position: pos, map: map });
}
window.addEventListener('load', ttfcInitPropertyMap);
JS );
} );
