<?php
/**
 * [community_supporters] shortcode.
 *
 * @package CommunitySupportersDirectory
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers and renders the front-end shortcode.
 */
class COMSUP_Shortcode {

	/**
	 * Fields shown by default (in order) when the shortcode/block doesn't
	 * specify its own `fields` list.
	 *
	 * @var string[]
	 */
	const DEFAULT_FIELDS = array(
		'Full Name',
		'Role Type',
		'Region',
		'Country',
		'Languages',
		'WordPress profile',
		'Employer',
		'Sponsored By',
	);

	/**
	 * Supporters are shown when this field equals STATUS_ACTIVE.
	 *
	 * @var string
	 */
	const STATUS_FIELD = 'Status';

	/**
	 * The value of the status field that marks a supporter as active.
	 *
	 * @var string
	 */
	const STATUS_ACTIVE = 'Active';

	/**
	 * Who the supporter works for. Powers the Employer filter.
	 *
	 * Named "Sponsor Company Name" until 2026-07-31. That field conflated an
	 * employer with a Five-for-the-Future sponsor, so an Automattic-sponsored
	 * contributor who works somewhere else read as Automattic staff, and every
	 * filled row got a "Sponsored" badge that said nothing useful. The question
	 * being answered is "who do they work for", so the field says that.
	 *
	 * @var string
	 */
	const EMPLOYER_FIELD = 'Employer';

	/**
	 * Field used for the Country filter.
	 *
	 * @var string
	 */
	const COUNTRY_FIELD = 'Country';

	/**
	 * Field used for the Language filter (multi-value).
	 *
	 * @var string
	 */
	const LANGUAGE_FIELD = 'Languages';

	/**
	 * Whether the stylesheet has been registered.
	 *
	 * @var bool
	 */
	private $assets_registered = false;

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_shortcode( 'community_supporters', array( $this, 'render' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );
	}

	/**
	 * Register (but don't enqueue) the stylesheet. It's enqueued on demand
	 * when the shortcode actually runs, so pages without it stay lean.
	 */
	public function register_assets() {
		wp_register_style(
			'community-supporters',
			COMSUP_PLUGIN_URL . 'assets/css/community-supporters.css',
			array(),
			COMSUP_VERSION
		);
		wp_register_script(
			'community-supporters-filters',
			COMSUP_PLUGIN_URL . 'assets/js/filters.js',
			array(),
			COMSUP_VERSION,
			true
		);

		// Bundled Leaflet (same version as the Education Programs Map plugin) and
		// the supporter-countries map script.
		wp_register_style( 'comsup-leaflet', COMSUP_PLUGIN_URL . 'assets/vendor/leaflet/leaflet.css', array(), '1.9.4' );
		wp_register_script( 'comsup-leaflet', COMSUP_PLUGIN_URL . 'assets/vendor/leaflet/leaflet.js', array(), '1.9.4', true );
		wp_register_script(
			'community-supporters-map',
			COMSUP_PLUGIN_URL . 'assets/js/map.js',
			array( 'comsup-leaflet' ),
			COMSUP_VERSION,
			true
		);

		$this->assets_registered = true;
	}

