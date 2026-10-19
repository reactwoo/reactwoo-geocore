<?php
/**
 * PHPUnit bootstrap: minimal WordPress stubs for engine classes (no full WP load).
 */

define( 'ABSPATH', dirname( __DIR__ ) . '/' );

if ( ! function_exists( 'sanitize_text_field' ) ) {
	/**
	 * @param mixed $str Value.
	 * @return string
	 */
	function sanitize_text_field( $str ) {
		return is_scalar( $str ) ? (string) $str : '';
	}
}

if ( ! function_exists( 'sanitize_key' ) ) {
	/**
	 * @param mixed $key Value.
	 * @return string
	 */
	function sanitize_key( $key ) {
		$key = strtolower( (string) $key );
		return preg_replace( '/[^a-z0-9_\-]/', '', $key );
	}
}

if ( ! function_exists( 'absint' ) ) {
	/**
	 * @param mixed $maybeint Value.
	 * @return int
	 */
	function absint( $maybeint ) {
		return (int) abs( (float) $maybeint );
	}
}

if ( ! function_exists( 'apply_filters' ) ) {
	/**
	 * @param string $hook Hook name.
	 * @param mixed  $value Value.
	 * @param mixed  ...$args Extra args (unused in tests).
	 * @return mixed
	 */
	function apply_filters( $hook, $value, ...$args ) {
		if ( isset( $GLOBALS['rwgc_test_filters'][ $hook ] ) && is_callable( $GLOBALS['rwgc_test_filters'][ $hook ] ) ) {
			return $GLOBALS['rwgc_test_filters'][ $hook ]( $value, ...$args );
		}
		return $value;
	}
}

if ( ! function_exists( 'wp_unslash' ) ) {
	/**
	 * @param mixed $value Value.
	 * @return mixed
	 */
	function wp_unslash( $value ) {
		return $value;
	}
}

if ( ! function_exists( 'esc_html' ) ) {
	/**
	 * @param mixed $text Text.
	 * @return string
	 */
	function esc_html( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'wp_parse_url' ) ) {
	/**
	 * @param string $url URL.
	 * @param int    $component parse_url() component.
	 * @return mixed
	 */
	function wp_parse_url( $url, $component = -1 ) {
		if ( -1 === $component ) {
			return parse_url( (string) $url );
		}
		return parse_url( (string) $url, $component );
	}
}

if ( ! function_exists( 'wp_json_encode' ) ) {
	/**
	 * @param mixed $value Value.
	 * @return string|false
	 */
	function wp_json_encode( $value ) {
		return json_encode( $value );
	}
}

if ( ! function_exists( 'do_action' ) ) {
	/**
	 * @param string $hook Hook name.
	 * @param mixed  ...$args Arguments.
	 * @return void
	 */
	function do_action( $hook, ...$args ) {
	}
}

if ( ! class_exists( 'WP_Post', false ) ) {
	/**
	 * Minimal post object stub for unit tests.
	 */
	class WP_Post {
		/** @var int */
		public $ID;
		/** @var string */
		public $post_status;
		/** @var string */
		public $post_title;
	}
}

if ( ! function_exists( 'wp_strip_all_tags' ) ) {
	/**
	 * @param string $text          Text.
	 * @param bool   $remove_breaks Collapse breaks.
	 * @return string
	 */
	function wp_strip_all_tags( $text, $remove_breaks = false ) {
		$text = preg_replace( '@<(script|style)[^>]*?>.*?</\\1>@si', '', (string) $text );
		$text = strip_tags( (string) $text );
		if ( $remove_breaks ) {
			$text = preg_replace( '/[\r\n\t ]+/', ' ', $text );
		}
		return trim( (string) $text );
	}
}

if ( ! function_exists( 'wp_mkdir_p' ) ) {
	/**
	 * @param string $target Directory.
	 * @return bool
	 */
	function wp_mkdir_p( $target ) {
		if ( is_dir( $target ) ) {
			return true;
		}
		return mkdir( (string) $target, 0755, true ) || is_dir( $target );
	}
}

if ( ! function_exists( 'wp_is_writable' ) ) {
	/**
	 * @param string $path Path.
	 * @return bool
	 */
	function wp_is_writable( $path ) {
		return is_writable( $path );
	}
}

if ( ! function_exists( 'wp_delete_file' ) ) {
	/**
	 * @param string $file File.
	 * @return void
	 */
	function wp_delete_file( $file ) {
		if ( is_string( $file ) && '' !== $file && file_exists( $file ) && ! is_dir( $file ) ) {
			unlink( $file );
		}
	}
}

if ( ! class_exists( 'WP_Filesystem_Direct', false ) ) {
	/**
	 * Recursive local delete for MaxMind temp directories.
	 */
	class WP_Filesystem_Direct {
		/**
		 * @param mixed $arg Unused.
		 */
		public function __construct( $arg = null ) {
			unset( $arg );
		}

		/**
		 * @param string $path      File or directory.
		 * @param bool   $recursive Delete contents.
		 * @return bool
		 */
		public function rmdir( $path, $recursive = false ) {
			if ( ! is_string( $path ) || '' === $path || ! file_exists( $path ) ) {
				return false;
			}
			if ( is_link( $path ) || is_file( $path ) ) {
				return unlink( $path );
			}
			if ( $recursive ) {
				$items = scandir( $path );
				if ( ! is_array( $items ) ) {
					return false;
				}
				foreach ( $items as $item ) {
					if ( '.' === $item || '..' === $item ) {
						continue;
					}
					if ( ! $this->rmdir( $path . '/' . $item, true ) ) {
						return false;
					}
				}
			}
			return rmdir( $path );
		}
	}
}

if ( ! function_exists( '__' ) ) {
	/**
	 * @param string $text Text.
	 * @return string
	 */
	function __( $text ) {
		return $text;
	}
}

if ( ! function_exists( 'get_the_title' ) ) {
	/**
	 * @param mixed $post Post.
	 * @return string
	 */
	function get_the_title( $post = 0 ) {
		return is_object( $post ) && isset( $post->post_title ) ? (string) $post->post_title : '';
	}
}

if ( ! function_exists( 'get_post_modified_time' ) ) {
	/**
	 * @param string $d Format.
	 * @param bool   $gmt GMT.
	 * @param mixed  $post Post.
	 * @param bool   $translate Translate.
	 * @return string
	 */
	function get_post_modified_time( $d, $gmt, $post, $translate ) {
		unset( $d, $gmt, $post, $translate );
		return '2026-06-01 12:00';
	}
}

if ( ! function_exists( 'admin_url' ) ) {
	/**
	 * @param string $path Path.
	 * @return string
	 */
	function admin_url( $path = '' ) {
		return 'http://example.test/wp-admin/' . ltrim( $path, '/' );
	}
}

$base = dirname( __DIR__ ) . '/includes/';
require_once $base . 'contracts/class-rwgc-contracts.php';
RWGC_Contracts::load();
require_once $base . 'context/class-rwgc-context-attribution.php';
require_once $base . 'engine/class-rwgc-context.php';
require_once $base . 'rules/class-rwgc-rule-condition-evaluator.php';
require_once $base . 'events/class-rwgc-event.php';
require_once $base . 'events/class-rwgc-events.php';
