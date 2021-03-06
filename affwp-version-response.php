<?php
/*
 * Plugin Name: AffWP Add-On Version Response
 * Description: Sets up the response for the AffiliateWP add-on update checks
 * Version: 1.0
 * Author: Pippin Williamson
 */

class AFFWP_Add_On_Version_Response {
	
	public function __construct() {
		add_action( 'init', array( $this, 'check_for_request' ) );
	}

	public function check_for_request() {

		if( empty( $_POST['affwp_action'] ) || 'get_version' != $_POST['affwp_action'] ) {
			return;
		}

		if( empty( $_POST['license'] ) ) {
			return;
		}

		if( empty( $_POST['id'] ) || ! is_numeric( $_POST['id'] ) ) {
			return;
		}

		if( empty( $_POST['slug'] ) ) {
			return;
		}

		if( empty( $_POST['url'] ) ) {
			return;
		}

		if( ! function_exists( 'edd_software_licensing' ) ) {
			return;
		}

		$add_on = get_post( absint( $_POST['id'] ) );
		if( ! $add_on ) {
			status_header( 404 );
			return;
		}

		$licensing = edd_software_licensing();
		$license   = $licensing->get_license_by_key( sanitize_text_field( $_POST['license'] ) );

		if( ! $license ) {
			status_header( 402 );
			return;
		}
		
		$price_id = (int) get_post_meta( $license, '_edd_sl_download_price_id', true );

		if( 2 !== $price_id && 3 !== $price_id ) {
			status_header( 401 );
			return; // Not a developer license
		}

		// All good, retrieve the Add On details

		if( 'expired' === $licensing->get_license_status( $license ) ) {
			$description = '<p><strong>' . __( 'Your license is expired. Please renew it or purchase a new one in order to update this item.', 'edd_sl' ) . '</strong></p>' . $description;
			$changelog   = '<p><strong>' . __( 'Your license is expired. Please renew it or purchase a new one in order to update this item.', 'edd_sl' ) . '</strong></p>' . $changelog;
		} else {

			$changelog   = get_post_meta( $add_on->ID, '_edd_sl_changelog', true );
			$description = ! empty( $add_on->post_excerpt ) ? $add_on->post_excerpt : $add_on->post_content;

		}

		$response = array(
			'new_version'   => get_post_meta( $add_on->ID, '_edd_sl_version', true ),
			'name'          => $add_on->post_title,
			'slug'          => $_POST['slug'],
			'url'           => get_permalink( $add_on->ID ),
			'homepage'      => get_permalink( $add_on->ID ),
			'package'       => $this->get_encoded_download_package_url( $add_on->ID ),
			'download_link' => $this->get_encoded_download_package_url( $add_on->ID ),
			'sections'      => serialize(
				array(
					'description' => wpautop( strip_tags( $description, '<p><li><ul><ol><strong><a><em><span><br>' ) ),
					'changelog'   => wpautop( strip_tags( stripslashes( $changelog ), '<p><li><ul><ol><strong><a><em><span><br>' ) )
				 )
			 )
		);

		echo json_encode( $response ); exit;

	}

	public function get_encoded_download_package_url( $add_on_id ) {

		$download_name 	= get_the_title( $add_on_id );
		$file_key 		= get_post_meta( $add_on_id, '_edd_sl_upgrade_file_key', true );

		$hash = md5( $download_name . $file_key . $add_on_id );

		$package_url = add_query_arg( array(
			'edd_action' 	=> 'package_download',
			'id' 			=> $add_on_id,
			'key' 			=> $hash,
			'expires'		=> rawurlencode( base64_encode( strtotime( '+1 hour' ) ) )
		 ), trailingslashit( home_url() ) );

		return apply_filters( 'edd_sl_encoded_package_url', $package_url );
	}

}
new AFFWP_Add_On_Version_Response;