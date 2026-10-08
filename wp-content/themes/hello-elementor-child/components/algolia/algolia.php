<?php
// settings SEARCH ENGINE Algolia — velden volgens de PLATTE ACF Product-groep (SAP-normalisatie)
if (!defined('ABSPATH')) exit;

// product = zakken-assortiment (/onze-producten/), handschoen = Just Gloves (/just-gloves/).
// De pagina's scheiden de twee via een post_type-filter in de shortcode.
// LET OP: $should is de statuscheck van de plugin ('publish' + geen wachtwoord). Die MOET
// blijven staan: overschrijven we hem, dan wordt een product in concept of prullenbak bij
// het opslaan gewoon opnieuw naar Algolia gepusht en blijft het in het overzicht staan
// terwijl het detail leeg is (bug gevonden 2026-08-21 op artikel 1002466).
add_filter('algolia_should_index_post', function ($should, WP_Post $post) {
    return $should && in_array($post->post_type, ['product', 'handschoen'], true);
}, 10, 2);

add_filter('algolia_should_index_searchable_post', function ($should, WP_Post $post) {
    return $should && in_array($post->post_type, ['product', 'handschoen'], true);
}, 10, 2);

// Algolia credentials: Docker env -> anders plugin-instellingen (DB)
if (!defined('ALGOLIA_APP_ID')) {
    define('ALGOLIA_APP_ID', getenv('ALGOLIA_APP_ID') ?: get_option('algolia_application_id', ''));
}
if (!defined('ALGOLIA_ADMIN_API_KEY')) {
    define('ALGOLIA_ADMIN_API_KEY', getenv('ALGOLIA_ADMIN_API_KEY') ?: get_option('algolia_api_key', ''));
}
if (!defined('ALGOLIA_SEARCH_KEY')) {
    define('ALGOLIA_SEARCH_KEY', getenv('ALGOLIA_SEARCH_KEY') ?: get_option('algolia_search_api_key', ''));
}

function dim_algolia_index_prefix(): string {
    return get_option('algolia_index_name_prefix', 'wp_');
}

/**
 * Haal Algolia-credentials robuust op. wp-config kan ALGOLIA_APP_ID als LEGE string
 * definiëren (env-var uitgecommentarieerd in docker-compose); die lege constante mag
 * de sleutels uit de plugin-instellingen niet overschaduwen. Daarom: constante alleen
 * gebruiken als hij gevuld is, anders terugvallen op de plugin-optie (DB).
 */
function dim_algolia_app_id(): string {
    return (defined('ALGOLIA_APP_ID') && ALGOLIA_APP_ID) ? ALGOLIA_APP_ID : (string) get_option('algolia_application_id', '');
}
function dim_algolia_search_key(): string {
    return (defined('ALGOLIA_SEARCH_KEY') && ALGOLIA_SEARCH_KEY) ? ALGOLIA_SEARCH_KEY : (string) get_option('algolia_search_api_key', '');
}

/**
 * Algolia PHP-client (admin). De wp-search-with-algolia plugin bundelt de client onder
 * een geprefixte namespace (WebDevStudios\WPSWA\...); losse installs gebruiken de kale namespace.
 */
function dim_algolia_client() {
    $app = dim_algolia_app_id();
    $key = (defined('ALGOLIA_ADMIN_API_KEY') && ALGOLIA_ADMIN_API_KEY) ? ALGOLIA_ADMIN_API_KEY : (string) get_option('algolia_api_key', '');
    foreach ([
        '\\WebDevStudios\\WPSWA\\Algolia\\AlgoliaSearch\\SearchClient',
        '\\Algolia\\AlgoliaSearch\\SearchClient',
    ] as $cls) {
        if (class_exists($cls)) {
            return $cls::create($app, $key);
        }
    }
    return null;
}

$attributesToSearch = [
    'unordered(post_title)',
    'unordered(artikelcode)',
    'unordered(omschrijving)',
    'unordered(toepassingen)',   // "pedaalemmerzakken", "containerzakken" — mensentaal
    'unordered(zoektermen)',     // onzichtbare synoniemen ("vuilniszakken", "klikozakken")
    'unordered(kwaliteit)',
    'unordered(kleuren)',
    'unordered(type)',
    'unordered(groep)',
    'unordered(merk_naam)',
];

// facets — nodig om te kunnen filteren. LET OP: ook numerieke velden die je met een
// range-filter (rangeInput/rangeSlider) gebruikt MOETEN hier staan, anders krijgt de
// widget geen min/max terug en werkt het filter niet.
/**
 * Omtrek van de opengevouwen zak in cm.
 *
 * Zonder zijvouw is de platte breedte precies de halve omtrek, dus omtrek = 2b.
 * Met zijvouw zit er aan elke kant een ingevouwen flap van `inslag` diep; die
 * flap kost 2 x inslag aan materiaal, aan beide kanten. Vandaar 2 x (b + 2i).
 *
 * @return float|null null als er geen bruikbare breedte bekend is.
 */
