<?php
/**
 * Admin settings page.
 *
 * @package CommunitySupportersDirectory
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the "Community Supporters Directory" settings page and stores the plugin options.
 */
class COMSUP_Settings {

	const OPTION_KEY = 'comsup_settings';

	/**
	 * Constructor. Hooks into WordPress admin.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_post_comsup_refresh_data', array( $this, 'handle_refresh_data' ) );
	}

	/**
	 * Handle the "Refresh data" button: clear the cached Airtable records.
	 */
	public function handle_refresh_data() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do that.', 'community-supporters' ) );
		}
		check_admin_referer( 'comsup_refresh_data' );

		COMSUP_Airtable_Client::flush_cache();

		wp_safe_redirect( add_query_arg( 'comsup_refreshed', '1', admin_url( 'admin.php?page=community-supporters' ) ) );
		exit;
	}

	/**
	 * Default settings. Base ID and Table ID start blank until a Community
	 * Supporters Airtable base exists — see FUTURE_COREY.md for the proposed schema.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'api_token' => '',
			'base_id'   => '',
			'table_id'  => '',
			'view_id'   => '',
			'cache_ttl' => HOUR_IN_SECONDS,
			'show_map'  => true,
		);
	}

	/**
	 * Get the merged plugin settings.
	 *
	 * @return array
	 */
	public static function get() {
		$saved = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $saved ) ) {
			$saved = array();
		}
		return wp_parse_args( $saved, self::defaults() );
	}

	/**
	 * Register the top-level admin menu item.
	 */
	public function add_menu() {
		add_menu_page(
			__( 'Community Supporters Directory', 'community-supporters' ),
			__( 'Community Supporters Directory', 'community-supporters' ),
			'manage_options',
			'community-supporters',
			array( $this, 'render_page' ),
			'dashicons-groups',
			58
		);
	}

	/**
	 * Register the setting, section and fields via the Settings API.
	 */
	public function register_settings() {
		register_setting(
			'comsup_settings_group',
			self::OPTION_KEY,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize' ),
				'default'           => self::defaults(),
			)
		);

		add_settings_section(
			'comsup_main_section',
			__( 'Airtable connection', 'community-supporters' ),
			array( $this, 'render_section_intro' ),
			'community-supporters'
		);

		$fields = array(
			'api_token' => __( 'Personal Access Token', 'community-supporters' ),
			'base_id'   => __( 'Base ID', 'community-supporters' ),
			'table_id'  => __( 'Table ID or name', 'community-supporters' ),
			'view_id'   => __( 'View ID or name (optional)', 'community-supporters' ),
			'cache_ttl' => __( 'Cache lifetime (seconds)', 'community-supporters' ),
		);

		foreach ( $fields as $key => $label ) {
			add_settings_field(
				'comsup_field_' . $key,
				$label,
				array( $this, 'render_field' ),
				'community-supporters',
				'comsup_main_section',
				array(
					'key'       => $key,
					'label_for' => 'comsup_field_' . $key,
				)
			);
		}

		add_settings_section(
			'comsup_display_section',
			__( 'Display', 'community-supporters' ),
			'__return_false',
			'community-supporters'
		);

		add_settings_field(
			'comsup_field_show_map',
			__( 'Country map', 'community-supporters' ),
			array( $this, 'render_field' ),
			'community-supporters',
			'comsup_display_section',
			array(
				'key'       => 'show_map',
				'label_for' => 'comsup_field_show_map',
			)
		);
	}

	/**
	 * Section description.
	 */
	public function render_section_intro() {
		echo '<p>' . wp_kses_post(
			__( 'Create a read-only <strong>Personal Access Token</strong> at <a href="https://airtable.com/create/tokens" target="_blank" rel="noopener">airtable.com/create/tokens</a> with the <code>data.records:read</code> scope and access to your Community Supporters base, then paste it below along with the Base ID and Table ID.', 'community-supporters' )
		) . '</p>';
	}

	/**
	 * Render an individual settings field.
	 *
	 * @param array $args Field args (expects 'key').
	 */
	public function render_field( $args ) {
		$settings = self::get();
		$key      = $args['key'];
		$value    = isset( $settings[ $key ] ) ? $settings[ $key ] : '';
		$name     = self::OPTION_KEY . '[' . $key . ']';
		$id       = 'comsup_field_' . $key;

		switch ( $key ) {
			case 'api_token':
				printf(
					'<input type="password" autocomplete="off" spellcheck="false" id="%1$s" name="%2$s" value="%3$s" class="regular-text" />',
					esc_attr( $id ),
					esc_attr( $name ),
					esc_attr( $value )
				);
				echo '<p class="description">' . esc_html__( 'Stored in your database. Use a token scoped to read-only access.', 'community-supporters' ) . '</p>';
				break;

			case 'cache_ttl':
				printf(
					'<input type="number" min="0" step="60" id="%1$s" name="%2$s" value="%3$s" class="small-text" />',
					esc_attr( $id ),
					esc_attr( $name ),
					esc_attr( $value )
				);
				echo '<p class="description">' . esc_html__( 'How long to cache Airtable results before refetching. Set to 0 to disable caching.', 'community-supporters' ) . '</p>';
				break;

			case 'show_map':
				printf(
					'<label for="%1$s"><input type="checkbox" id="%1$s" name="%2$s" value="1" %3$s /> %4$s</label>',
					esc_attr( $id ),
					esc_attr( $name ),
					checked( ! empty( $value ), true, false ),
					esc_html__( 'Show a world map of supporter countries above the list', 'community-supporters' )
				);
				echo '<p class="description">' . esc_html__( 'Highlights the countries supporters come from. Can be overridden per placement with the shortcode attribute map="yes|no".', 'community-supporters' ) . '</p>';
				break;

			default:
				printf(
					'<input type="text" id="%1$s" name="%2$s" value="%3$s" class="regular-text" spellcheck="false" />',
					esc_attr( $id ),
					esc_attr( $name ),
					esc_attr( $value )
				);
				break;
		}
	}

	/**
	 * Sanitize submitted settings and flush the record cache.
	 *
	 * @param array $input Raw submitted values.
	 * @return array
	 */
	public function sanitize( $input ) {
		$defaults = self::defaults();
		$clean    = array();

		$clean['api_token'] = isset( $input['api_token'] ) ? trim( sanitize_text_field( wp_unslash( $input['api_token'] ) ) ) : '';
		$clean['base_id']   = isset( $input['base_id'] ) ? trim( sanitize_text_field( wp_unslash( $input['base_id'] ) ) ) : $defaults['base_id'];
		$clean['table_id']  = isset( $input['table_id'] ) ? trim( sanitize_text_field( wp_unslash( $input['table_id'] ) ) ) : $defaults['table_id'];
		$clean['view_id']   = isset( $input['view_id'] ) ? trim( sanitize_text_field( wp_unslash( $input['view_id'] ) ) ) : '';
		$clean['cache_ttl'] = isset( $input['cache_ttl'] ) ? max( 0, (int) $input['cache_ttl'] ) : $defaults['cache_ttl'];
		$clean['show_map']  = ! empty( $input['show_map'] );

		// Any settings change may invalidate cached results.
		COMSUP_Airtable_Client::flush_cache();

		return $clean;
	}

	/**
	 * Render the full settings page.
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$refreshed = isset( $_GET['comsup_refreshed'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'Community Supporters Directory', 'community-supporters' ); ?></h1>

			<?php if ( $refreshed ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php echo esc_html__( 'Cached supporter data cleared. The latest records will be fetched from Airtable on the next page view.', 'community-supporters' ); ?></p></div>
			<?php endif; ?>

			<form action="options.php" method="post">
				<?php
				settings_fields( 'comsup_settings_group' );
				do_settings_sections( 'community-supporters' );
				submit_button();
				?>
			</form>

			<hr />

			<h2><?php echo esc_html__( 'Supporter data', 'community-supporters' ); ?></h2>
			<p><?php echo esc_html__( 'Supporter records are fetched from Airtable and cached for speed. Profile photos load directly from WordPress.org in the visitor’s browser, so they don’t need refreshing. To pull the latest records from Airtable now, clear the cache:', 'community-supporters' ); ?></p>
			<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
				<input type="hidden" name="action" value="comsup_refresh_data" />
				<?php wp_nonce_field( 'comsup_refresh_data' ); ?>
				<?php submit_button( __( 'Refresh data', 'community-supporters' ), 'secondary', 'submit', false ); ?>
			</form>

			<hr />

			<h2><?php echo esc_html__( 'How to display supporters', 'community-supporters' ); ?></h2>
			<p><?php echo esc_html__( 'Add this shortcode to any post or page (in the Editor, use a “Shortcode” block):', 'community-supporters' ); ?></p>
			<p><code>[community_supporters]</code></p>
			<p><?php echo esc_html__( 'Optional attributes:', 'community-supporters' ); ?></p>
			<ul style="list-style:disc;margin-left:20px;">
				<li><code>layout="grid|table"</code> — <?php echo esc_html__( 'card grid (default) or a plain table.', 'community-supporters' ); ?></li>
				<li><code>limit="10"</code> — <?php echo esc_html__( 'maximum number of supporters to show.', 'community-supporters' ); ?></li>
				<li><code>columns="3"</code> — <?php echo esc_html__( 'number of columns in grid layout (1–6).', 'community-supporters' ); ?></li>
				<li><code>country="Spain"</code> — <?php echo esc_html__( 'only supporters from a given country.', 'community-supporters' ); ?></li>
				<li><code>language="English"</code> — <?php echo esc_html__( 'only supporters who speak a given language.', 'community-supporters' ); ?></li>
				<li><code>fields="Full Name,Country"</code> — <?php echo esc_html__( 'restrict which fields are shown, in order.', 'community-supporters' ); ?></li>
				<li><code>photos="yes|no"</code> — <?php echo esc_html__( 'show each supporter’s WordPress.org profile photo (default yes).', 'community-supporters' ); ?></li>
				<li><code>photo_size="160"</code> — <?php echo esc_html__( 'profile photo size in pixels.', 'community-supporters' ); ?></li>
				<li><code>filters="yes|no"</code> — <?php echo esc_html__( 'show the front-end filter bar (Sponsorship, Language, Country). Default yes.', 'community-supporters' ); ?></li>
				<li><code>map="yes|no"</code> — <?php echo esc_html__( 'show the country map. Defaults to the Country map setting above.', 'community-supporters' ); ?></li>
			</ul>
			<p><em><?php echo esc_html__( 'Example:', 'community-supporters' ); ?></em> <code>[community_supporters layout="grid" columns="3" language="Spanish"]</code></p>
		</div>
		<?php
	}
}