	/**
	 * Render the shortcode.
	 *
	 * @param array|string $atts Shortcode attributes.
	 * @return string HTML output.
	 */
	public function render( $atts ) {
		$atts = shortcode_atts(
			array(
				'layout'     => 'grid',
				'limit'      => 0,
				'columns'    => 3,
				'country'    => '',
				'language'   => '',
				'search'     => '',
				'fields'     => '',
				'view'       => '',
				'photos'     => 'yes',
				'photo_size' => 116,
				'filters'    => 'yes',
				'map'        => '', // Empty = inherit the "Country map" setting.
			),
			$atts,
			'community_supporters'
		);

		$show_photos  = filter_var( $atts['photos'], FILTER_VALIDATE_BOOLEAN );
		$photo_size   = max( 24, min( 512, (int) $atts['photo_size'] ) );
		$show_filters = filter_var( $atts['filters'], FILTER_VALIDATE_BOOLEAN );

		// Make sure the stylesheet is available even for late-registered contexts.
		if ( ! wp_style_is( 'community-supporters', 'registered' ) ) {
			$this->register_assets();
		}
		wp_enqueue_style( 'community-supporters' );

		$settings = COMSUP_Settings::get();

		// The map defaults to the global setting; the "map" attribute overrides it.
		$show_map = '' === trim( (string) $atts['map'] )
			? ! empty( $settings['show_map'] )
			: filter_var( $atts['map'], FILTER_VALIDATE_BOOLEAN );
		$client   = new COMSUP_Airtable_Client( $settings );

		$requested_fields = $this->parse_list( $atts['fields'] );
		if ( empty( $requested_fields ) ) {
			// Restrict to the curated field set by default.
			$requested_fields = apply_filters( 'comsup_default_fields', self::DEFAULT_FIELDS );
		}

		// Fetch every field (not just the displayed ones) so we can filter on
		// Status, employer and location regardless of which fields are shown.
		$records = $client->get_records(
			array(
				'view'        => sanitize_text_field( $atts['view'] ),
				'max_records' => 0, // Filter locally, then apply the limit.
			)
		);

		if ( is_wp_error( $records ) ) {
			return $this->render_error( $records->get_error_message() );
		}

		// Only show supporters whose Status is active.
		$records = $this->only_active( $records );

		$records = $this->filter_records( $records, $atts );

		$limit = (int) $atts['limit'];
		if ( $limit > 0 ) {
			$records = array_slice( $records, 0, $limit );
		}

		if ( empty( $records ) ) {
			return '<div class="comsup-supporters comsup-supporters--empty"><p>' . esc_html__( 'No supporters to display.', 'community-supporters' ) . '</p></div>';
		}

		$field_order = $this->resolve_field_order( $records, $requested_fields );

		if ( 'table' === $atts['layout'] ) {
			$list = $this->render_table( $records, $field_order, $show_photos, $photo_size );
		} else {
			$list = $this->render_grid( $records, $field_order, (int) $atts['columns'], $show_photos, $photo_size );
		}

		$map = $show_map ? COMSUP_Map::render( $records ) : '';

		// No map and no filters: return the bare list.
		if ( ! $show_filters && '' === $map ) {
			return $list;
		}

		// The filter script powers the filter bar and applies map clicks; load it
		// whenever the map or the filter bar is present.
		if ( ! wp_script_is( 'community-supporters-filters', 'registered' ) ) {
			$this->register_assets();
		}
		wp_enqueue_script( 'community-supporters-filters' );

		// The map needs Leaflet and the map script.
		if ( '' !== $map ) {
			wp_enqueue_style( 'comsup-leaflet' );
			wp_enqueue_script( 'comsup-leaflet' );
			wp_enqueue_script( 'community-supporters-map' );
		}

		$inner = $map;

		if ( $show_filters ) {
			$inner .= $this->render_filter_bar( $records );
			$inner .= $list;
			$inner .= '<p class="comsup-supporters__noresults" hidden>' . esc_html__( 'No supporters match the selected filters.', 'community-supporters' ) . '</p>';
		} else {
			$inner .= $list;
		}

		return '<div class="comsup-supporters-wrap">' . $inner . '</div>';
	}

	/**
	 * Keep only supporters whose Status field marks them as active.
	 *
	 * @param array $records Records from Airtable.
	 * @return array
	 */
	private function only_active( array $records ) {
		$field  = apply_filters( 'comsup_status_field', self::STATUS_FIELD );
		$active = apply_filters( 'comsup_status_active_value', self::STATUS_ACTIVE );

		return array_values(
			array_filter(
				$records,
				function ( $record ) use ( $field, $active ) {
					$fields = isset( $record['fields'] ) ? $record['fields'] : array();
					if ( ! isset( $fields[ $field ] ) ) {
						return false;
					}
					$value = is_array( $fields[ $field ] ) ? implode( ', ', $fields[ $field ] ) : (string) $fields[ $field ];
					return 0 === strcasecmp( trim( $value ), trim( (string) $active ) );
				}
			)
		);
	}

	/**
	 * The supporter's employer, or '' when unknown.
	 *
	 * @param array $fields Record fields.
	 * @return string
	 */
	private function employer_of( array $fields ) {
		if ( ! isset( $fields[ self::EMPLOYER_FIELD ] ) ) {
			return '';
		}
		$value = $fields[ self::EMPLOYER_FIELD ];
		if ( is_array( $value ) ) {
			$value = implode( ', ', array_filter( $value, 'strlen' ) );
		}
		return trim( (string) $value );
	}

	/**
	 * A filter token for an employer name, so the select and the cards agree.
	 *
	 * @param string $employer Employer name.
	 * @return string
	 */
	private function employer_token( $employer ) {
		return strtolower( trim( (string) $employer ) );
	}

