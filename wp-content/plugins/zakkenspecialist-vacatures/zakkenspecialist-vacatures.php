<?php
/**
 * Plugin Name:       De Zakkenspecialist - Vacatures (Dimensio API)
 * Plugin URI:         https://www.dezakkenspecialist.nl/
 * Description:        Shortcode [dimensio_vacatures] die de vacatures van De Zakkenspecialist toont, opgehaald uit de centrale vacature-API op dimensio.nl (plugin "Dimensio Vacatures API"). Plaats de shortcode in Elementor via het Shortcode-widget.
 * Version:            1.0.0
 * Requires PHP:       7.4
 * Author:             De Zakkenspecialist
 * Text Domain:        zakkenspecialist-vacatures
 *
 * Gebruik:
 * 1. Activeer deze plugin.
 * 2. Zet in Elementor een "Shortcode" widget neer op de pagina /vacatures/ en vul in: [dimensio_vacatures]
 * 3. Klaar. De vacatures worden automatisch opgehaald en 15 minuten gecached.
 *
 * Configuratie (zie de constantes hieronder):
 * - DIMENSIO_VACATURES_API_URL   De vacatures-lijst endpoint van de Dimensio Vacatures API.
 *                                 Staat nu op de testomgeving; verander dit naar de productie-URL
 *                                 zodra de "Dimensio Vacatures API" plugin op www.dimensio.nl live staat,
 *                                 bijv. https://www.dimensio.nl/wp-json/dimensio/v1/vacatures
 * - DIMENSIO_VACATURES_ENTITEIT  Welke entiteit getoond wordt (code of label, hoofdletterongevoelig).
 * - DIMENSIO_VACATURES_CACHE_TTL Cache-tijd in seconden voor de opgehaalde data.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Direct toegang niet toegestaan.
}

if ( ! defined( 'DIMENSIO_VACATURES_API_URL' ) ) {
	// TODO: aanpassen naar de productie-URL zodra dimensio.nl live staat.
	define( 'DIMENSIO_VACATURES_API_URL', 'https://test.dimensio.sixpaths.nl/wp-json/dimensio/v1/vacatures' );
}

if ( ! defined( 'DIMENSIO_VACATURES_ENTITEIT' ) ) {
	define( 'DIMENSIO_VACATURES_ENTITEIT', 'dzs' );
}

if ( ! defined( 'DIMENSIO_VACATURES_CACHE_TTL' ) ) {
	define( 'DIMENSIO_VACATURES_CACHE_TTL', 15 * MINUTE_IN_SECONDS );
}

/**
 * Haal vacatures op bij de Dimensio API, gefilterd op entiteit, met caching via transients.
 *
 * @param string $entiteit
 * @return array{items: array, error: bool}
 */
if ( ! function_exists( 'dimensio_vacatures_fetch' ) ) :
	function dimensio_vacatures_fetch( $entiteit ) {
		$cache_key = 'dzs_vacatures_' . md5( $entiteit );
		$cached    = get_transient( $cache_key );

		if ( false !== $cached && is_array( $cached ) ) {
			return array(
				'items' => $cached,
				'error' => false,
			);
		}

		$url = add_query_arg( array( 'entiteit' => $entiteit ), DIMENSIO_VACATURES_API_URL );

		$response = wp_remote_get(
			$url,
			array(
				'timeout' => 8,
			)
		);

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			// API niet bereikbaar: val terug op eventueel verlopen cache, anders leeg met foutmelding.
			$stale = get_transient( $cache_key . '_stale' );
			if ( is_array( $stale ) ) {
				return array(
					'items' => $stale,
					'error' => false,
				);
			}
			return array(
				'items' => array(),
				'error' => true,
			);
		}

		$body  = wp_remote_retrieve_body( $response );
		$items = json_decode( $body, true );

		if ( ! is_array( $items ) ) {
			$items = array();
		}

		set_transient( $cache_key, $items, DIMENSIO_VACATURES_CACHE_TTL );
		// Bewaar ook een langer houdbare kopie als fallback voor als de API later even niet bereikbaar is.
		set_transient( $cache_key . '_stale', $items, DAY_IN_SECONDS );

		return array(
			'items' => $items,
			'error' => false,
		);
	}
endif;

/**
 * Bouw de meta-regel "Vakgebied • Plaatsnaam • Uren per week" voor een vacature.
 *
 * @param array $item
 * @return string
 */
if ( ! function_exists( 'dimensio_vacatures_format_meta' ) ) :
	function dimensio_vacatures_format_meta( $item ) {
		$parts = array();

		if ( ! empty( $item['vakgebied'] ) ) {
			$parts[] = $item['vakgebied'];
		}
		if ( ! empty( $item['plaatsnaam'] ) ) {
			$parts[] = $item['plaatsnaam'];
		}
		if ( ! empty( $item['uren_per_week'] ) ) {
			$uren = trim( $item['uren_per_week'] );
			// Voorkom "39 uur uur" als het ACF-veld de eenheid al bevat.
			if ( ! preg_match( '/uur/i', $uren ) ) {
				$uren .= ' uur';
			}
			$parts[] = $uren;
		}

		return implode( ' &bull; ', array_map( 'esc_html', $parts ) );
	}
