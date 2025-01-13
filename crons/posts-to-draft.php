<?php
/**
 * This is an example cron.
 * It changes the post statuses to draft.
 */

header('Cache-Control: no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

require_once $_SERVER['DOCUMENT_ROOT'] . '/wp-load.php';
require_once STUURLUI_CRON_MANAGER_DIR . '/includes/class-stuurlui-cron-manager.php';

$file_name = basename( __FILE__, '.php' );
$cron_name = str_replace( '-', '_', $file_name );

/**
 * This is to count how many posts the cron runs the function below.
 */
function get_total_post_count() {
	global $wpdb;

	return (int) $wpdb->get_var(
		"SELECT COUNT(ID)
		FROM {$wpdb->posts}
		WHERE post_status = 'publish'"
	);
}

/**
 * This is what the cron does.
 */
function update_post_statuses( $offset, $batch_size ) {
	global $wpdb;

	$posts = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT ID
			FROM {$wpdb->posts}
			WHERE post_status = 'publish'
			LIMIT %d OFFSET %d",
			$batch_size,
			$offset
		)
	);

	$processed = 0;
	foreach ( $posts as $post ) {
		wp_update_post(
			array(
				'ID'         => $post->ID,
				'post_status' => 'draft',
			)
		);
		$processed++;
	}

	return array(
		'processed' => $processed
	);
}

$cron_manager = new Stuurlui_Cron_Manager( $cron_name, 'update_post_statuses', 'get_total_post_count' );

if ( isset( $_GET['reset'] ) ) {
	$cron_manager->reset_state();
}

if ( isset( $_GET['run'] ) ) {
	$cron_manager->run();
}