	/**
	 * Which contribution bucket a supporter falls into, from checkable facts:
	 *
	 *  - 'a8c'       employer is Automattic (their community work is the day job)
	 *  - 'sponsored' a Five for the Future pledge covers their time ("Sponsored By",
	 *                parsed from the pledge block on their own .org profile)
	 *  - 'volunteer' their .org profile was checked and shows no pledge
	 *  - ''          no profile on file, so nothing could be checked
	 *
	 * Absence of a pledge is only meaningful when there was a profile to check,
	 * which is why the unknown bucket exists instead of defaulting to volunteer.
	 *
	 * @param array $fields Record fields.
	 * @return string
	 */
	private function contribution_of( array $fields ) {
		$employer = $this->employer_of( $fields );
		if ( 0 === strcasecmp( $employer, 'Automattic' ) ) {
			return 'a8c';
		}
		$sponsor = isset( $fields['Sponsored By'] ) ? trim( (string) $fields['Sponsored By'] ) : '';
		if ( '' !== $sponsor ) {
			return 'sponsored';
		}
		$profile = isset( $fields['WordPress profile'] ) ? trim( (string) $fields['WordPress profile'] ) : '';
		return '' !== $profile ? 'volunteer' : '';
	}

	/**
	 * The chip shown under a supporter's name for their contribution bucket.
	 *
	 * @param string $bucket Bucket from contribution_of().
	 * @return string HTML, '' when there is nothing worth saying.
	 */
	private function contribution_chip( $bucket ) {
		if ( 'sponsored' === $bucket ) {
			return '<span class="comsup-chip comsup-chip--sponsored">' . esc_html__( 'Sponsored contributor', 'community-supporters' ) . '</span>';
		}
		if ( 'volunteer' === $bucket ) {
			return '<span class="comsup-chip comsup-chip--volunteer">' . esc_html__( 'Volunteer', 'community-supporters' ) . '</span>';
		}
		return '';
	}

	/**
	 * Role tokens for a record (lowercased Role Type values).
	 *
	 * @param array $fields Record fields.
	 * @return string[]
	 */
	private function record_roles( array $fields ) {
		if ( ! isset( $fields['Role Type'] ) ) {
			return array();
		}
		$value = $fields['Role Type'];
		$items = is_array( $value ) ? $value : explode( ',', (string) $value );
		$out   = array();
		foreach ( $items as $item ) {
			$item = strtolower( trim( $item ) );
			if ( '' !== $item ) {
				$out[] = $item;
			}
		}
		return $out;
	}

	/**
	 * The list of languages for a record.
	 *
	 * @param array $fields Record fields.
	 * @return string[]
	 */
	private function record_languages( array $fields ) {
		if ( ! isset( $fields[ self::LANGUAGE_FIELD ] ) ) {
			return array();
		}
		$value = $fields[ self::LANGUAGE_FIELD ];
		$items = is_array( $value ) ? $value : explode( ',', (string) $value );
		return array_values( array_filter( array_map( 'trim', $items ), 'strlen' ) );
	}

	/**
	 * Build the data-* attributes used by the client-side filter.
	 *
	 * @param array  $fields   Record fields.
	 * @param string $employer The supporter's employer, '' when unknown.
	 * @return string
	 */
	private function filter_data_attrs( array $fields, $employer ) {
		$langs = array();
		foreach ( $this->record_languages( $fields ) as $lang ) {
			$langs[] = strtolower( $lang );
		}
		// Wrap each value in pipes for token matching in JS.
		$lang_attr    = empty( $langs ) ? '' : '|' . implode( '|', $langs ) . '|';
		$country_keys = array_keys( $this->country_tokens( $fields ) );
		$country_attr = empty( $country_keys ) ? '' : '|' . implode( '|', $country_keys ) . '|';

		$roles     = $this->record_roles( $fields );
		$role_attr = empty( $roles ) ? '' : '|' . implode( '|', $roles ) . '|';

		return sprintf(
			'data-employer="%1$s" data-countries="%2$s" data-languages="%3$s" data-roles="%4$s" data-contrib="%5$s"',
			esc_attr( $this->employer_token( $employer ) ),
			esc_attr( $country_attr ),
			esc_attr( $lang_attr ),
			esc_attr( $role_attr ),
			esc_attr( $this->contribution_of( $fields ) )
		);
	}

