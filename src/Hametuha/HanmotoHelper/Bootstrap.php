<?php

namespace Hametuha\HanmotoHelper;


use Hametuha\HanmotoHelper\Pattern\Singleton;

/**
 * Plugin Bootstrap.
 *
 * @package hanmoto
 */
class Bootstrap extends Singleton {

	/**
	 * @inheritDoc
	 */
	protected function init() {

		// Register command.
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			\WP_CLI::add_command( 'hanmoto', \Hametuha\HanmotoHelper\Utility\Commands::class );
		}

	}


}
