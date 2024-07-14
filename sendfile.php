<?php
/*
Plugin Name: Sendfile
Plugin URI: https://www.cbdweb.net
Description: Force WP to use X-sendfile without checking the module exists. When running PHP FPM, WP cannot detect X-sendfile and falls back on force download
Version: 1.0
Author: CBDWeb
Author URI: http://www.cbdweb.net
*/
add_action( 'init', 'force_x_sendfile' );
function force_x_sendfile()
{
	// add_action( 'woocommerce_download_file_xsendfile', array( __CLASS__, 'download_file_xsendfile' ), 10, 2 ); // found in WC_Download_Handler
	remove_action( 'woocommerce_download_file_xsendfile', array( WC_Download_Handler::class, 'download_file_xsendfile' ), 10 );
	add_action( 'woocommerce_download_file_xsendfile', 'custom_download_file_xsendfile', 10, 2 );
}
function custom_download_file_xsendfile( $file_path, $filename ) {
	error_log('filepath = ' . $file_path);
	error_log('filename = ' . $filename);
	header('X-Sendfile: ' . $file_path . $filename);
	$parsed_file_path = WC_Download_Handler::parse_file_path($file_path);
	/*'remote_file'
			'file_path'*/
	error_log('parsed_file_path[remote_file] = ' . $parsed_file_path['remote_file']);
	error_log('parsed_file_path[file_path] = ' . $parsed_file_path['file_path']);
	custom_download_headers( $parsed_file_path['file_path'], $filename );
	error_log('after custom_download_headers');
	//$filepath = apply_filters( 'woocommerce_download_file_xsendfile_file_path', $parsed_file_path['file_path'], $file_path, $filename, $parsed_file_path );
	$filepath = $parsed_file_path['file_path'];
	error_log('filepath = ' . $filepath);
	header( 'X-Sendfile: ' . $filepath );
	exit;
}
function custom_clean_buffers() {
	if ( ob_get_level() ) {
		$levels = ob_get_level();
		for ( $i = 0; $i < $levels; $i++ ) {
			@ob_end_clean(); // phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged
		}
	} else {
		@ob_end_clean(); // phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged
	}
}
function custom_download_headers( $file_path, $filename, $download_range = array() ) {
	custom_check_server_config();
	custom_clean_buffers();
	wc_nocache_headers();

	header( 'X-Robots-Tag: noindex, nofollow', true );
	header( 'Content-Type: x/pdf' );
	header( 'Content-Description: File Transfer' );
	header( 'Content-Disposition: inline; filename="' . $filename . '";' );
	header( 'Content-Transfer-Encoding: binary' );

	$file_size = @filesize( $file_path ); // phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged
	if ( ! $file_size ) {
		return;
	}

	if ( isset( $download_range['is_range_request'] ) && true === $download_range['is_range_request'] ) {
		if ( false === $download_range['is_range_valid'] ) {
			header( 'HTTP/1.1 416 Requested Range Not Satisfiable' );
			header( 'Content-Range: bytes 0-' . ( $file_size - 1 ) . '/' . $file_size );
			exit;
		}

		$start  = $download_range['start'];
		$end    = $download_range['start'] + $download_range['length'] - 1;
		$length = $download_range['length'];

		header( 'HTTP/1.1 206 Partial Content' );
		header( "Accept-Ranges: 0-$file_size" );
		header( "Content-Range: bytes $start-$end/$file_size" );
		header( "Content-Length: $length" );
	} else {
		header( 'Content-Length: ' . $file_size );
	}
}
function custom_check_server_config() {
	wc_set_time_limit( 0 );
	if ( function_exists( 'apache_setenv' ) ) {
		@apache_setenv( 'no-gzip', 1 ); // phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged, WordPress.PHP.DiscouragedPHPFunctions.runtime_configuration_apache_setenv
	}
	@ini_set( 'zlib.output_compression', 'Off' ); // phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged, WordPress.PHP.DiscouragedPHPFunctions.runtime_configuration_ini_set
	@session_write_close(); // phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged, WordPress.VIP.SessionFunctionsUsage.session_session_write_close
}
