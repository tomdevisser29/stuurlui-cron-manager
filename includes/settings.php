<?php

add_action( 'admin_menu', 'strl_add_cron_manager_admin_page' );
add_action( 'admin_notices', 'strl_admin_cron_notices' );
add_action( 'admin_init', 'strl_force_run_cron' );
add_action( 'admin_init', 'strl_force_reset_cron' );

function strl_add_cron_manager_admin_page() {
	add_menu_page(
			__( 'Stuurlui Cron Manager', 'strl' ),
			__( 'Stuurlui Crons', 'strl' ),
			'manage_options',
			'cron-manager',
			'strl_render_cron_manager_page',
			'dashicons-clock',
			99
	);
}

function strl_render_cron_manager_page() {
	$crons = array();
	$cron_dir = STUURLUI_CRON_MANAGER_DIR . '/crons';
	$files = glob( $cron_dir . '/*.php' );

	foreach ( $files as $file ) {
		$file_name = basename( $file, '.php' );
		$cron_name = str_replace( '-', '_', $file_name );

		$description = '';

		if ( file_exists( $file ) ) {
			$file_contents = file_get_contents( $file );
			
			// Check for a description (assuming it’s a comment at the top of the file).
			if ( preg_match( '/\/\*\*(.*?)\*\//s', $file_contents, $matches ) ) {
				// Capture everything between /** and */
				$doc_comment = $matches[1];

				// Remove the leading asterisks and any extra whitespace
				$description = preg_replace( '/^\s*\* ?/m', '', $doc_comment );
		}
		}

		// Add the cron name and description to the array.
		$crons[ $cron_name ] = $description;
	}

	// Handle ordering
	$orderby = isset( $_GET['orderby'] ) ? sanitize_text_field( $_GET['orderby'] ) : 'name';
	$order = isset( $_GET['order'] ) && $_GET['order'] === 'asc' ? 'asc' : 'desc';

	// Sort the array of cron names based on the selected order
	if ( 'name' === $orderby ) {
		if ( 'asc' === $order ) {
			ksort( $crons );
		} else {
			krsort( $crons );
		}
	}

	$log_dir = STUURLUI_CRON_MANAGER_DIR . '/logs';

	ob_start();
	?>
	<div class="wrap">
		<h1 class="wp-heading-inline"><?php echo esc_html( get_admin_page_title() ); ?></h1>
		<hr class="wp-header-end">
		<div class="tablenav top">
			<br class="clear">
		</div>
		<table class="wp-list-table widefat fixed striped table-view-list">
			<thead>
				<tr>
					<th scope="col" id="cron-name" class="manage-column column-primary <?php echo ( 'name' === $orderby ) ? 'sorted ' . ( 'asc' === $order ? 'asc' : 'desc' ) : ''; ?>" aria-sort="<?php echo ( 'asc' === $order ) ? 'ascending' : 'descending'; ?>">
						<a href="<?php echo esc_url( add_query_arg( array( 'orderby' => 'name', 'order' => ( 'asc' === $order ? 'desc' : 'asc' ) ) ) ); ?>">
							<span><?php esc_html_e( 'Cron name', 'strl' ); ?></span>
							<span class="sorting-indicators">
								<span class="sorting-indicator asc" aria-hidden="true"></span>
								<span class="sorting-indicator desc" aria-hidden="true"></span>
							</span>
						</a>
					</th>
					<th class="manage-column"><?php esc_html_e( 'Description', 'strl' ); ?></th>
					<th class="manage-column column-posts"><?php esc_html_e( 'Last offset', 'strl' ); ?></th>
					<th class="manage-column column-posts"><?php esc_html_e( 'Batch size', 'strl' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php
				foreach ( $crons as $cron_name => $description ) {
					$log_file    = trailingslashit( $log_dir ) . $cron_name . '.log';
					$cron_option = get_option( $cron_name, array( 'offset' => 0, 'batch_size' => 0 ) );
					?>
					<tr>
						<td class="column-primary">
							<strong><?php echo esc_html( $cron_name ); ?></strong>
							<div class="row-actions">
								<span class="edit">
									<a href="<?php echo esc_url( add_query_arg( array( 'force_run' => $cron_name ) ) ); ?>">
										<?php esc_html_e( 'Run now', 'strl' ); ?>
									</a>
								</span>
								<?php
								if ( file_exists( $log_file ) ) {
									?>
									| <span class="download">
										<a href="<?php echo esc_url( STUURLUI_CRON_MANAGER_URL . '/logs/' . $cron_name . '.log' ); ?>" target="_blank" download>
											<?php esc_html_e( 'Download log', 'strl' ); ?>
										</a>
									</span>
									<?php
								}
								?>
								| <span class="delete">
									<a href="<?php echo esc_url( add_query_arg( array( 'force_reset' => $cron_name ) ) ); ?>" class="submitdelete">
										<?php esc_html_e( 'Reset', 'strl' ); ?>
									</a>
								</span>
							</div>
						</td>
						<td><?php echo esc_html( $description ); ?></td>
						<td class="column-posts"><?php echo esc_html( $cron_option['offset'] ); ?></td>
						<td class="column-posts"><?php echo esc_html( $cron_option['batch_size'] ); ?></td>
					</tr>
					<?php
				}
				?>
			</tbody>
		</table>
	</div>
	<?php
	echo ob_get_clean();
}

function strl_force_run_cron() {
	if ( isset( $_GET['force_run'] ) ) {
		$cron_name = sanitize_text_field( $_GET['force_run'] );
		$cron_file = str_replace( '_', '-', $cron_name );

		require_once STUURLUI_CRON_MANAGER_DIR . "/crons/{$cron_file}.php";

		$cron_manager->run();

		wp_redirect(
			add_query_arg(
				array(
					'cron_status' => 'ran',
					'cron_name'   => $cron_name,
				),
				remove_query_arg( array( 'force_run' ) )
			)
		);
		exit;
	}
}

function strl_force_reset_cron() {
	if ( isset( $_GET['force_reset'] ) ) {
		$cron_name = sanitize_text_field( $_GET['force_reset'] );
		$cron_file = str_replace( '_', '-', $cron_name );

		require_once STUURLUI_CRON_MANAGER_DIR . "/crons/{$cron_file}.php";

		$cron_manager->reset_state();

		wp_redirect(
			add_query_arg(
				array(
					'cron_status' => 'reset',
					'cron_name'   => $cron_name,
				),
				remove_query_arg( array( 'force_reset' ) )
			)
		);
		exit;
	}
}

function strl_admin_cron_notices() {
	if ( isset( $_GET['cron_status'] ) ) {
		$cron_status = sanitize_text_field( $_GET['cron_status'] );
		$cron_name   = isset( $_GET['cron_name'] ) ? sanitize_text_field( $_GET['cron_name'] ) : '';
		
		if ( $cron_status === 'ran' ) {
			$message = sprintf(
				/* translators: %s is the name of the cron that was run */
				__( 'The cron "%s" has been successfully run.', 'strl' ),
				esc_html( $cron_name )
			);
		} elseif ( $cron_status === 'reset' ) {
			$message = sprintf(
				/* translators: %s is the name of the cron that was reset */
				__( 'The cron "%s" has been successfully reset.', 'strl' ),
				esc_html( $cron_name )
			);
		} else {
			return;
		}

		printf(
			'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
			esc_html( $message )
		);
	}
}