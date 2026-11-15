<?php
/**
 * Elementor plugin stand-in for A/B surface tests.
 *
 * @package ReactWoo_Geo_Core
 */

namespace Elementor {

	class Plugin {

		/** @var self|null */
		public static $instance;

		/** @var object|null */
		public $editor;

		/** @var object|null */
		public $preview;

		/** @var object|null */
		public $experiments;
	}
}
