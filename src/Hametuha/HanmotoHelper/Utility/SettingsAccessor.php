<?php

namespace Hametuha\HanmotoHelper\Utility;

use Hametuha\HanmotoHelper\Controller\Settings;

/**
 * Access option.
 */
trait SettingsAccessor {

	/**
	 * Get settings.
	 *
	 * @return Settings
	 */
	public function option() {
		return Settings::get_instance();
	}
}