	/**
	 * Resolve a record's countries into token => label pairs.
	 *
	 * Token is the map country id when known (so "United States" and
	 * "United States of America" collapse to one), else the lowercased name.
	 * Multi-country values (comma separated) yield multiple entries.
	 *
	 * @param array $fields Record fields.
	 * @return array token => display label
	 */
	private function country_tokens( array $fields ) {
		if ( ! isset( $fields[ self::COUNTRY_FIELD ] ) ) {
			return array();
		}
		$raw = is_array( $fields[ self::COUNTRY_FIELD ] ) ? implode( ',', $fields[ self::COUNTRY_FIELD ] ) : (string) $fields[ self::COUNTRY_FIELD ];

		$out = array();
		foreach ( explode( ',', $raw ) as $piece ) {
			$piece = trim( $piece );
			if ( '' === $piece ) {
				continue;
			}
			$id            = COMSUP_Map::resolve_id( $piece );
			$token         = '' !== $id ? $id : strtolower( $piece );
			$label         = ( '' !== $id && '' !== COMSUP_Map::label( $id ) ) ? COMSUP_Map::label( $id ) : $piece;
			$out[ $token ] = $label;
		}
		return $out;
	}

	/**
	 * Render the interactive filter bar (Employer / Language / Country).
	 *
	 * @param array $records Records being displayed.
	 * @return string
	 */
	private function render_filter_bar( array $records ) {
		$countries = array();
		$languages = array();
		$employers = array();
		$roles     = array();
		$contribs  = array();

		foreach ( $records as $record ) {
			$fields = isset( $record['fields'] ) ? $record['fields'] : array();

			$employer = $this->employer_of( $fields );
			if ( '' !== $employer ) {
				$employers[ $this->employer_token( $employer ) ] = $employer;
			}

			foreach ( $this->record_roles( $fields ) as $role ) {
				$roles[ $role ] = ucwords( $role );
			}

			$bucket = $this->contribution_of( $fields );
			if ( '' !== $bucket ) {
				$contribs[ $bucket ] = true;
			}

			foreach ( $this->country_tokens( $fields ) as $token => $label ) {
				$countries[ $token ] = $label;
			}

			foreach ( $this->record_languages( $fields ) as $lang ) {
				$languages[ strtolower( $lang ) ] = $lang;
			}
		}

		asort( $countries, SORT_NATURAL | SORT_FLAG_CASE );
		asort( $languages, SORT_NATURAL | SORT_FLAG_CASE );
		asort( $employers, SORT_NATURAL | SORT_FLAG_CASE );

		$controls = '';

		// Role filter — the official Community Team roles (Event Supporter,
		// Program Supporter, Program Manager), straight from the data.
		if ( count( $roles ) > 1 ) {
			$role_order   = array( 'program manager', 'program supporter', 'event supporter' );
			$role_options = array( '' => __( 'All roles', 'community-supporters' ) );
			foreach ( $role_order as $key ) {
				if ( isset( $roles[ $key ] ) ) {
					$role_options[ $key ] = $roles[ $key ];
					unset( $roles[ $key ] );
				}
			}
			foreach ( $roles as $key => $label ) {
				$role_options[ $key ] = $label;
			}
			$controls .= $this->filter_select( 'role', __( 'Role', 'community-supporters' ), $role_options );
		}

		// Contribution filter — how their time is funded, from checkable facts
		// (Five for the Future pledges + stated employer).
		if ( count( $contribs ) > 1 ) {
			$contrib_options = array( '' => __( 'Everyone', 'community-supporters' ) );
			if ( isset( $contribs['volunteer'] ) ) {
				$contrib_options['volunteer'] = __( 'Volunteers', 'community-supporters' );
			}
			if ( isset( $contribs['sponsored'] ) ) {
				$contrib_options['sponsored'] = __( 'Sponsored contributors', 'community-supporters' );
			}
			if ( isset( $contribs['a8c'] ) ) {
				$contrib_options['a8c'] = __( 'Automattic employees', 'community-supporters' );
			}
			$controls .= $this->filter_select( 'contrib', __( 'Contribution', 'community-supporters' ), $contrib_options );
		}

		// Employer filter. Karen's question was "who do they work for", so the
		// useful control is a list of employers, not a sponsored yes/no.
		if ( count( $employers ) > 1 ) {
			$employer_options = array( '' => __( 'All employers', 'community-supporters' ) );
			foreach ( $employers as $key => $label ) {
				$employer_options[ $key ] = $label;
			}
			$controls .= $this->filter_select( 'employer', __( 'Employer', 'community-supporters' ), $employer_options );
		}

		if ( count( $languages ) > 1 ) {
			$lang_options = array( '' => __( 'All languages', 'community-supporters' ) );
			foreach ( $languages as $key => $label ) {
				$lang_options[ $key ] = $label;
			}
			$controls .= $this->filter_select( 'language', __( 'Language', 'community-supporters' ), $lang_options );
		}

		if ( count( $countries ) > 1 ) {
			$country_options = array( '' => __( 'All countries', 'community-supporters' ) );
			foreach ( $countries as $key => $label ) {
				$country_options[ $key ] = $label;
			}
			$controls .= $this->filter_select( 'country', __( 'Country', 'community-supporters' ), $country_options );
		}

		if ( '' === $controls ) {
			return ''; // Nothing meaningful to filter by.
		}

		return '<div class="comsup-filters">' . $controls . '</div>';
	}

