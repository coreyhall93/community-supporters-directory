<?php
/**
 * Renders the optional supporter-countries map (Leaflet).
 *
 * @package CommunitySupportersDirectory
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds a Leaflet map with **one dot per supporter**, placed at their own
 * location rather than aggregated into a per-country blob.
 *
 * The point of this map is "who is near me" — someone in South Carolina looking
 * for the nearest person needs to see individuals in relation to each other, so
 * an aggregated count-scaled country circle actively gets in the way. Dots are
 * modest and resize with zoom instead of staying large.
 *
 * Placement honesty: a dot is solid when we resolved a self-reported city, and
 * hollow when we only knew the country and fell back to its centroid. Country
 * data (aliases, labels, representative points) is bundled from Natural Earth
 * (public domain). Only the CARTO map tiles are fetched at runtime, by the
 * visitor's browser — same as the Programs map.
 */
class COMSUP_Map {

	const COUNTRY_FIELD  = 'Country';
	const EMPLOYER_FIELD = 'Employer';
	const MAP_HEIGHT     = '560px';

	/**
	 * Cached country data ([labels, aliases, latlng]).
	 *
	 * @var array|null
	 */
	private static $data = null;

	/**
	 * Running instance counter for unique map element ids.
	 *
	 * @var int
	 */
	private static $instance = 0;

	/**
	 * Extra aliases for country names that differ from the map's data.
	 *
	 * @var array
	 */
	private static $extra_aliases = array(
		'usa'            => 'USA',
		'u.s.a.'         => 'USA',
		'us'             => 'USA',
		'united states'  => 'USA',
		'uk'             => 'GBR',
		'u.k.'           => 'GBR',
		'great britain'  => 'GBR',
		'england'        => 'GBR',
		'scotland'       => 'GBR',
		'wales'          => 'GBR',
		'czech republic' => 'CZE',
		'south korea'    => 'KOR',
		'north korea'    => 'PRK',
		'russia'         => 'RUS',
		'ivory coast'    => 'CIV',
		'uae'            => 'ARE',
	);

	/**
	 * Resolve a country name to its map id (ISO A3), or '' if unknown.
	 *
	 * @param string $name Country name.
	 * @return string
	 */
	public static function resolve_id( $name ) {
		$key = strtolower( trim( (string) $name ) );
		if ( '' === $key ) {
			return '';
		}
		$data = self::data();
		if ( isset( $data['aliases'][ $key ] ) ) {
			return $data['aliases'][ $key ];
		}
		if ( isset( self::$extra_aliases[ $key ] ) ) {
			return self::$extra_aliases[ $key ];
		}
		return '';
	}

	/**
	 * Get the display label for a map id.
	 *
	 * @param string $id Country id.
	 * @return string
	 */
	public static function label( $id ) {
		$data = self::data();
		return isset( $data['labels'][ $id ] ) ? $data['labels'][ $id ] : '';
	}

	/**
	 * Load the bundled country data once.
	 *
	 * @return array
	 */
	private static function data() {
		if ( null === self::$data ) {
			$file       = COMSUP_PLUGIN_DIR . 'includes/data-countries.php';
			$data       = is_readable( $file ) ? include $file : array();
			self::$data = is_array( $data ) ? $data : array();
		}
		return self::$data;
	}

	/**
	 * Render the map container for a set of records.
	 *
	 * The markers are localized on the element as JSON; the front-end script
	 * (map.js) builds the Leaflet map from them.
	 *
	 * @param array $records Records being displayed.
	 * @return string HTML, or '' if there's nothing to plot.
	 */
	public static function render( array $records ) {
		$markers = array();

		foreach ( $records as $index => $record ) {
			$fields = isset( $record['fields'] ) ? $record['fields'] : array();

			$lat = isset( $fields['Latitude'] ) ? $fields['Latitude'] : '';
			$lng = isset( $fields['Longitude'] ) ? $fields['Longitude'] : '';
			if ( '' === $lat || '' === $lng || ! is_numeric( $lat ) || ! is_numeric( $lng ) ) {
				continue;
			}

			$country = isset( $fields[ self::COUNTRY_FIELD ] ) ? (string) $fields[ self::COUNTRY_FIELD ] : '';
			$city    = isset( $fields['City'] ) ? (string) $fields['City'] : '';

			// "city" means we resolved a self-reported city string to a point.
			// "country" means we only knew the country, so the dot sits on the
			// country's centroid — shown hollow so it never reads as precise.
			$precision = isset( $fields['Location Precision'] ) ? (string) $fields['Location Precision'] : '';

			// Self-reported city strings often already carry the country
			// ("Mukono - Uganda"), so only append it when it isn't in there.
			$place = $city;
			if ( '' !== $country && ( '' === $city || false === stripos( $city, $country ) ) ) {
				$place = trim( $city . ( '' !== $city ? ', ' : '' ) . $country );
			}

			$markers[] = array(
				'i'         => (int) $index,
				'name'      => isset( $fields['Full Name'] ) ? (string) $fields['Full Name'] : '',
				'role'      => isset( $fields['Role Type'] ) ? (string) $fields['Role Type'] : '',
				'employer'  => isset( $fields[ self::EMPLOYER_FIELD ] ) ? (string) $fields[ self::EMPLOYER_FIELD ] : '',
				'place'     => $place,
				'country'   => $country,
				'cid'       => self::resolve_id( $country ),
				'profile'   => isset( $fields['WordPress profile'] ) ? (string) $fields['WordPress profile'] : '',
				'precision' => 'city' === $precision ? 'city' : 'country',
				'lat'       => (float) $lat,
				'lng'       => (float) $lng,
			);
		}

		if ( empty( $markers ) ) {
			return '';
		}

		++self::$instance;
		$id = 'comsup-map-' . self::$instance;

		return sprintf(
			'<div class="comsup-map"><div id="%1$s" class="comsup-map__canvas" style="height:%2$s;" data-markers="%3$s" data-label-supporters="%4$s"></div></div>',
			esc_attr( $id ),
			esc_attr( self::MAP_HEIGHT ),
			esc_attr( wp_json_encode( $markers ) ),
			esc_attr__( 'supporters', 'community-supporters' )
		);
	}

}