function dim_bereken_omtrek_cm( $breedte, $inslag ): ?float {
    if ( ! is_numeric( $breedte ) || (float) $breedte <= 0 ) return null;
    $i = is_numeric( $inslag ) ? (float) $inslag : 0.0;
    return round( 2 * ( (float) $breedte + 2 * $i ), 1 );
}

function dim_algolia_faceting_attrs(): array {
    return [
        'searchable(taxonomies.product-group)',
        'searchable(taxonomies.category_product)',
        'searchable(toepassingen)',
        'searchable(kwaliteit)',
        'searchable(kleuren)',
        'searchable(type)',
        'searchable(groep)',
        'searchable(merk_naam)',
        'searchable(vorm)',
        'searchable(certificering)',
        'trekband',
        'geperforeerd',
        'bedrukking_mogelijk',
        'post_type',
        // numerieke range-filters (linkerkant): breedte / lengte / inhoud
        'breedte_cm',
        'lengte_cm',
        'inslag_cm',
        // omtrek = de maat waar de zakkencalculator op matcht. Een zak met
        // zijvouw (65/25) is opengevouwen veel wijder dan zijn platte breedte,
        // dus filteren op breedte_cm alleen zou die zakken onterecht laten
        // afvallen. Zie dim_bereken_omtrek_cm() hieronder.
        'omtrek_cm',
        'inhoud_liter',
        'colli_per_laag',
        'colli_per_pallet',
        // dikte in 2 stappen: eerst eenheid (T / my / mm), dan waarde
        'searchable(dikte_eenheid)',
        'dikte_waarde',
        // variant-groepering (kleur/dikte-varianten van hetzelfde basisproduct)
        'filterOnly(variant_group)',
        'filterOnly(post_id)',
    ];
}

add_filter('algolia_searchable_posts_index_settings', function ($settings) use ($attributesToSearch) {
    $prefix = dim_algolia_index_prefix();
    $settings['searchableAttributes'] = $attributesToSearch;
    $settings['attributesForFaceting'] = dim_algolia_faceting_attrs();
    // distinct: één grid-kaart per variant-groep (query's zetten distinct=true zelf aan)
    $settings['attributeForDistinct'] = 'variant_group';

    $settings['replicas'] = [
        "{$prefix}searchable_posts_priority_asc",
        "{$prefix}searchable_posts_name_asc",
        "{$prefix}searchable_posts_date_desc",
    ];
    return $settings;
});

add_filter('algolia_posts_index_settings', function ($settings) use ($attributesToSearch) {
    $settings['searchableAttributes'] = $attributesToSearch;
    return $settings;
});