	/**
	 * Render one labelled filter <select>.
	 *
	 * @param string $key     Filter key (employer|language|country).
	 * @param string $label   Visible label.
	 * @param array  $options value => label pairs.
	 * @return string
	 */
	private function filter_select( $key, $label, array $options ) {
		$id   = 'comsup-filter-' . $key;
		$html = '<div class="comsup-filters__field"><label class="comsup-filters__label" for="' . esc_attr( $id ) . '">' . esc_html( $label ) . '</label>';
		$html .= '<select class="comsup-filters__select" id="' . esc_attr( $id ) . '" data-comsup-filter="' . esc_attr( $key ) . '">';
		foreach ( $options as $value => $text ) {
			$html .= '<option value="' . esc_attr( $value ) . '">' . esc_html( $text ) . '</option>';
		}
		$html .= '</select></div>';
		return $html;
	}

	/**
	 * Apply country/language/search filters to the records.
	 *
	 * @param array $records Records from Airtable.
	 * @param array $atts    Shortcode attributes.
	 * @return array
	 */
	private function filter_records( array $records, array $atts ) {
		$country  = trim( (string) $atts['country'] );
		$language = trim( (string) $atts['language'] );
		$search   = trim( (string) $atts['search'] );

		if ( '' === $country && '' === $language && '' === $search ) {
			return $records;
		}

		return array_values(
			array_filter(
				$records,
				function ( $record ) use ( $country, $language, $search ) {
					$fields = isset( $record['fields'] ) ? $record['fields'] : array();

					if ( '' !== $country && ! $this->field_contains( $fields, 'Country', $country ) ) {
						return false;
					}
					if ( '' !== $language && ! $this->field_contains( $fields, 'Languages', $language ) ) {
						return false;
					}
					if ( '' !== $search ) {
						$haystack = strtolower( wp_json_encode( $fields ) );
						if ( false === strpos( $haystack, strtolower( $search ) ) ) {
							return false;
						}
					}
					return true;
				}
			)
		);
	}

	/**
	 * Case-insensitive "does this field's value contain the needle" test.
	 *
	 * @param array  $fields     Record fields.
	 * @param string $field_name Field to inspect.
	 * @param string $needle     Value to look for.
	 * @return bool
	 */
	private function field_contains( array $fields, $field_name, $needle ) {
		if ( ! isset( $fields[ $field_name ] ) ) {
			return false;
		}
		$value = is_array( $fields[ $field_name ] ) ? implode( ', ', $fields[ $field_name ] ) : (string) $fields[ $field_name ];
		return false !== stripos( $value, $needle );
	}

	/**
	 * Determine the ordered list of fields to display.
	 *
	 * @param array $records          Records.
	 * @param array $requested_fields Explicit field list from the shortcode.
	 * @return array
	 */
	private function resolve_field_order( array $records, array $requested_fields ) {
		if ( ! empty( $requested_fields ) ) {
			return $requested_fields;
		}

		// Collect every field name seen across records, preserving first-seen order.
		$order = array();
		foreach ( $records as $record ) {
			if ( empty( $record['fields'] ) ) {
				continue;
			}
			foreach ( array_keys( $record['fields'] ) as $name ) {
				if ( ! in_array( $name, $order, true ) ) {
					$order[] = $name;
				}
			}
		}
		return $order;
	}