endif;

/**
 * Shortcode [dimensio_vacatures]
 *
 * Attributen:
 * - entiteit  Overschrijft DIMENSIO_VACATURES_ENTITEIT (optioneel).
 */
if ( ! function_exists( 'dimensio_vacatures_shortcode' ) ) :
	function dimensio_vacatures_shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'entiteit' => DIMENSIO_VACATURES_ENTITEIT,
			),
			$atts,
			'dimensio_vacatures'
		);

		$result = dimensio_vacatures_fetch( $atts['entiteit'] );
		$items  = $result['items'];
		$error  = $result['error'];

		// Unieke vakgebieden voor de filtertabs, in volgorde van eerste voorkomen.
		$vakgebieden = array();
		foreach ( $items as $item ) {
			if ( ! empty( $item['vakgebied'] ) && ! in_array( $item['vakgebied'], $vakgebieden, true ) ) {
				$vakgebieden[] = $item['vakgebied'];
			}
		}

		ob_start();
		?>
		<div class="dzs-vacatures">
			<?php if ( $error ) : ?>
				<p class="dzs-vacatures-melding">
					<?php esc_html_e( 'Vacatures konden op dit moment niet worden opgehaald. Probeer het later opnieuw.', 'zakkenspecialist-vacatures' ); ?>
				</p>
			<?php elseif ( empty( $items ) ) : ?>
				<p class="dzs-vacatures-melding">
					<?php esc_html_e( 'Op dit moment zijn er geen openstaande vacatures.', 'zakkenspecialist-vacatures' ); ?>
				</p>
			<?php else : ?>

				<?php if ( count( $vakgebieden ) > 1 ) : ?>
					<div class="dzs-vacatures-tabs" role="tablist">
						<button type="button" class="dzs-vacatures-tab is-active" data-vakgebied="alle"><?php esc_html_e( 'Alles', 'zakkenspecialist-vacatures' ); ?></button>
						<?php foreach ( $vakgebieden as $vakgebied ) : ?>
							<button type="button" class="dzs-vacatures-tab" data-vakgebied="<?php echo esc_attr( strtolower( $vakgebied ) ); ?>"><?php echo esc_html( $vakgebied ); ?></button>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>

				<div class="dzs-vacatures-grid">
					<?php foreach ( $items as $item ) : ?>
						<a class="dzs-vacature-card"
							data-vakgebied="<?php echo esc_attr( strtolower( isset( $item['vakgebied'] ) ? $item['vakgebied'] : '' ) ); ?>"
							href="<?php echo esc_url( isset( $item['link'] ) ? $item['link'] : '#' ); ?>">
							<span class="dzs-vacature-image" <?php if ( ! empty( $item['featured_image'] ) ) : ?>style="background-image:url(<?php echo esc_url( $item['featured_image'] ); ?>)"<?php endif; ?>></span>
							<span class="dzs-vacature-body">
								<span class="dzs-vacature-meta"><?php echo wp_kses_post( dimensio_vacatures_format_meta( $item ) ); ?></span>
								<span class="dzs-vacature-title"><?php echo esc_html( trim( isset( $item['titel'] ) ? $item['titel'] : '' ) ); ?></span>
							</span>
						</a>
					<?php endforeach; ?>
				</div>

			<?php endif; ?>
		</div>
		<?php

		dimensio_vacatures_print_assets();

		return ob_get_clean();
	}
	add_shortcode( 'dimensio_vacatures', 'dimensio_vacatures_shortcode' );
endif;

/**
 * Print de CSS/JS voor de vacatures-grid. Wordt maar één keer per pagina uitgevoerd,
 * ook als de shortcode meerdere keren gebruikt wordt.
 */
if ( ! function_exists( 'dimensio_vacatures_print_assets' ) ) :
	function dimensio_vacatures_print_assets() {
		static $printed = false;

		if ( $printed ) {
			return;
		}
		$printed = true;

		$css_url = plugins_url( 'assets/style.css', __FILE__ );
		$js_url  = plugins_url( 'assets/script.js', __FILE__ );
		$version = '1.0.0';

		printf(
			'<link rel="stylesheet" id="dzs-vacatures-css" href="%s?ver=%s" media="all" />',
			esc_url( $css_url ),
			esc_attr( $version )
		);
		printf(
			'<script id="dzs-vacatures-js" src="%s?ver=%s" defer></script>',
			esc_url( $js_url ),
			esc_attr( $version )
		);
	}
endif;
