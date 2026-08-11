<?php
/**
 * Platform-neutral ReactWoo Component Definition (WP8).
 *
 * @package ReactWoo_Geo_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Describes a component without binding it to Elementor/Gutenberg.
 */
final class RWGC_Component_Definition {

	/** @var string */
	private $type;
	/** @var int */
	private $schema_version;
	/** @var array<string, mixed> */
	private $props_schema;
	/** @var array<string, mixed> */
	private $design_tokens;
	/** @var array<string, mixed> */
	private $responsive;
	/** @var array<string, mixed> */
	private $accessibility;
	/** @var string */
	private $renderer_id;
	/** @var array<string, mixed> */
	private $metadata;

	/**
	 * @param string               $type Component type (e.g. hero).
	 * @param int                  $schema_version Schema version.
	 * @param array<string, mixed> $props_schema Props JSON-schema-ish map.
	 * @param array<string, mixed> $design_tokens Token contract.
	 * @param array<string, mixed> $responsive Responsive contract.
	 * @param array<string, mixed> $accessibility A11y contract.
	 * @param string               $renderer_id Default renderer id.
	 * @param array<string, mixed> $metadata Metadata.
	 */
	public function __construct(
		$type,
		$schema_version,
		array $props_schema,
		array $design_tokens,
		array $responsive,
		array $accessibility,
		$renderer_id = 'php_html',
		array $metadata = array()
	) {
		$this->type           = strtolower( trim( (string) $type ) );
		$this->schema_version = (int) $schema_version;
		$this->props_schema   = $props_schema;
		$this->design_tokens  = $design_tokens;
		$this->responsive     = $responsive;
		$this->accessibility  = $accessibility;
		$this->renderer_id    = (string) $renderer_id;
		$this->metadata       = $metadata;
	}

	/** @return string */
	public function type() {
		return $this->type;
	}

	/** @return int */
	public function schema_version() {
		return $this->schema_version;
	}

	/**
	 * @return array<string, mixed>
	 */
	public function props_schema() {
		return $this->props_schema;
	}

	/**
	 * @return array<string, mixed>
	 */
	public function design_tokens() {
		return $this->design_tokens;
	}

	/**
	 * @return array<string, mixed>
	 */
	public function responsive() {
		return $this->responsive;
	}

	/**
	 * @return array<string, mixed>
	 */
	public function accessibility() {
		return $this->accessibility;
	}

	/** @return string */
	public function renderer_id() {
		return $this->renderer_id;
	}

	/**
	 * @return array<string, mixed>
	 */
	public function metadata() {
		return $this->metadata;
	}

	/**
	 * @return array<string, mixed>
	 */
	public function to_array() {
		return array(
			'type'           => $this->type,
			'schema_version' => $this->schema_version,
			'props_schema'   => $this->props_schema,
			'design_tokens'  => $this->design_tokens,
			'responsive'     => $this->responsive,
			'accessibility'  => $this->accessibility,
			'renderer_id'    => $this->renderer_id,
			'metadata'       => $this->metadata,
		);
	}
}