	/**
	 * Render the card-grid layout.
	 *
	 * @param array $records     Records.
	 * @param array $field_order Ordered field names.
	 * @param int   $columns     Column count (1-6).
	 * @return string
	 */
	private function render_grid( array $records, array $field_order, $columns, $show_photos = true, $photo_size = 116 ) {
		$columns = min( 6, max( 1, (int) $columns ) );

		// Every card renders the same fixed set of vertical slots — one per
		// field (empty placeholder when a value is missing), plus a photo slot
		// and a place slot — so CSS subgrid can line the fields up on shared
		// row tracks across the cards in each row.
		$rows = count( $field_order ) + 2; // Fields + place slot + contribution chip slot.
		if ( $show_photos ) {
			$rows++;
		}

		$html = '<div class="comsup-supporters comsup-supporters--grid" style="--comsup-columns:' . esc_attr( $columns ) . ';">';
		foreach ( $records as $record ) {
			$fields   = isset( $record['fields'] ) ? $record['fields'] : array();
			$employer = $this->employer_of( $fields );
			$html    .= '<article class="comsup-card" style="--comsup-rows:' . esc_attr( $rows ) . ';" ' . $this->filter_data_attrs( $fields, $employer ) . '>';

			if ( $show_photos ) {
				$html .= $this->render_photo( $fields, $photo_size );
			}

			foreach ( $field_order as $name ) {
				$value = array_key_exists( $name, $fields ) ? $fields[ $name ] : '';
				$role  = $this->field_role( $name );

				if ( '' === $value || array() === $value ) {
					$html .= '<div class="comsup-card__row comsup-card__row--empty" aria-hidden="true"></div>';
				} else {
					$html .= $this->render_card_field( $name, $value, $role );
				}

				// Under the name, show where they actually are. This directory
				// is used to find the nearest person, so the city earns that
				// slot far more than a badge does. Empty placeholder when we
				// don't know, to keep the rows aligned across cards.
				if ( 'title' === $role ) {
					$city = isset( $fields['City'] ) ? trim( (string) $fields['City'] ) : '';
					$html .= '' !== $city
						? '<div class="comsup-card__row comsup-card__row--place"><span class="comsup-card__place">' . esc_html( $city ) . '</span></div>'
						: '<div class="comsup-card__row comsup-card__row--empty" aria-hidden="true"></div>';

					$chip  = $this->contribution_chip( $this->contribution_of( $fields ) );
					$html .= '' !== $chip
						? '<div class="comsup-card__row comsup-card__row--contrib">' . $chip . '</div>'
						: '<div class="comsup-card__row comsup-card__row--empty" aria-hidden="true"></div>';
				}
			}

			$html .= '</article>';
		}
		$html .= '</div>';

		return $html;
	}

	/**
	 * Render one field inside a card, styled per its role.
	 *
	 * @param string       $name  Field name.
	 * @param string|array $value Field value.
	 * @param string       $role  Field role.
	 * @return string
	 */
	private function render_card_field( $name, $value, $role ) {
		switch ( $role ) {
			case 'title':
				return '<h3 class="comsup-card__title">' . esc_html( $this->stringify( $value ) ) . '</h3>';

			case 'badge':
				return '<div class="comsup-card__row comsup-card__row--badge"><span class="comsup-badge">' . esc_html( $this->stringify( $value ) ) . '</span></div>';

			case 'tags':
				return '<div class="comsup-card__row comsup-card__row--tags"><span class="comsup-card__label">' . esc_html( $name ) . '</span>' . $this->render_tags( $value ) . '</div>';

			case 'link':
				$url = esc_url( $this->stringify( $value ) );
				if ( '' === $url ) {
					// Keep a slot so subgrid alignment stays consistent.
					return '<div class="comsup-card__row comsup-card__row--empty" aria-hidden="true"></div>';
				}
				return '<div class="comsup-card__row comsup-card__row--link"><a class="comsup-card__link" href="' . $url . '" target="_blank" rel="noopener noreferrer">' . esc_html( $name ) . ' <span aria-hidden="true">↗</span></a></div>';

			default:
				return '<div class="comsup-card__row"><span class="comsup-card__label">' . esc_html( $name ) . '</span><span class="comsup-card__value">' . esc_html( $this->stringify( $value ) ) . '</span></div>';
		}
	}

	/**
	 * Render a supporter's profile photo, resolved from their WordPress.org profile.
	 *
	 * @param array $fields  Record fields.
	 * @param int   $size    Requested image size in pixels.
	 * @param bool  $wrapped Whether to wrap the image in the card photo container.
	 * @return string
	 */
	private function render_photo( array $fields, $display_size, $wrapped = true ) {
		$display_size = max( 24, (int) $display_size );
		$name         = $this->find_name( $fields );
		$profile_url  = $this->find_profile_url( $fields );

		// Request at 2x for crisp rendering on high-DPI screens (Gravatar caps at 512).
		$avatar = '' !== $profile_url ? COMSUP_Avatars::get_url( $profile_url, min( 512, $display_size * 2 ) ) : '';

		if ( '' === $avatar ) {
			// Not resolved yet (or no profile): show an initials placeholder so the
			// layout stays consistent while the background job fills in real photos.
			$media = $this->render_photo_placeholder( $name, $display_size );
		} else {
			$alt = '' !== $name
				/* translators: %s: supporter name. */
				? sprintf( __( 'Profile photo of %s', 'community-supporters' ), $name )
				: __( 'Supporter profile photo', 'community-supporters' );

			$media = sprintf(
				'<img class="comsup-photo" src="%1$s" width="%2$d" height="%2$d" style="width:%2$dpx;height:%2$dpx;" alt="%3$s" loading="lazy" decoding="async" />',
				esc_url( $avatar ),
				(int) $display_size,
				esc_attr( $alt )
			);
		}

		return $wrapped ? '<div class="comsup-card__photo">' . $media . '</div>' : $media;
	}

