<?php
/**
 * Minimal Atomic control stubs for the Elementor A/B tests.
 *
 * @package ReactWoo_Geo_Core
 */

namespace Elementor\Modules\AtomicWidgets\PropTypes\Primitives {

	class String_Prop_Type {
		/** @var string */
		public $default = '';

		/** @return self */
		public static function make() {
			return new self();
		}

		/**
		 * @param string $default Default.
		 * @return self
		 */
		public function default( $default ) {
			$this->default = (string) $default;
			return $this;
		}
	}
}

namespace Elementor\Modules\AtomicWidgets\Controls\Types {

	class Select_Control {
		/** @var string */
		public $bind = '';
		/** @var string */
		public $label = '';
		/** @var array<int, mixed> */
		public $options = array();
		/** @var string */
		public $description = '';

		/**
		 * @param string $bind Prop.
		 * @return self
		 */
		public static function bind_to( $bind ) {
			$control       = new self();
			$control->bind = (string) $bind;
			return $control;
		}

		/**
		 * @param string $label Label.
		 * @return self
		 */
		public function set_label( $label ) {
			$this->label = (string) $label;
			return $this;
		}

		/**
		 * @param array<int, mixed> $options Options.
		 * @return self
		 */
		public function set_options( $options ) {
			$this->options = $options;
			return $this;
		}

		/**
		 * @param string $description Description.
		 * @return self
		 */
		public function set_description( $description ) {
			$this->description = (string) $description;
			return $this;
		}
	}

	class Text_Control {
		/** @var string */
		public $bind = '';
		/** @var string */
		public $label = '';

		/**
		 * @param string $bind Prop.
		 * @return self
		 */
		public static function bind_to( $bind ) {
			$control       = new self();
			$control->bind = (string) $bind;
			return $control;
		}

		/**
		 * @param string $label Label.
		 * @return self
		 */
		public function set_label( $label ) {
			$this->label = (string) $label;
			return $this;
		}
	}
}

namespace Elementor\Modules\AtomicWidgets\Controls {

	class Section {
		/** @var string */
		private $id = '';
		/** @var string */
		private $label = '';
		/** @var array<int, mixed> */
		private $items = array();

		/** @return self */
		public static function make() {
			return new self();
		}

		/**
		 * @param string $label Label.
		 * @return self
		 */
		public function set_label( $label ) {
			$this->label = (string) $label;
			return $this;
		}

		/**
		 * @param string $id Id.
		 * @return self
		 */
		public function set_id( $id ) {
			$this->id = (string) $id;
			return $this;
		}

		/**
		 * @param array<int, mixed> $items Items.
		 * @return self
		 */
		public function set_items( $items ) {
			$this->items = $items;
			return $this;
		}

		/** @return string */
		public function get_id() {
			return $this->id;
		}

		/** @return string */
		public function get_label() {
			return $this->label;
		}

		/** @return array<int, mixed> */
		public function get_items() {
			return $this->items;
		}
	}
}