function wds_algolia_custom_fields( array $attributes, WP_Post $post ) {

    if ( ! in_array( $post->post_type, [ 'product', 'handschoen' ], true ) ) {
        return $attributes;
    }
    $is_handschoen = $post->post_type === 'handschoen';

    // Platte ACF-velden — tekst
    $text_fields = [
        'artikelcode', 'omschrijving', 'kwaliteit', 'formaat', 'dikte', 'dikte_eenheid', 'kleuren',
        'bedrukking', 'merk_naam', 'verpakt_per', 'groep', 'type', 'vorm',
        'certificering', 'ean_doos', 'afmetingen_colli', 'custom_product_url',
    ];
    foreach ( $text_fields as $field ) {
        $data = get_field( $field, $post->ID );
        if ( $data !== null && $data !== '' ) {
            $attributes[ $field ] = (string) $data;
        }
    }
    // merk_naam netjes weergegeven (ECONORM -> Econorm); korte all-caps acroniemen blijven (VLA, VHC)
    if ( ! empty( $attributes['merk_naam'] ) ) {
        $attributes['merk_naam'] = dim_merk_titlecase( $attributes['merk_naam'] );
    }

    // variant-groepering: zakken = basis (materiaal+type+vorm+formaat+merk) zonder kleur/dikte;
    // handschoenen = basistitel + kleur, met maat (S/M/L/XL) als variant-dimensie
    foreach ( [ 'variant_group', 'variant_base_title' ] as $field ) {
        $v = get_post_meta( $post->ID, $field, true );
        if ( $v === '' || $v === false || $v === null ) {
            // fallback: bereken on-the-fly als de admin-actie nog niet is gedraaid
            $calc = $is_handschoen ? dim_calc_variant_handschoen( $post->ID ) : dim_calc_variant( $post->ID );
            $v = $calc[ $field ];
        }
        if ( $v !== '' ) {
            $attributes[ $field ] = (string) $v;
        }
    }
    // toepassingen: multi-select -> array in de index (facet + doorzoekbaar)
    $toep = get_field( 'toepassingen', $post->ID );
    if ( is_string( $toep ) && $toep !== '' ) $toep = array_map( 'trim', explode( '|', $toep ) );
    $toep = is_array( $toep ) ? array_values( array_filter( $toep ) ) : [];
    $attributes['toepassingen'] = $toep;

    // onzichtbare zoektermen: hoe mensen écht zoeken ("vuilniszakken", "klikozakken", "incozakken")
    $synoniemen = [
        'Pedaalemmerzakken'            => [ 'pedaalemmer zakken', 'vuilniszakken', 'vuilniszak klein', 'badkamer emmerzakken' ],
        'Prullenbak & kantoor'         => [ 'vuilniszakken', 'prullenbak zakken', 'kantoor afvalzakken' ],
        'Huisvuil & keuken'            => [ 'huisvuilzakken', 'vuilniszakken', 'keuken afvalzakken', 'vuilnis' ],
        'Containerzakken & kliko'      => [ 'containerzakken', 'container zakken', 'klikozakken', 'kliko zakken', 'rolcontainer zakken', 'vuilniszakken groot' ],
        'PMD & afval scheiden'         => [ 'pmd zakken', 'plastic afval', 'afvalscheiding' ],
        'GFT & composteerbaar'         => [ 'gft zakken', 'composteerbare zakken', 'biozakken', 'bio zakken' ],
        'Zorg & medisch afval'         => [ 'medisch afval zakken', 'ziekenhuis zakken', 'zorgafval' ],
        'Incontinentie & luiers'       => [ 'incozakken', 'inco zakken', 'luierzakken' ],
        'Voedsel & horeca'             => [ 'voedselzakken', 'horeca zakken', 'keukenzakken' ],
        'Diepvries & vacuüm'           => [ 'diepvrieszakken', 'vacuumzakken', 'vershoudzakken' ],
        'Kleinverpakking & onderdelen' => [ 'gripzakjes', 'hersluitbare zakjes', 'ziplock zakjes', 'druksluiting zakjes' ],
        'Verzenden & webshop'          => [ 'verzendzakken', 'webshopzakken', 'mailingbags', 'verzendenveloppen' ],
        'Draagtassen & retail'         => [ 'plastic tassen', 'winkeltassen', 'hemdtassen' ],
        'Bouw & afdekken'              => [ 'bouwfolie', 'afdekzeil', 'puinzakken', 'afdekplastic' ],
        'Pallet & transport'           => [ 'pallethoezen', 'krimphoezen', 'palletfolie' ],
        'Bescherming & hoezen'         => [ 'beschermhoezen', 'meubelhoezen', 'matrashoezen' ],
        'Industrie & folie op rol'     => [ 'folierollen', 'folie op rol', 'industriefolie' ],
    ];
    $zoek = [];
    foreach ( $toep as $tp ) {
        foreach ( ( $synoniemen[ $tp ] ?? [] ) as $s ) $zoek[] = $s;
    }
    $attributes['zoektermen'] = array_values( array_unique( $zoek ) );

    // groepsdata voor de grid-kaart: alle kleuren/diktes/formaten/vormen binnen de groep + aantal
    foreach ( [ 'variant_kleuren', 'variant_diktes', 'variant_formaten', 'variant_vormen' ] as $field ) {
        $arr = json_decode( (string) get_post_meta( $post->ID, $field, true ), true );
        $attributes[ $field ] = is_array( $arr ) ? array_values( $arr ) : [];
    }
    $vc = get_post_meta( $post->ID, 'variant_count', true );
    $attributes['variant_count'] = is_numeric( $vc ) ? (int) $vc : 1;

    // Numeriek — nodig voor range-filters en het dikte-waarde facet
    $num_fields = [ 'breedte_cm', 'lengte_cm', 'inslag_cm', 'dikte_waarde', 'inhoud_liter',
                    'colli_per_laag', 'colli_per_pallet' ];
    foreach ( $num_fields as $field ) {
        $data = get_field( $field, $post->ID );
        if ( is_numeric( $data ) ) {
            $attributes[ $field ] = (float) $data;
        }
    }

    // Omtrek van de opengevouwen zak — het veld waar de zakkencalculator op
    // filtert. Een platte zak zonder zijvouw heeft omtrek 2 x breedte; bij een
    // zak met zijvouw komt er per kant 2 x de inslag bij, dus 2 x (b + 2 x i).
    // Controle op het assortiment: kratzak 68/17 -> 2 x (68 + 34) = 204 cm, en
    // de omtrek van een euro-krat 60 x 40 is 200 cm. Klopt.
    $omtrek = dim_bereken_omtrek_cm(
        $attributes['breedte_cm'] ?? null,
        $attributes['inslag_cm']  ?? null
    );
    if ( $omtrek !== null ) {
        $attributes['omtrek_cm'] = $omtrek;
    }

    // Booleans — voor aan/uit-filters
    foreach ( [ 'trekband', 'geperforeerd' ] as $field ) {
        $attributes[ $field ] = (bool) get_field( $field, $post->ID );
    }

    // Bedrukking is geen eigenschap van de zak maar een dienst. We leiden het
    // af uit de SAP-opdruk (zie dim_bedrukking_is_gevuld) in plaats van er een
    // apart veld voor te maken: geen extra kolom in de import, niets extra's om
    // gevuld te houden. LET OP: dit is "bewezen mogelijk", geen volledige lijst
    // — zodra verkoop de echte regel geeft (bv. alles behalve een paar groepen)
    // hoeft alleen die ene functie te veranderen.
    $attributes['bedrukking_mogelijk'] = function_exists( 'dim_bedrukking_is_gevuld' )
        ? dim_bedrukking_is_gevuld( $post->ID )
        : false;

    $priority = get_field('prioriteit', $post->ID);
    $attributes['priority'] = is_numeric($priority) ? (int) $priority : 0;
    $attributes['post_date'] = (int) get_post_time('U', true, $post);

    if (has_post_thumbnail($post->ID)) {
        $attributes['medium_url'] = get_the_post_thumbnail_url($post->ID, 'medium');
    }

    // 360-gradenvideo voor de hover op de kaart (renderHit toont alleen een
    // <video> als dit veld bestaat). ACF-fileveld met return_format=array,
    // maar we vangen ook een los ID of een kale URL af — dan blijft dit
    // werken als iemand het veldtype ooit omzet.
    $video = get_field('360_video_view', $post->ID);
    $video_url = is_array($video) ? (string) ($video['url'] ?? '') : (is_numeric($video) ? (string) wp_get_attachment_url((int) $video) : (string) $video);
    if ($video_url !== '') {
        $attributes['video_url'] = $video_url;
    }

    return $attributes;
}