	/**
	 * Render an initials placeholder circle for a supporter without a resolved photo.
	 *
	 * @param string $name Supporter name.
	 * @param int    $size Size in pixels.
	 * @return string
	 */
	private function render_photo_placeholder( $name, $size ) {
		$initials = '';
		foreach ( preg_split( '/\s+/', trim( (string) $name ) ) as $word ) {
			if ( '' !== $word ) {
				$initials .= function_exists( 'mb_substr' ) ? mb_substr( $word, 0, 1 ) : substr( $word, 0, 1 );
			}
			if ( 2 <= strlen( $initials ) ) {
				break;
			}
		}
		$initials = strtoupper( $initials );

		return sprintf(
			'<span class="comsup-photo comsup-photo--placeholder" style="width:%1$dpx;height:%1$dpx;font-size:%2$dpx;" aria-hidden="true">%3$s</span>',
			(int) $size,
			(int) max( 12, round( $size / 2.5 ) ),
			esc_html( $initials )
		);
	}

	/**
	 * Find the supporter's WordPress.org profile URL in a record.
	 *
	 * Scans every field for a wordpress.org URL, preferring profiles.wordpress.org.
	 * This avoids picking up other link fields (LinkedIn, personal sites, etc.),
	 * which sort before "WordPress profile" for some supporters.
	 *
	 * @param array $fields Record fields.
	 * @return string
	 */
	private function find_profile_url( array $fields ) {
		$fallback = '';

		foreach ( $fields as $value ) {
			$url = $this->stringify( $value );
			if ( '' === $url || ! preg_match( '#^https?://#i', $url ) ) {
				continue;
			}

			$host = wp_parse_url( $url, PHP_URL_HOST );
			if ( ! $host || ! preg_match( '/(^|\.)wordpress\.org$/i', $host ) ) {
				continue;
			}

			if ( 0 === strcasecmp( $host, 'profiles.wordpress.org' ) ) {
				return $url; // Best match — the canonical profile host.
			}

			if ( '' === $fallback ) {
				$fallback = $url; // Any other *.wordpress.org URL.
			}
		}

		return $fallback;
	}

	/**
	 * Find the supporter's display name (first field whose role is "title").
	 *
	 * @param array $fields Record fields.
	 * @return string
	 */
	private function find_name( array $fields ) {
		foreach ( $fields as $name => $value ) {
			if ( 'title' === $this->field_role( $name ) ) {
				return $this->stringify( $value );
			}
		}
		return '';
	}

	/**
	 * Render a comma/array value as a set of tag pills.
	 *
	 * @param string|array $value Value to render.
	 * @return string
	 */
	private function render_tags( $value ) {
		$items = is_array( $value ) ? $value : array_map( 'trim', explode( ',', (string) $value ) );
		$items = array_filter( array_map( 'trim', $items ), 'strlen' );

		if ( empty( $items ) ) {
			return '';
		}

		$html = '<span class="comsup-tags">';
		foreach ( $items as $item ) {
			$html .= '<span class="comsup-tag">' . esc_html( $item ) . '</span>';
		}
		$html .= '</span>';
		return $html;
	}

