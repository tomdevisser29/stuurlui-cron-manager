<?php

class Stuurlui_Cron_Manager {
	private $cron_name;
	private $log_dir;
	private $batch_size;
	private $callback;
	private $total_count_callback;

	public function __construct( $cron_name, $callback, $total_count_callback, $batch_size = 20 ) {
		$this->cron_name = $cron_name;
		$this->batch_size = $batch_size;
		$this->callback = $callback;
		$this->total_count_callback = $total_count_callback;
		$this->log_dir = STUURLUI_CRON_MANAGER_DIR . '/logs';

		if ( ! is_dir( $this->log_dir ) ) {
			wp_mkdir_p( $this->log_dir );
		}
	}

	private function get_state() {
		return get_option(
			$this->cron_name,
			array(
				'offset' => 0,
				'batch_size' => $this->batch_size,
				'completed' => false,
				'total_count' => -1,
			)
		);
	}

	private function update_state( $state ) {
		update_option( $this->cron_name, $state );
	}

	public function reset_state() {
		$this->update_state(
			array(
				'offset' => 0,
				'batch_size' => $this->batch_size,
				'completed' => false,
				'total_count' => -1,
			)
		);

		$this->log_event( "The state has been reset." );
	}

	public function log_event( $message ) {
		$log_file = trailingslashit( $this->log_dir ) . $this->cron_name . '.log';
		$timestamp = gmdate( 'Y-m-d H:i:s' );
		$log_entry = "[{$timestamp}] {$message}" . PHP_EOL;

		// LOCK_EX prevents other processes from accessing the file at the same time.
		file_put_contents( $log_file, $log_entry, FILE_APPEND | LOCK_EX );
	}

	public function run() {
		$state = $this->get_state();

		if ( $state['completed'] ) {
			$this->log_event( "Cron {$this->cron_name} has already been completed." );
			return;
		}

		if ( $state['total_count'] == -1 && $this->total_count_callback ) {
			$state['total_count'] = call_user_func( $this->total_count_callback );
			$this->update_state( $state );
			$this->log_event( "Total count calculated: {$state['total_count']}." );
	}

		$results = call_user_func( $this->callback, $state['offset'], $state['batch_size'] );

		if ( ! is_array( $results ) || ! isset( $results['processed'] ) ) {
			throw new InvalidArgumentException( 'Callback must return an array with the key "processed".' );
		}

		$state['offset'] += $results['processed'];
		$state['completed'] = $state['offset'] >= $state['total_count'];

		$this->update_state( $state );
		$this->log_event( "Processed {$results['processed']} of {$state['total_count']} items. Offset is now {$state['offset']}." );

		if ( $state['completed'] ) {
			$this->log_event( "Cron {$this->cron_name} has been completed." );
		}
	}
}
