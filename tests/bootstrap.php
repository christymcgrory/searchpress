<?php

$_tests_dir = getenv( 'WP_TESTS_DIR' );

if ( ! $_tests_dir ) {
	$_tests_dir = '/tmp/wordpress-tests-lib';
}

require_once $_tests_dir . '/includes/functions.php';

function sp_manually_load_plugin() {
	// If your ES server is not at localhost:9200, you need to set $_ENV['SEARCHPRESS_HOST'].
	$host = getenv( 'SEARCHPRESS_HOST' );
	if ( empty( $host ) ) {
		$host = 'http://localhost:9200';
	}

	update_option( 'sp_settings', array(
		'host'      => $host,
		'must_init' => false,
		'active'    => true,
	) );
	require dirname( __FILE__ ) . '/../searchpress.php';

	SP_API()->index = 'searchpress-tests';

	// Make sure ES is running and responding
	$tries = 5;
	$sleep = 3;
	do {
		$response = wp_remote_get( 'http://localhost:9200/' );
		if ( '200' == wp_remote_retrieve_response_code( $response ) ) {
			// Looks good!
			break;
		} else {
			printf( "\nInvalid response from ES (%s), sleeping %d seconds and trying again...\n", wp_remote_retrieve_response_code( $response ), $sleep );
			sleep( $sleep );
		}
	} while ( --$tries );

	// If we didn't end with a 200 status code, exit
	sp_tests_verify_response_code( $response );

	sp_index_flush_data();

	$i = 0;
	while ( ! ( $beat = SP_Heartbeat()->check_beat( true ) ) && $i++ < 5 ) {
		echo "\nHeartbeat failed, sleeping 2 seconds and trying again...\n";
		sleep( 2 );
	}
	if ( ! $beat && ! SP_Heartbeat()->check_beat( true ) ) {
		echo "\nCould not find a heartbeat!";
		exit( 1 );
	}
}
tests_add_filter( 'muplugins_loaded', 'sp_manually_load_plugin' );

function sp_remove_index() {
	SP_Config()->flush();
}
tests_add_filter( 'shutdown', 'sp_remove_index' );

function sp_index_flush_data() {
	SP_Config()->flush();
	SP_Config()->create_mapping();
	SP_API()->post( '_refresh' );
}

function sp_tests_verify_response_code( $response ) {
	if ( '200' != wp_remote_retrieve_response_code( $response ) ) {
		printf( "Could not index posts!\nResponse code %s\n", wp_remote_retrieve_response_code( $response ) );
		if ( is_wp_error( $response ) ) {
			printf( "Message: %s\n", $response->get_error_message() );
		}
		exit( 1 );
	}
}


/**
 * Fakes a cron job
 */
function sp_tests_fake_cron() {
	$crons = _get_cron_array();
	foreach ( $crons as $timestamp => $cronhooks ) {
		foreach ( $cronhooks as $hook => $keys ) {
			if ( substr( $hook, 0, 3 ) !== 'sp_' ) {
				continue; // only run our own jobs.
			}

			foreach ( $keys as $k => $v ) {
				$schedule = $v['schedule'];

				if ( $schedule != false ) {
					$new_args = array( $timestamp, $schedule, $hook, $v['args'] );
					call_user_func_array( 'wp_reschedule_event', $new_args );
				}

				wp_unschedule_event( $timestamp, $hook, $v['args'] );
				do_action_ref_array( $hook, $v['args'] );
			}
		}
	}
}

/**
 * Is the current version of WordPress at least ... ?
 *
 * @param  float $min_version Minimum version required, e.g. 3.9.
 * @return bool True if it is, false if it isn't.
 */
function sp_phpunit_is_wp_at_least( $min_version ) {
	global $wp_version;
	return floatval( $wp_version ) >= $min_version;
}

require $_tests_dir . '/includes/bootstrap.php';

// Load a reusable test case.
require_once( dirname( __FILE__ ) . '/class-searchpress-unit-test-case.php' );