	/**
	 * Render the table layout.
	 *
	 * @param array $records     Records.
	 * @param array $field_order Ordered field names.
	 * @return string
	 */
	private function render_table( array $records, array $field_order, $show_photos = true, $photo_size = 116 ) {
		$html  = '<div class="comsup-supporters comsup-supporters--table-wrap">';
		$html .= '<table class="comsup-table">';
		$html .= '<thead><tr>';
		if ( $show_photos ) {
			$html .= '<th scope="col" class="comsup-table__photo-col"><span class="screen-reader-text">' . esc_html__( 'Photo', 'community-supporters' ) . '</span></th>';
		}
		foreach ( $field_order as $name ) {
			$html .= '<th scope="col">' . esc_html( $name ) . '</th>';
		}
		$html .= '</tr></thead><tbody>';

		foreach ( $records as $record ) {
			$fields   = isset( $record['fields'] ) ? $record['fields'] : array();
			$employer = $this->employer_of( $fields );
			$html    .= '<tr ' . $this->filter_data_attrs( $fields, $employer ) . '>';
			if ( $show_photos ) {
				$html .= '<td class="comsup-table__photo-col" data-label="' . esc_attr__( 'Photo', 'community-supporters' ) . '">' . $this->render_photo( $fields, 40, false ) . '</td>';
			}
			foreach ( $field_order as $name ) {
				$value = array_key_exists( $name, $fields ) ? $fields[ $name ] : '';
				$role  = $this->field_role( $name );
				$cell  = $this->render_table_cell( $value, $role );

				// Show the city under the name, same as the card layout.
				if ( 'title' === $role ) {
					$city = isset( $fields['City'] ) ? trim( (string) $fields['City'] ) : '';
					if ( '' !== $city ) {
						$cell .= '<span class="comsup-card__place">' . esc_html( $city ) . '</span>';
					}
				}
				$html .= '<td data-label="' . esc_attr( $name ) . '">' . $cell . '</td>';
			}
			$html .= '</tr>';
		}

		$html .= '</tbody></table></div>';
		return $html;
	}

	/**
	 * Render a single table cell.
	 *
	 * @param string|array $value Value.
	 * @param string       $role  Field role.
	 * @return string
	 */
	private function render_table_cell( $value, $role ) {
		if ( '' === $value || array() === $value ) {
			return '';
		}
		if ( 'link' === $role ) {
			$url = esc_url( $this->stringify( $value ) );
			if ( '' === $url ) {
				return '';
			}
			return '<a href="' . $url . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'View', 'community-supporters' ) . ' <span aria-hidden="true">↗</span></a>';
		}
		if ( 'tags' === $role ) {
			return $this->render_tags( $value );
		}
		if ( 'badge' === $role ) {
			return '<span class="comsup-badge">' . esc_html( $this->stringify( $value ) ) . '</span>';
		}
		return esc_html( $this->stringify( $value ) );
	}

	/**
	 * Map a field name to a display role. Known Community Supporters fields get
	 * tailored treatment; anything else falls back to plain text.
	 *
	 * @param string $name Field name.
	 * @return string One of: title, badge, tags, link, text.
	 */
	private function field_role( $name ) {
		$key = strtolower( trim( $name ) );

		$map = array(
			'full name'         => 'title',
			'name'              => 'title',
			'role type'         => 'badge',
			'role'              => 'badge',
			'region'            => 'badge',
			'country'           => 'badge',
			'languages'         => 'tags',
			'language'          => 'tags',
			'slack channels'    => 'tags',
			'wordpress profile' => 'link',
			'profile'           => 'link',
		);

		if ( isset( $map[ $key ] ) ) {
			return $map[ $key ];
		}

		if ( false !== strpos( $key, 'sponsor' ) ) {
			return 'tags';
		}
		if ( false !== strpos( $key, 'profile' ) || false !== strpos( $key, 'url' ) || false !== strpos( $key, 'website' ) || false !== strpos( $key, 'link' ) ) {
			return 'link';
		}

		return apply_filters( 'comsup_field_role', 'text', $name );
	}

	/**
	 * Coerce any value into a display string.
	 *
	 * @param mixed $value Value.
	 * @return string
	 */
	private function stringify( $value ) {
		if ( is_array( $value ) ) {
			return implode( ', ', array_map( array( $this, 'stringify' ), $value ) );
		}
		return trim( (string) $value );
	}

	/**
	 * Parse a comma-separated attribute into a trimmed list.
	 *
	 * @param string $raw Raw attribute value.
	 * @return array
	 */
	private function parse_list( $raw ) {
		if ( '' === trim( (string) $raw ) ) {
			return array();
		}
		return array_values( array_filter( array_map( 'trim', explode( ',', $raw ) ), 'strlen' ) );
	}

	/**
	 * Render an admin-only error notice; visitors see nothing.
	 *
	 * @param string $message Error message.
	 * @return string
	 */
	private function render_error( $message ) {
		if ( current_user_can( 'manage_options' ) ) {
			return '<div class="comsup-supporters comsup-supporters--error"><strong>' . esc_html__( 'Community Supporters Directory:', 'community-supporters' ) . '</strong> ' . esc_html( $message ) . '</div>';
		}
		return '';
	}
}