add_filter( 'algolia_post_shared_attributes', 'wds_algolia_custom_fields', 10, 2 );
add_filter( 'algolia_searchable_post_shared_attributes', 'wds_algolia_custom_fields', 10, 2 );

/* --------------------------------------------------------------------------
 * Helpers: merknaam-opmaak + variant-groepering
 * ------------------------------------------------------------------------ */

// ECONORM -> Econorm; korte all-caps acroniemen (<=4 letters: VLA, VHC, DKT, TRP) blijven staan.
function dim_merk_titlecase( string $merk ): string {
    $merk = trim( $merk );
    if ( $merk === '' ) return '';
    // per woord (bv. "Happy Sacks")
    $parts = preg_split( '/\s+/', $merk );
    $out = array_map( function ( $w ) {
        if ( ctype_upper( preg_replace( '/[^A-Za-z]/', '', $w ) ) && mb_strlen( $w ) <= 4 ) {
            return $w; // acroniem laten staan
        }
        return mb_strtoupper( mb_substr( $w, 0, 1 ) ) . mb_strtolower( mb_substr( $w, 1 ) );
    }, $parts );
    return implode( ' ', $out );
}

// Normalisatie-helper voor groepssleutels.
function dim_vnorm( $s ) {
    $s = mb_strtolower( trim( (string) $s ) );
    return preg_replace( '/\s+/', ' ', $s );
}

/* --------------------------------------------------------------------------
 * Handschoenen (Just Gloves): eigen, simpele variant-logica.
 * Groep = basistitel + kleur; MAAT (S/M/L/XL) is de variant-dimensie en komt
 * als klikbare chips op de Formaat-regel van de detailview (de bestaande
 * "niet-splitsbaar formaat"-route in products-overview.js).
 * ------------------------------------------------------------------------ */

// Maat-volgorde: S < M < L < XL. Onbekende maten komen achteraan (alfabetisch).
function dim_maat_index( string $maat ): int {
    static $orde = [
        'xs' => 1, 'extra small' => 1,
        's'  => 2, 'small'       => 2,
        'm'  => 3, 'medium'      => 3,
        'l'  => 4, 'large'       => 4,
        'xl' => 5, 'extra large' => 5,
        'xxl' => 6, '2xl' => 6,
        'xxxl' => 7, '3xl' => 7,
    ];
    return $orde[ dim_vnorm( $maat ) ] ?? 99;
}

// Basistitel = post_title zonder maat-aanduiding aan het eind
// ("Onderzoekshandschoen nitril XL" -> "Onderzoekshandschoen nitril").
function dim_handschoen_basistitel( int $post_id ): string {
    $titel = (string) get_the_title( $post_id );
    $zonder = preg_replace(
        '/\s*[-–]?\s*(xs|s|m|l|xl|xxl|2xl|3xl|xxxl|extra\s+small|small|medium|large|extra\s+large)\s*$/i',
        '', $titel
    );
    $zonder = trim( (string) $zonder );
    return $zonder !== '' ? $zonder : trim( $titel );
}

function dim_calc_variant_handschoen( int $post_id ): array {
    $basis = dim_handschoen_basistitel( $post_id );
    $kleur = trim( (string) get_field( 'kleuren', $post_id ) );
    $maat  = trim( (string) get_field( 'formaat', $post_id ) );
    // kleur in de titel: er komt per kleur één kaart, anders heten beide kaarten hetzelfde
    $titel = trim( $basis . ( $kleur !== '' ? ' ' . mb_strtolower( $kleur ) : '' ) );
    return [
        'variant_group'      => 'hs|' . dim_vnorm( $basis ) . '|' . dim_vnorm( $kleur ),
        'variant_base_title' => $titel,
        'basis'              => $basis,
        'kleur'              => $kleur,
        'maat'               => $maat,
    ];
}

