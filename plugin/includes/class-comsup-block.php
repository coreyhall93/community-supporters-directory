<?php
/**
 * Gutenberg block registration.
 *
 * @package CommunitySupportersDirectory
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the "Community Supporters Directory" dynamic block.
 *
 * The block is server-rendered and delegates to COMSUP_Shortcode, so the block and
 * the [community_supporters] shortcode always produce identical markup.
 */
class COMSUP_Block {

	/**
	 * Shortcode renderer, reused for server-side block output.
	 *
	 * @var COMSUP_Shortcode
	 */
	private $shortcode;

	/**
	 * Constructor.
	 *
	 * @param COMSUP_Shortcode $shortcode Shared shortcode instance.
	 */
	public function __construct( COMSUP_Shortcode $shortcode ) {
		$this->shortcode = $shortcode;
		add_action( 'init', array( $this, 'register' ) );
	}

	/**
	 * Register the editor script and the block type.
	 */
	public function register() {
		if ( ! function_exists( 'register_block_type' ) ) {
			return; // Classic-only WordPress; nothing to do.
		}

		wp_register_script(
			'community-supporters-block',
			COMSUP_PLUGIN_URL . 'assets/js/block.js',
			array( 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-server-side-render', 'wp-i18n' ),
			COMSUP_VERSION,
			true
		);

		// Register the shared stylesheet here too, so the block.json "style" /
		// "editorStyle" handle exists in both front-end and editor contexts.
		if ( ! wp_style_is( 'community-supporters', 'registered' ) ) {
			wp_register_style(
				'community-supporters',
				COMSUP_PLUGIN_URL . 'assets/css/community-supporters.css',
				array(),
				COMSUP_VERSION
			);
		}

		wp_set_script_translations( 'community-supporters-block', 'community-supporters' );

		register_block_type(
			COMSUP_PLUGIN_DIR . 'blocks/community-supporters',
			array(
				'render_callback' => array( $this, 'render' ),
			)
		);
	}

	/**
	 * Server-side render callback: map block attributes onto the shortcode.
	 *
	 * @param array $attributes Block attributes.
	 * @return string
	 */
	public function render( $attributes ) {
		$atts = array(
			'layout'   => isset( $attributes['layout'] ) ? $attributes['layout'] : 'grid',
			'columns'  => isset( $attributes['columns'] ) ? (int) $attributes['columns'] : 3,
			'limit'    => isset( $attributes['limit'] ) ? (int) $attributes['limit'] : 0,
			'country'  => isset( $attributes['country'] ) ? $attributes['country'] : '',
			'language' => isset( $attributes['language'] ) ? $attributes['language'] : '',
			'search'   => isset( $attributes['search'] ) ? $attributes['search'] : '',
			'fields'     => isset( $attributes['fields'] ) ? $attributes['fields'] : '',
			'view'       => isset( $attributes['view'] ) ? $attributes['view'] : '',
			'photos'     => ( ! isset( $attributes['photos'] ) || $attributes['photos'] ) ? 'yes' : 'no',
			'photo_size' => isset( $attributes['photoSize'] ) ? (int) $attributes['photoSize'] : 116,
			'filters'    => ( ! isset( $attributes['filters'] ) || $attributes['filters'] ) ? 'yes' : 'no',
		);

		$output = $this->shortcode->render( $atts );

		// In the editor preview, never return an empty string (ServerSideRender
		// shows a generic "not available" notice for empty output).
		if ( '' === trim( (string) $output ) && $this->is_editor_preview() ) {
			return '<div class="comsup-supporters comsup-supporters--empty"><p>' . esc_html__( 'Nothing to preview yet. Add your Airtable token in the Community Supporters Directory menu, or adjust the filters.', 'community-supporters' ) . '</p></div>';
		}

		return $output;
	}

	/**
	 * Detect the block-renderer REST request used for editor previews.
	 *
	 * @return bool
	 */
	private function is_editor_preview() {
		return defined( 'REST_REQUEST' ) && REST_REQUEST;
	}
}
