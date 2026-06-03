<?php
add_filter( 'acf/settings/enable_acf_ai', '__return_true' );
add_filter( 'acf/settings/enable_schema', '__return_true' );

add_action( 'acf/init', function () {
	if ( defined( 'ACF_GOOGLE_MAPS_API_KEY' ) && ACF_GOOGLE_MAPS_API_KEY ) {
		acf_update_setting( 'google_api_key', ACF_GOOGLE_MAPS_API_KEY );
	}
} );