/**
 * Splits een formaat-string in (breedte-descriptor, lengte in mm, lengte-tekst).
 * "70 x 110cm" -> ["70", 1100, "110cm"]; "58/2x22 x 63cm" -> ["58/2x22", 630, "63cm"];
 * "127 x (2 x 44) x 120 cm" -> ["127 x (2 x 44)", 1200, "120 cm"].
 * Niet-clusterbare formaten ("320x130x425 mm", "305+50mm") geven lengte null.
 */
function dim_split_formaat( string $formaat ): array {
    $delen = explode( ' x ', $formaat );
    if ( count( $delen ) < 2 ) return [ $formaat, null, '' ];
    $lengte_txt = trim( (string) array_pop( $delen ) );
    $breedte = trim( implode( ' x ', $delen ) );
    if ( ! preg_match( '/^(\d+(?:[.,]\d+)?)\s*(cm|mm)$/u', $lengte_txt, $m ) ) {
        return [ $formaat, null, '' ];
    }
    $mm = (float) str_replace( ',', '.', $m[1] ) * ( $m[2] === 'cm' ? 10 : 1 );
    return [ $breedte, $mm, $lengte_txt ];
}

// Pre-sleutel (zonder lengte-cluster): materiaal + type + merk + breedte-descriptor.
// VORM en FORMAAT-lengte zijn variant-dimensies geworden (kies-knoppen in de detail).
function dim_calc_variant( int $post_id ): array {
    $g = function ( $f ) use ( $post_id ) { return trim( (string) get_field( $f, $post_id ) ); };
    $kwaliteit = $g('kwaliteit'); $type = $g('type');
    $formaat = $g('formaat'); $merk = dim_merk_titlecase( $g('merk_naam') );
    [ $breedte, $lengte_mm, $lengte_txt ] = dim_split_formaat( $formaat );
    $pre = implode( '|', array_map( 'dim_vnorm', [ $kwaliteit, $type, $merk, $breedte ] ) );
    // fallback-titel (admin-actie berekent de definitieve met lengte-range)
    $title = trim( preg_replace( '/\s+/', ' ', $kwaliteit . ' ' . $type . ' ' . $formaat . ' ' . $merk ) );
    return [
        'variant_group'      => $pre . '|' . dim_vnorm( $lengte_mm === null ? $formaat : '' ),
        'variant_base_title' => $title,
        'pre'                => $pre,
        'lengte_mm'          => $lengte_mm,
        'lengte_txt'         => $lengte_txt,
        'breedte'            => $breedte,
        'kwaliteit'          => $kwaliteit,
        'type'               => $type,
        'merk'               => $merk,
        'formaat'            => $formaat,
    ];
}

/* --------------------------------------------------------------------------
 * Admin-actie: wp-admin openen met ?dim_fix_products=1
 * - merk_naam netjes (ECONORM -> Econorm) opslaan in ACF-meta
 * - product-group taxonomie-term koppelen op basis van het 'groep'-veld
 * - variant_group + variant_base_title berekenen en opslaan
 * ------------------------------------------------------------------------ */
add_action('admin_init', function () {
    if (!current_user_can('manage_options')) return;
    if (empty($_GET['dim_fix_products'])) return;

    $taxonomy = 'product-group';
    $tax_exists = taxonomy_exists( $taxonomy );

    $q = new WP_Query([
        'post_type'      => 'product',
        'post_status'    => 'any',
        'posts_per_page' => -1,
        'fields'         => 'ids',
        'no_found_rows'  => true,
    ]);

    $merk_fixed = 0; $terms_set = 0; $variant_set = 0;
    $groups = []; // variant_group -> ['pids'=>[], 'kleuren'=>[], 'diktes'=>[]]
    foreach ( $q->posts as $pid ) {
        // 1) merk_naam opmaak
        $merk = trim( (string) get_field( 'merk_naam', $pid ) );
        if ( $merk !== '' ) {
            $nieuw = dim_merk_titlecase( $merk );
            if ( $nieuw !== $merk ) {
                update_field( 'merk_naam', $nieuw, $pid );
                $merk_fixed++;
            }
        }
        // 2) product-group taxonomie koppelen op basis van 'groep'
        if ( $tax_exists ) {
            $groep = trim( (string) get_field( 'groep', $pid ) );
            if ( $groep !== '' ) {
                $term = term_exists( $groep, $taxonomy );
                if ( ! $term ) $term = wp_insert_term( $groep, $taxonomy );
                if ( ! is_wp_error( $term ) ) {
                    wp_set_object_terms( $pid, (int) $term['term_id'], $taxonomy, false );
                    $terms_set++;
                }
            }
        }
        // 3) variant-data (pass 1: per PRE-groep verzamelen; clustering volgt in pass 2)
        $calc = dim_calc_variant( $pid );
        $pre = $calc['pre'];
        if ( ! isset( $groups[ $pre ] ) ) $groups[ $pre ] = [ 'leden' => [], 'calc' => $calc ];
        $groups[ $pre ]['leden'][] = [
            'pid'        => $pid,
            'lengte_mm'  => $calc['lengte_mm'],
            'lengte_txt' => $calc['lengte_txt'],
            'formaat'    => $calc['formaat'],
            'vorm'       => trim( (string) get_field( 'vorm', $pid ) ),
            'kleur'      => trim( (string) get_field( 'kleuren', $pid ) ),
            'dikte'      => trim( (string) get_field( 'dikte', $pid ) ),
        ];
        $variant_set++;
    }

    // pass 2: lengte-clustering per pre-groep + groepsdata per product opslaan.
    // Chain-regel: gesorteerd op lengte; zelfde cluster zolang het gat naar de vorige
    // <= max(100 mm, 15%) is. 110->120->130 samen; 130->180 (gat 500 mm) splitst.
    $multi_groups = 0; $totaal_groepen = 0;
    $sort_num = function ( $a, $b ) {
        preg_match( '/[\d.]+/', $a, $ma ); preg_match( '/[\d.]+/', $b, $mb );
        return ( (float) ( $ma[0] ?? 0 ) ) <=> ( (float) ( $mb[0] ?? 0 ) );
    };
    foreach ( $groups as $pre => $info ) {
        $c = $info['calc'];
        // verdeel leden in subgroepen
        $met_lengte = array_filter( $info['leden'], fn( $l ) => $l['lengte_mm'] !== null );
        $zonder     = array_filter( $info['leden'], fn( $l ) => $l['lengte_mm'] === null );
        $sub = [];
        usort( $met_lengte, fn( $a, $b ) => $a['lengte_mm'] <=> $b['lengte_mm'] );
        $ci = -1; $vorige = null;
        foreach ( $met_lengte as $lid ) {
            if ( $vorige === null || ( $lid['lengte_mm'] - $vorige ) > max( 100, 0.15 * $vorige ) ) $ci++;
            $vorige = $lid['lengte_mm'];
            $sub[ 'L' . $ci ][] = $lid;
        }
        foreach ( $zonder as $lid ) {
            $sub[ 'F' . dim_vnorm( $lid['formaat'] ) ][] = $lid;   // zelfde formaat-string = zelfde groep
        }

        foreach ( $sub as $sid => $leden ) {
            $totaal_groepen++;
            $key = $pre . '|' . $sid;
            $kleuren = []; $diktes = []; $formaten = []; $vormen = [];
            foreach ( $leden as $l ) {
                if ( $l['kleur'] !== '' && ! in_array( $l['kleur'], $kleuren, true ) )   $kleuren[]  = $l['kleur'];
                if ( $l['dikte'] !== '' && ! in_array( $l['dikte'], $diktes, true ) )    $diktes[]   = $l['dikte'];
                if ( $l['formaat'] !== '' && ! in_array( $l['formaat'], $formaten, true ) ) $formaten[] = $l['formaat'];
                if ( $l['vorm'] !== '' && ! in_array( $l['vorm'], $vormen, true ) )      $vormen[]   = $l['vorm'];
            }
            usort( $diktes, $sort_num );
            usort( $formaten, function ( $a, $b ) {   // op lengte sorteren
                [ , $la ] = dim_split_formaat( $a ); [ , $lb ] = dim_split_formaat( $b );
                return ( $la ?? 0 ) <=> ( $lb ?? 0 );
            } );
            sort( $vormen );

            // basistitel: bij meerdere lengtes een range ("70 x 110–130cm")
            if ( $sid[0] === 'L' && count( $formaten ) > 1 ) {
                $eerste = $leden[0]; $laatste = $leden[ count( $leden ) - 1 ];
                preg_match( '/[\d.,]+/', $eerste['lengte_txt'], $mn );
                $range = ( $mn[0] ?? '' ) . '–' . $laatste['lengte_txt'];
                $formaat_titel = $c['breedte'] . ' x ' . $range;
            } else {
                $formaat_titel = $leden[0]['formaat'];
            }
            $title = trim( preg_replace( '/\s+/', ' ',
                $c['kwaliteit'] . ' ' . $c['type'] . ' ' . $formaat_titel . ' ' . $c['merk'] ) );

            if ( count( $leden ) > 1 ) $multi_groups++;
            foreach ( $leden as $l ) {
                update_post_meta( $l['pid'], 'variant_group', $key );
                update_post_meta( $l['pid'], 'variant_base_title', $title );
                update_post_meta( $l['pid'], 'variant_kleuren',  wp_json_encode( $kleuren ) );
                update_post_meta( $l['pid'], 'variant_diktes',   wp_json_encode( $diktes ) );
                update_post_meta( $l['pid'], 'variant_formaten', wp_json_encode( $formaten ) );
                update_post_meta( $l['pid'], 'variant_vormen',   wp_json_encode( $vormen ) );
                update_post_meta( $l['pid'], 'variant_count',    count( $leden ) );
            }
        }
    }

    /* --- HANDSCHOENEN (Just Gloves): groeperen op basistitel + kleur, ---------
       met maat als variant-dimensie (chips in de detailview). ---------------- */
    $hq = new WP_Query([
        'post_type'      => 'handschoen',
        'post_status'    => 'any',
        'posts_per_page' => -1,
        'fields'         => 'ids',
        'no_found_rows'  => true,
    ]);
    $hgroups = [];
    foreach ( $hq->posts as $pid ) {
        $c = dim_calc_variant_handschoen( $pid );
        $key = $c['variant_group'];
        if ( ! isset( $hgroups[ $key ] ) ) {
            $hgroups[ $key ] = [ 'titel' => $c['variant_base_title'], 'kleur' => $c['kleur'], 'leden' => [] ];
        }
        $hgroups[ $key ]['leden'][] = [ 'pid' => $pid, 'maat' => $c['maat'] ];
    }
    $h_multi = 0;
    foreach ( $hgroups as $key => $g ) {
        $maten = array_values( array_unique( array_filter( array_map( fn( $l ) => $l['maat'], $g['leden'] ) ) ) );
        usort( $maten, fn( $a, $b ) => [ dim_maat_index( $a ), dim_vnorm( $a ) ] <=> [ dim_maat_index( $b ), dim_vnorm( $b ) ] );
        if ( count( $g['leden'] ) > 1 ) $h_multi++;
        foreach ( $g['leden'] as $l ) {
            update_post_meta( $l['pid'], 'variant_group', $key );
            update_post_meta( $l['pid'], 'variant_base_title', $g['titel'] );
            update_post_meta( $l['pid'], 'variant_kleuren',  wp_json_encode( array_values( array_filter( [ $g['kleur'] ] ) ) ) );
            update_post_meta( $l['pid'], 'variant_diktes',   wp_json_encode( [] ) );
            update_post_meta( $l['pid'], 'variant_formaten', wp_json_encode( $maten ) );
            update_post_meta( $l['pid'], 'variant_vormen',   wp_json_encode( [] ) );
            update_post_meta( $l['pid'], 'variant_count',    count( $g['leden'] ) );
        }
    }

    wp_die( sprintf(
        'Klaar. Producten verwerkt: %d | merk_naam bijgewerkt: %d | product-group termen gezet: %d%s | variant-data: %d | variant-groepen: %d (waarvan %d met >1 product; verwacht grid-resultaat: %d).<br>Handschoenen verwerkt: %d | handschoen-groepen: %d (waarvan %d met >1 maat).<br>Volgende stappen: 1) ?dim_push_algolia_settings=1 (alleen bij facet-wijzigingen)  2) ?dim_reindex_producten=1 (of =handschoen voor alleen de handschoenen).',
        count( $q->posts ), $merk_fixed, $terms_set,
        $tax_exists ? '' : ' (taxonomie product-group bestaat niet!)', $variant_set,
        $totaal_groepen, $multi_groups, $totaal_groepen,
        count( $hq->posts ), count( $hgroups ), $h_multi
    ) );
});

// Replica-instellingen pushen: wp-admin openen met ?dim_push_algolia_replicas=1
add_action('admin_init', function () {
    if (!current_user_can('manage_options')) return;
    if (empty($_GET['dim_push_algolia_replicas'])) return;

    $client = dim_algolia_client();
    if (!$client) {
        wp_die('Algolia PHP client not found. Make sure the WP Algolia plugin is active.');
    }

    $main = dim_algolia_index_prefix() . 'searchable_posts';

    $replicas = [
        "{$main}_name_asc"     => [ 'ranking' => ['asc(post_title)','typo','geo','words','filters','proximity','attribute','exact','custom'] ],
        "{$main}_priority_asc" => [ 'ranking' => ['asc(priority)','typo','geo','words','filters','proximity','attribute','exact','custom'] ],
        "{$main}_date_desc"    => [ 'ranking' => ['desc(post_date)','typo','geo','words','filters','proximity','attribute','exact','custom'] ],
    ];

    /* Een replica erft NIETS automatisch wanneer setSettings() hem zelf
       aanmaakt: hij heeft dan alléén de ranking, en mist attributesForFaceting
       enz. Het vaste filter post_type:product matcht dan nul records en elke
       sortering toont een leeg overzicht (zo ging het op productie). Daarom:
       de volledige settings van de hoofdindex kopiëren en daar de ranking
       overheen leggen — volgorde-onafhankelijk en veilig om te herhalen. */
    $basis = $client->initIndex($main)->getSettings();
    unset($basis['replicas'], $basis['primary']);

    foreach ($replicas as $replicaName => $override) {
        $client->initIndex($replicaName)->setSettings(array_merge($basis, $override));
    }

    $client->initIndex($main)->setSettings([ 'replicas' => array_keys($replicas) ]);

    wp_die('Replica settings pushed to Algolia (volledige settings van ' . esc_html($main) . ' + eigen ranking). Sorteren werkt zodra Algolia ze verwerkt heeft (enkele seconden).');
});

// Index-settings direct pushen (los van de plugin, die dit niet betrouwbaar doet bij re-index):
// wp-admin openen met ?dim_push_algolia_settings=1
add_action('admin_init', function () {
    if (!current_user_can('manage_options')) return;
    if (empty($_GET['dim_push_algolia_settings'])) return;

    $client = dim_algolia_client();
    if (!$client) {
        wp_die('Algolia PHP client not found. Make sure the WP Algolia plugin is active.');
    }
    $main = dim_algolia_index_prefix() . 'searchable_posts';

    global $attributesToSearch;
    $settings = [
        'attributesForFaceting' => dim_algolia_faceting_attrs(),
        'attributeForDistinct'  => 'variant_group',
    ];
    // zelfde zoekvelden als de plugin-filter (o.a. toepassingen — "pedaalemmerzakken" etc.)
    $settings['searchableAttributes'] = [
        'unordered(post_title)', 'unordered(artikelcode)', 'unordered(omschrijving)',
        'unordered(toepassingen)', 'unordered(zoektermen)', 'unordered(kwaliteit)',
        'unordered(kleuren)', 'unordered(type)', 'unordered(groep)', 'unordered(merk_naam)',
    ];
    $client->initIndex($main)->setSettings($settings, ['forwardToReplicas' => true]);

    wp_die('Index-settings gepusht naar ' . esc_html($main) . ' (searchableAttributes + attributesForFaceting + attributeForDistinct, incl. replicas).');
});

/* --------------------------------------------------------------------------
 * INDEX OPSCHONEN — verwijdert weesrecords uit de Algolia-index: records
 * waarvan de post niet meer bestaat, in de prullenbak of op concept staat, of
 * geen product/handschoen (meer) is. Zonder dit blijft een vervallen artikel in
 * het overzicht staan terwijl het detail leeg is (SAP-export laat artikelen
 * vervallen; de import verwijdert die posts niet).
 * Gebruik: wp-admin openen met ?dim_opruim_algolia=1 (of &dry=1 om eerst te kijken).
 * ------------------------------------------------------------------------ */
add_action('admin_init', function () {
    if (!current_user_can('manage_options')) return;
    if (empty($_GET['dim_opruim_algolia'])) return;
    @set_time_limit(0);
    @ini_set('memory_limit', '512M');
    ignore_user_abort(true);

    $client = dim_algolia_client();
    if (!$client) {
        wp_die('Algolia PHP client niet gevonden. Staat de WP Algolia-plugin aan?');
    }

    $dry    = !empty($_GET['dry']);
    $prefix = dim_algolia_index_prefix();
    // Alleen primaire indexen; replicas nemen deletes automatisch over.
    $kandidaten = [ $prefix . 'searchable_posts', $prefix . 'posts_product', $prefix . 'posts_handschoen' ];

    $bestaand = [];
    try {
        $lijst = $client->listIndices();
        foreach (($lijst['items'] ?? []) as $i) { $bestaand[] = $i['name']; }
    } catch (\Exception $e) {
        wp_die('Kon de index-lijst niet ophalen: ' . esc_html($e->getMessage()));
    }

    $regels = [];
    $totaal = 0;
    foreach ($kandidaten as $naam) {
        if (!in_array($naam, $bestaand, true)) continue;
        $index   = $client->initIndex($naam);
        $wees    = [];   // objectID's om te verwijderen
        $details = [];   // per artikel één regel voor het rapport
        $gezien  = 0;
        foreach ($index->browseObjects([
            'attributesToRetrieve' => ['post_id', 'post_type', 'post_title', 'artikelcode'],
        ]) as $rec) {
            $gezien++;
            $pid  = (int) ($rec['post_id'] ?? 0);
            $post = $pid ? get_post($pid) : null;
            if ($post
                && in_array($post->post_type, ['product', 'handschoen'], true)
                && $post->post_status === 'publish') {
                continue;
            }
            $wees[]  = (string) $rec['objectID'];
            $reden   = !$post
                ? 'post bestaat niet meer'
                : ($post->post_status !== 'publish' ? 'status: ' . $post->post_status : 'post_type: ' . $post->post_type);
            $sleutel = $pid ?: ('obj:' . $rec['objectID']);
            $details[$sleutel] = sprintf('%s — %s — %s',
                (string) ($rec['artikelcode'] ?? ('post ' . $pid)),
                (string) ($rec['post_title'] ?? ''),
                $reden);
        }
        if ($wees && !$dry) {
            foreach (array_chunk($wees, 500) as $batch) {
                $index->deleteObjects($batch);
            }
        }
        $totaal += count($wees);
        $regels[] = sprintf('<strong>%s</strong>: %d records bekeken, %d weesrecord(s) over %d artikel(en)%s',
            esc_html($naam), $gezien, count($wees), count($details), ($dry || !$wees) ? '' : ' — verwijderd');
        foreach (array_slice($details, 0, 25) as $r) {
            $regels[] = '&nbsp;&nbsp;&bull; ' . esc_html($r);
        }
        if (count($details) > 25) {
            $regels[] = sprintf('&nbsp;&nbsp;&hellip; en nog %d', count($details) - 25);
        }
    }
    if (!$regels) {
        $regels[] = 'Geen product-indexen gevonden onder prefix ' . esc_html($prefix);
    }

    wp_die(sprintf('%sIndex opschonen klaar: %d record(s)%s.<br><br>%s',
        $dry ? '<strong>DRY RUN</strong> — ' : '',
        $totaal,
        $dry ? ' zouden worden verwijderd' : ' verwijderd',
        implode('<br>', $regels)
    ));
});
