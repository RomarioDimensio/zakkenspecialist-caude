<?php
/**
 * Productveld-helpers (SAP-normalisatie).
 * - toepassingen: multi-select; CSV-import levert "a|b|c" -> array
 * - admin-actie ?dim_import_csv=1[&dry=1] importeert het klaargezette CSV-bestand
 *   (wp-content/uploads/dim-import/wp_producten_import.csv) via de Dimensio-importer,
 *   zonder handmatige upload.
 */
if (!defined('ABSPATH')) exit;

// CSV levert pipe-gescheiden strings voor multi-select velden; ACF verwacht een array.
add_filter('acf/update_value/name=toepassingen', function ($value) {
    if (is_string($value)) {
        $value = array_values(array_filter(array_map('trim', explode('|', $value))));
    }
    return $value;
}, 10, 1);

/* --------------------------------------------------------------------------
 * Admin-pagina: Producten -> Foto's — overzicht van alle productfoto's
 * met bron (beeldbank / generiek / geen) en filters.
 * ------------------------------------------------------------------------ */
add_action('admin_menu', function () {
    add_submenu_page(
        'edit.php?post_type=product',
        'Productfoto' . "\xE2\x80\x99" . 's',
        'Foto' . "\xE2\x80\x99" . 's',
        'manage_options',
        'dim-product-fotos',
        'dim_render_product_fotos_pagina'
    );
});

function dim_render_product_fotos_pagina(): void {
    $q = new WP_Query([ 'post_type' => 'product', 'post_status' => 'publish',
        'posts_per_page' => -1, 'fields' => 'ids', 'no_found_rows' => true ]);

    $items = []; $tel = [ 'beeldbank' => 0, 'generiek' => 0, 'geen' => 0 ];
    foreach ($q->posts as $pid) {
        $thumb_id = get_post_thumbnail_id($pid);
        $bron = 'geen';
        if ($thumb_id) {
            $bestand = basename((string) get_attached_file($thumb_id));
            $code = trim((string) get_field('artikelcode', $pid));
            if ($code !== '' && strpos($bestand, $code . '_1') === 0) $bron = 'beeldbank';
            elseif (strpos($bestand, 'DZS_zak-') === 0) $bron = 'generiek';
            else $bron = 'beeldbank'; // handmatig gekozen eigen foto telt als eigen
        }
        $tel[$bron]++;
        $items[] = [ 'pid' => $pid, 'thumb' => $thumb_id, 'bron' => $bron ];
    }

    $filter = isset($_GET['bron']) ? sanitize_key($_GET['bron']) : '';
    $basis = admin_url('edit.php?post_type=product&page=dim-product-fotos');
    $labels = [ '' => 'Alle (' . count($items) . ')',
        'beeldbank' => 'Eigen foto (' . $tel['beeldbank'] . ')',
        'generiek' => 'Generieke kleurfoto (' . $tel['generiek'] . ')',
        'geen' => 'Geen foto (' . $tel['geen'] . ')' ];
    ?>
    <div class="wrap">
        <h1>Productfoto&rsquo;s</h1>
        <p>
            <?php foreach ($labels as $sleutel => $label) :
                $url = $sleutel === '' ? $basis : $basis . '&bron=' . $sleutel;
                $actief = ($filter === $sleutel); ?>
                <a href="<?php echo esc_url($url); ?>" class="button<?php echo $actief ? ' button-primary' : ''; ?>" style="margin-right:6px"><?php echo esc_html($label); ?></a>
            <?php endforeach; ?>
        </p>
        <p style="color:#646970">
            Acties: <a href="<?php echo esc_url(admin_url('index.php?dim_koppel_fotos=1')); ?>">ontbrekende foto&rsquo;s koppelen</a> &middot;
            <a href="<?php echo esc_url(admin_url('index.php?dim_vervang_fotos=1')); ?>">transparante versies doorvoeren</a> &middot;
            <a href="<?php echo esc_url(admin_url('index.php?dim_reindex_producten=1')); ?>">zoekindex verversen</a> &middot;
            <a href="<?php echo esc_url(admin_url('index.php?dim_opruim_algolia=1&dry=1')); ?>">zoekindex opschonen (proef)</a>
        </p>
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:12px">
            <?php foreach ($items as $it) :
                if ($filter !== '' && $it['bron'] !== $filter) continue; ?>
                <a href="<?php echo esc_url(get_edit_post_link($it['pid'])); ?>" style="background:#fff;border:1px solid #dcdcde;border-radius:6px;padding:8px;text-decoration:none;color:#1d2327">
                    <div style="height:120px;display:flex;align-items:center;justify-content:center;background:#f6f7f7;border-radius:4px">
                        <?php if ($it['thumb']) {
                            echo wp_get_attachment_image($it['thumb'], [ 110, 110 ], false, [ 'loading' => 'lazy', 'style' => 'max-height:110px;width:auto' ]);
                        } else {
                            echo '<span style="color:#b32d2e">geen foto</span>';
                        } ?>
                    </div>
                    <div style="font-size:12px;margin-top:6px;line-height:1.3">
                        <strong><?php echo esc_html(get_field('artikelcode', $it['pid'])); ?></strong><br>
                        <?php echo esc_html(wp_trim_words(get_the_title($it['pid']), 7)); ?><br>
                        <span style="color:#646970"><?php echo $it['bron'] === 'beeldbank' ? 'eigen foto' : esc_html($it['bron']); ?></span>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
    <?php
}

// Debug: test de beeldbank-verbinding voor één artikelcode: ?dim_foto_test=1012231-001
add_action('admin_init', function () {
    if (!current_user_can('manage_options')) return;
    if (empty($_GET['dim_foto_test'])) return;
    $code = sanitize_text_field($_GET['dim_foto_test']);
    $url = 'https://beeldbank.diminfra.nl/thumbnail/data/' . rawurlencode($code) . '/' . rawurlencode($code) . '_1.jpg';
    // reproduceer de exacte flow van dim_koppel_fotos (filter-route + download + sideload)
    add_filter('http_request_args', function ($args, $u) {
        if (strpos($u, 'beeldbank.diminfra.nl') !== false) $args['sslverify'] = false;
        return $args;
    }, 10, 2);
    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/media.php';
    require_once ABSPATH . 'wp-admin/includes/image.php';
    $head = wp_remote_head($url, [ 'timeout' => 15 ]);
    $head_txt = is_wp_error($head) ? 'WP_Error: ' . $head->get_error_message() : (string) wp_remote_retrieve_response_code($head);
    $tmp = download_url($url, 30);
    $dl_txt = is_wp_error($tmp) ? 'WP_Error: ' . $tmp->get_error_message() : 'OK ' . filesize($tmp) . ' bytes';
    $side_txt = '(niet geprobeerd)';
    if (!is_wp_error($tmp)) {
        $sid = media_handle_sideload([ 'name' => $code . '_1.jpg', 'tmp_name' => $tmp ], 0);
        $side_txt = is_wp_error($sid) ? 'WP_Error: ' . $sid->get_error_message() : 'attachment #' . $sid;
    }
    wp_die('URL: ' . esc_html($url) . '<br>HEAD(filter): ' . esc_html($head_txt)
        . '<br>download_url: ' . esc_html($dl_txt) . '<br>sideload: ' . esc_html($side_txt));
});

// Foto's koppelen: wp-admin openen met ?dim_koppel_fotos=1  (optioneel &force=1 om
// bestaande thumbnails te overschrijven). Prioriteit per product:
//   1. échte productfoto uit de beeldbank op artikelcode
//      (https://beeldbank.diminfra.nl/thumbnail/data/{code}/{code}_1.jpg, 800x800)
//   2. generieke zakfoto per kleur: DZS_zak-{kleur}_360_0001_{A|B} uit de Media Library
//      (A = korte zak, B = lange zak; lengte/breedte >= 1.5 telt als lang; grijs -> grijs_zwart)
//   3. geen match -> placeholder blijft
// Bonus: 360-video per kleur (DZS_zak-{kleur}_360_{A|B}_2sec) in het ACF-veld 360_video_view.
add_action('admin_init', function () {
    if (!current_user_can('manage_options')) return;
    if (empty($_GET['dim_koppel_fotos'])) return;
    @set_time_limit(0);
    @ini_set('memory_limit', '512M');
    ignore_user_abort(true);
    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/media.php';
    require_once ABSPATH . 'wp-admin/includes/image.php';

    // De lokale Docker-container mist het CA-certificaat van beeldbank.diminfra.nl
    // (cURL error 60), terwijl het certificaat publiek geldig is. Alleen voor deze
    // host de verificatie uitzetten — geldt uitsluitend binnen deze actie.
    add_filter('http_request_args', function ($args, $url) {
        if (strpos($url, 'beeldbank.diminfra.nl') !== false) $args['sslverify'] = false;
        return $args;
    }, 10, 2);

    $force = !empty($_GET['force']);
    $vind = function (string $titel): int {
        $r = get_posts([ 'post_type' => 'attachment', 'post_status' => 'inherit',
            'title' => $titel, 'posts_per_page' => 1, 'fields' => 'ids' ]);
        return $r ? (int) $r[0] : 0;
    };

    $q = new WP_Query([ 'post_type' => 'product', 'post_status' => 'publish',
        'posts_per_page' => -1, 'fields' => 'ids', 'no_found_rows' => true ]);
    $stat = [ 'beeldbank' => 0, 'generiek' => 0, 'video' => 0, 'al_gekoppeld' => 0, 'geen_match' => 0 ];

    foreach ($q->posts as $pid) {
        $code = trim((string) get_field('artikelcode', $pid));
        $kleur = strtolower(trim((string) strtok((string) get_field('kleuren', $pid), ',/')));
        $kleur = [ 'grijs' => 'grijs_zwart' ][ $kleur ] ?? $kleur;
        $b = (float) get_field('breedte_cm', $pid); $l = (float) get_field('lengte_cm', $pid);
        $volgorde = ($b > 0 && $l > 0 && $l / $b >= 1.5) ? [ 'B', 'A' ] : [ 'A', 'B' ];

        if ($force || !has_post_thumbnail($pid)) {
            $img_id = 0; $bron = '';
            // 1) beeldbank op artikelcode (hergebruik als al eerder gesideload)
            if ($code !== '') {
                $img_id = $vind($code . '_1');
                if (!$img_id) {
                    $url = 'https://beeldbank.diminfra.nl/thumbnail/data/' . rawurlencode($code) . '/' . rawurlencode($code) . '_1.jpg';
                    // NIET download_url(): wp_safe_remote_get weigert hosts op interne IPs
                    // (de beeldbank resolvet intern) — daarom zelf ophalen en wegschrijven.
                    $resp = wp_remote_get($url, [ 'timeout' => 30 ]);
                    if (!is_wp_error($resp) && wp_remote_retrieve_response_code($resp) === 200) {
                        $body = wp_remote_retrieve_body($resp);
                        if (strlen($body) > 1000) {
                            $tmp = wp_tempnam($code . '_1.jpg');
                            if ($tmp && file_put_contents($tmp, $body) !== false) {
                                $nieuw = media_handle_sideload([ 'name' => $code . '_1.jpg', 'tmp_name' => $tmp ], $pid);
                                if (is_wp_error($nieuw)) { @unlink($tmp); } else { $img_id = (int) $nieuw; }
                            }
                        }
                    }
                }
                if ($img_id) $bron = 'beeldbank';
            }
            // 2) generieke kleurfoto (A/B), bedrukte varianten (_DZS/_YAH) bewust overgeslagen
            if (!$img_id && $kleur !== '') {
                foreach ($volgorde as $ab) {
                    $img_id = $vind('DZS_zak-' . $kleur . '_360_0001_' . $ab);
                    if ($img_id) break;
                }
                if ($img_id) $bron = 'generiek';
            }
            if ($img_id) {
                set_post_thumbnail($pid, $img_id);
                update_field('product_image', $img_id, $pid);
                $stat[$bron]++;
            } else {
                $stat['geen_match']++;
            }
        } else {
            $stat['al_gekoppeld']++;
        }

        // bonus: 360-video per kleur (alleen vullen als het veld leeg is)
        if ($kleur !== '' && !get_field('360_video_view', $pid)) {
            foreach ($volgorde as $ab) {
                $vid = $vind('DZS_zak-' . $kleur . '_360_' . $ab . '_2sec');
                if ($vid) { update_field('360_video_view', $vid, $pid); $stat['video']++; break; }
            }
        }
    }

    wp_die(sprintf(
        'Foto-koppeling klaar. Beeldbank (artikelcode): %d | Generiek (kleur A/B): %d | 360-video gezet: %d | al gekoppeld (overgeslagen): %d | geen match: %d.<br>Draai nu ?dim_reindex_producten=1 zodat de fotos in de zoekindex komen.',
        $stat['beeldbank'], $stat['generiek'], $stat['video'], $stat['al_gekoppeld'], $stat['geen_match']
    ));
});

// Vervang beeldbank-foto's door de transparante versies uit
// uploads/dim-import/transparant/{code}_1.webp — zelfde attachment-ID's blijven,
// dus alle product-koppelingen blijven intact. Gebruik: ?dim_vervang_fotos=1
add_action('admin_init', function () {
    if (!current_user_can('manage_options')) return;
    if (empty($_GET['dim_vervang_fotos'])) return;
    @set_time_limit(0);
    @ini_set('memory_limit', '512M');
    ignore_user_abort(true);
    require_once ABSPATH . 'wp-admin/includes/image.php';

    $upload_dir = wp_upload_dir();
    $bron_map = trailingslashit($upload_dir['basedir']) . 'dim-import/transparant';
    if (!is_dir($bron_map)) wp_die('Map niet gevonden: ' . esc_html($bron_map));

    $vervangen = 0; $niet_gevonden = 0; $al_webp = 0;
    foreach (glob($bron_map . '/*_1.webp') as $bron) {
        $naam = basename($bron, '.webp');   // "{code}_1"
        $att = get_posts([ 'post_type' => 'attachment', 'post_status' => 'inherit',
            'title' => $naam, 'posts_per_page' => 1, 'fields' => 'ids' ]);
        if (!$att) { $niet_gevonden++; continue; }
        $att_id = (int) $att[0];
        $oud_pad = get_attached_file($att_id);
        if (!$oud_pad) { $niet_gevonden++; continue; }
        if (substr($oud_pad, -5) === '.webp') { $al_webp++; continue; }

        // oude derivaten + origineel opruimen
        $meta = wp_get_attachment_metadata($att_id);
        $map = trailingslashit(dirname($oud_pad));
        if (!empty($meta['sizes'])) {
            foreach ($meta['sizes'] as $s) { if (!empty($s['file'])) @unlink($map . $s['file']); }
        }
        @unlink($oud_pad);

        // nieuw bestand plaatsen en attachment bijwerken
        $nieuw_pad = $map . $naam . '.webp';
        copy($bron, $nieuw_pad);
        update_attached_file($att_id, $nieuw_pad);
        wp_update_post([ 'ID' => $att_id, 'post_mime_type' => 'image/webp' ]);
        $nieuwe_meta = wp_generate_attachment_metadata($att_id, $nieuw_pad);
        wp_update_attachment_metadata($att_id, $nieuwe_meta);
        $vervangen++;
    }

    wp_die(sprintf('Transparante foto-vervanging klaar: %d vervangen | %d al webp | %d attachment niet gevonden.<br>Draai nu ?dim_reindex_producten=1.',
        $vervangen, $al_webp, $niet_gevonden));
});

// Serverside re-index: pusht ALLE producten opnieuw naar Algolia in één request
// (betrouwbaarder dan de browser-batches van de plugin, die stoppen als de tab
// naar de achtergrond gaat). Gebruik: wp-admin openen met ?dim_reindex_producten=1
// (product + handschoen), of gericht: =product / =handschoen.
add_action('admin_init', function () {
    if (!current_user_can('manage_options')) return;
    if (empty($_GET['dim_reindex_producten'])) return;
    @set_time_limit(0);
    @ini_set('memory_limit', '512M');
    ignore_user_abort(true);
    $keuze = sanitize_text_field((string) $_GET['dim_reindex_producten']);
    $types = in_array($keuze, ['product', 'handschoen'], true) ? [$keuze] : ['product', 'handschoen'];
    $q = new WP_Query([
        'post_type' => $types, 'post_status' => 'publish',
        'posts_per_page' => -1, 'fields' => 'ids', 'no_found_rows' => true,
    ]);
    $n = 0;
    foreach ($q->posts as $pid) {
        wp_update_post([ 'ID' => $pid ]);   // triggert de Algolia-push van de plugin
        $n++;
    }
    wp_die(sprintf('Re-index klaar: %d posts (%s) opnieuw naar Algolia gepusht.', $n, implode(' + ', $types)));
});

// Serverside import van het klaargezette CSV-bestand (zelfde flow als de upload-pagina).
add_action('admin_init', function () {
    if (!current_user_can('manage_options')) return;
    if (empty($_GET['dim_import_csv'])) return;
    if (!function_exists('dg_run_product_import')) {
        wp_die('Dimensio import-plugin niet actief.');
    }
    $upload_dir = wp_upload_dir();
    $file = trailingslashit($upload_dir['basedir']) . 'dim-import/wp_producten_import.csv';
    if (!file_exists($file)) {
        wp_die('CSV niet gevonden: ' . esc_html($file));
    }
    $dry = !empty($_GET['dry']);
    // 604 producten x ACF-updates x synchrone Algolia-pushes > 30s PHP-limiet
    @set_time_limit(0);
    @ini_set('memory_limit', '512M');
    ignore_user_abort(true);
    $summary = dg_run_product_import($file, 'product-group', 'product', 'csv', $dry);
    wp_die(sprintf('%sImport klaar. Created: %d | Updated: %d | Skipped: %d %s',
        $dry ? '<strong>DRY RUN</strong> — ' : '',
        (int) $summary['created'], (int) $summary['updated'], (int) $summary['skipped'],
        !empty($summary['errors']) ? '<br>Fouten: ' . esc_html(implode(' | ', array_slice($summary['errors'], 0, 5))) : ''
    ));
});

/* --------------------------------------------------------------------------
 * RENDER-FOTO'S KOPPELEN — uniforme 3D-stills (STILL_IMAGES_DZS) als
 * productfoto voor alle zak- en kratzak-groepen, gekozen op kleur + model.
 * Bestanden: wp-content/uploads/dim-import/renders/render_*.webp (1200px).
 * Gebruik: wp-admin openen met ?dim_koppel_renders=1[&dry=1]
 * - één GEDEELD attachment per render (zelfde nette aanpak als de generieke
 *   DZS-foto's); oude foto's blijven in de mediabibliotheek staan.
 * - mapping: transparant -> wit_licht-render (besluit 2026-08-05);
 *   grijs -> grijs_zwart-still; Bio zakken -> Happy Sacks; model A/B via
 *   lengte/breedte >= 1.5; roze/transparant alleen model A.
 * Daarna draaien: ?dim_reindex_producten=product (medium_url in Algolia).
 * ------------------------------------------------------------------------ */

// Model A (kort) of B (lang) op basis van de verhouding lengte/breedte.
function dim_render_model(int $pid): string {
    $b = (float) get_field('breedte_cm', $pid);
    $l = (float) get_field('lengte_cm', $pid);
    return ($b > 0 && $l > 0 && ($l / $b) >= 1.5) ? 'B' : 'A';
}

add_action('admin_init', function () {
    if (!current_user_can('manage_options')) return;
    if (empty($_GET['dim_koppel_renders'])) return;
    @set_time_limit(0);
    @ini_set('memory_limit', '512M');
    ignore_user_abort(true);
    $dry = !empty($_GET['dry']);

    // ?dim_koppel_renders=vernieuw : eerst de afgeleide formaten (300px enz.) van alle
    // render-attachments opnieuw genereren — nodig nadat een webp in renders/ is
    // overschreven (bv. roze/transparant vrijstaand gemaakt); daarna gewoon doorkoppelen.
    $vernieuwd = 0;
    if ($_GET['dim_koppel_renders'] === 'vernieuw' && !$dry) {
        require_once ABSPATH . 'wp-admin/includes/image.php';
        $render_atts = get_posts([
            'post_type' => 'attachment', 'post_status' => 'inherit',
            'posts_per_page' => -1, 'fields' => 'ids', 's' => 'DZS render',
        ]);
        foreach ($render_atts as $ra) {
            $pad = get_attached_file($ra);
            if ($pad && file_exists($pad)) {
                wp_update_attachment_metadata($ra, wp_generate_attachment_metadata($ra, $pad));
                $vernieuwd++;
            }
        }
    }

    $zak_groepen = [
        'HDPE Afvalzakken rol', 'LDPE Afvalzakken rol', 'HDPE Pedaalemmer rol',
        'LDPE Afvalzakken los', 'HDPE Afvalzakken los', 'MDPE zakken', 'LDPE zak',
        'HDPE zakken', 'LDPE trekbandzakken', 'HDPE trekbandzakken',
        'MDPE trekbandzakken', 'Bio zakken', 'LDPE Harmonika los',
    ];
    $krat_groepen = [ 'HDPE kratzakken', 'LDPE Kratzakken' ];
    $zak_ab_kleuren = [ 'blauw', 'bruin', 'geel', 'grijs', 'groen', 'oranje', 'rood', 'wit', 'zwart' ];
    $krat_kleuren   = [ 'blauw', 'transparant', 'rood', 'groen', 'geel', 'oranje', 'paars', 'wit', 'zwart' ];

    $uploads  = wp_get_upload_dir();
    $rend_pad = trailingslashit($uploads['basedir']) . 'dim-import/renders/';

    // Eén gedeeld attachment per render-bestand (hervindbaar op titel, dus herdraaibaar).
    $attach_cache = [];
    $attachment_voor = function (string $bestand) use (&$attach_cache, $rend_pad, $dry) {
        if (array_key_exists($bestand, $attach_cache)) return $attach_cache[$bestand];
        $titel = 'DZS render ' . preg_replace('/\.webp$/', '', $bestand);
        $bestaand = get_posts([
            'post_type' => 'attachment', 'post_status' => 'inherit',
            'title' => $titel, 'posts_per_page' => 1, 'fields' => 'ids',
        ]);
        if ($bestaand) return $attach_cache[$bestand] = (int) $bestaand[0];
        if (!file_exists($rend_pad . $bestand)) return $attach_cache[$bestand] = 0;
        if ($dry) return $attach_cache[$bestand] = -1;   // zou aangemaakt worden
        $id = wp_insert_attachment([
            'post_title'     => $titel,
            'post_mime_type' => 'image/webp',
            'post_status'    => 'inherit',
        ], $rend_pad . $bestand);
        if (is_wp_error($id) || !$id) return $attach_cache[$bestand] = 0;
        require_once ABSPATH . 'wp-admin/includes/image.php';
        wp_update_attachment_metadata($id, wp_generate_attachment_metadata($id, $rend_pad . $bestand));
        return $attach_cache[$bestand] = (int) $id;
    };

    // Bepaal het render-bestand voor een product; null = niet in scope / geen match.
    $via_titel = 0;
    $kies_bestand = function (int $pid) use ($zak_groepen, $krat_groepen, $zak_ab_kleuren, $krat_kleuren, &$via_titel) {
        $groep = trim((string) get_field('groep', $pid));
        $titel = (string) get_the_title($pid);
        // kratzak herkennen op de NAAM óók als de SAP-groep anders is
        // (bv. KZ6053: titel "MDPE kratzakken ..." maar groep "MDPE zakken")
        $is_krat = in_array($groep, $krat_groepen, true) || stripos($titel, 'kratzak') !== false;
        $is_zak  = !$is_krat && in_array($groep, $zak_groepen, true);
        if (!$is_zak && !$is_krat) return [null, 'geen zak/kratzak-groep'];

        if ($groep === 'Bio zakken') {   // besluit: Bio zakken = Happy Sacks-render
            return [ 'render_zak_happysacks_' . dim_render_model($pid) . '.webp', null ];
        }

        $kleur_raw = mb_strtolower(trim((string) get_field('kleuren', $pid)));
        $tokens = array_values(array_filter(array_map('trim', preg_split('/[\/,]/', $kleur_raw))));
        if (!$tokens) {
            // kleuren-veld leeg: kleur uit de productnaam halen (vaste prioriteit,
            // blauw vóór transparant; SAP-afkortingen blw/trp komen in namen voor,
            // bv. 2011416 "60/20 x 80cm T 10 Trp/blw")
            $lt = mb_strtolower($titel);
            $kleur_zoek = [
                'blauw' => ['blauw', 'blw'], 'zwart' => ['zwart'], 'grijs' => ['grijs'],
                'wit' => ['wit'], 'geel' => ['geel'], 'groen' => ['groen'], 'rood' => ['rood'],
                'bruin' => ['bruin'], 'oranje' => ['oranje'], 'roze' => ['roze'],
                'paars' => ['paars'], 'transparant' => ['transparant', 'trp'],
            ];
            foreach ($kleur_zoek as $kleur => $naalden) {
                foreach ($naalden as $n) {
                    if (strpos($lt, $n) !== false) { $tokens = [$kleur]; break 2; }
                }
            }
            if ($tokens) $via_titel++;
        }
        if (!$tokens) return [null, 'geen kleur'];

        foreach ($tokens as $t) {
            if ($is_krat) {
                if (in_array($t, $krat_kleuren, true)) return [ "render_kratzak_{$t}.webp", null ];
                continue;
            }
            // transparant/roze hebben nu óók een model B (picker-compositing "Zak Slank"),
            // dus gewoon de A/B-formule volgen — variant-groepen blijven zo consistent.
            if ($t === 'transparant') return [ 'render_zak_transparant_' . dim_render_model($pid) . '.webp', null ];
            if ($t === 'roze')        return [ 'render_zak_roze_' . dim_render_model($pid) . '.webp', null ];
            if (in_array($t, $zak_ab_kleuren, true)) {
                return [ 'render_zak_' . $t . '_' . dim_render_model($pid) . '.webp', null ];
            }
        }
        return [null, 'geen render voor kleur "' . $kleur_raw . '"'];
    };

    $q = new WP_Query([
        'post_type' => 'product', 'post_status' => 'publish',
        'posts_per_page' => -1, 'fields' => 'ids', 'no_found_rows' => true,
    ]);

    $gezet = 0; $al_goed = 0; $buiten_scope = 0; $geen_match = 0; $fout = 0;
    $per_bestand = []; $voorbeelden_mis = [];
    foreach ($q->posts as $pid) {
        [$bestand, $reden] = $kies_bestand($pid);
        if ($bestand === null) {
            if ($reden === 'geen zak/kratzak-groep') { $buiten_scope++; }
            else {
                $geen_match++;
                if (count($voorbeelden_mis) < 8) {
                    $voorbeelden_mis[] = get_field('artikelcode', $pid) . ' (' . $reden . ')';
                }
            }
            continue;
        }
        $aid = $attachment_voor($bestand);
        if ($aid === 0) { $fout++; if (count($voorbeelden_mis) < 8) $voorbeelden_mis[] = $bestand . ' ontbreekt in renders/'; continue; }
        $per_bestand[$bestand] = ($per_bestand[$bestand] ?? 0) + 1;
        if (!$dry) {
            // grid gebruikt de featured image; de DETAILVIEW (Elementor-template)
            // toont het ACF-veld product_image — beide moeten dus mee.
            $thumb_ok = ((int) get_post_thumbnail_id($pid) === $aid);
            $acf_ok   = ((int) get_post_meta($pid, 'product_image', true) === $aid);
            if ($thumb_ok && $acf_ok) { $al_goed++; continue; }
            if (!$thumb_ok) set_post_thumbnail($pid, $aid);
            if (!$acf_ok)   update_field('product_image', $aid, $pid);
        }
        $gezet++;
    }

    ksort($per_bestand);
    $verdeling = implode('<br>', array_map(
        fn($b, $n) => esc_html("$b: $n"), array_keys($per_bestand), $per_bestand
    ));
    wp_die(sprintf(
        '%sRender-koppeling klaar. Foto gezet: %d | al goed (overgeslagen): %d | buiten scope (folie/grip/hoes/...): %d | geen kleur-match: %d | kleur via productnaam: %d | fout: %d.%s<br><br><strong>Verdeling per render:</strong><br>%s%s<br><br>Volgende stap: <a href="%s">?dim_reindex_producten=product</a> zodat de zoekindex de nieuwe foto\'s krijgt.',
        $dry ? '<strong>DRY RUN</strong> — er is niets gewijzigd.<br>' : '',
        $gezet, $al_goed, $buiten_scope, $geen_match, $via_titel, $fout,
        $vernieuwd ? sprintf('<br>Afgeleide formaten vernieuwd voor %d render-attachments.', $vernieuwd) : '',
        $verdeling,
        $voorbeelden_mis ? '<br><br>Niet gekoppeld (voorbeelden):<br>' . esc_html(implode(' | ', $voorbeelden_mis)) : '',
        esc_url(admin_url('index.php?dim_reindex_producten=product'))
    ));
});

/* --------------------------------------------------------------------------
 * BEDRUKKING — intern een SAP-waarde, publiek een dienst
 *
 * Het ACF-veld `bedrukking` bevat wat er FEITELIJK op een product gedrukt is:
 * meestal de opdruk van één klant ("Knowaste Type B", "Dumoulin", "Le relais").
 * Dat hoort niet op een publieke productpagina — het is klantinformatie, en
 * niemand anders kan die opdruk bestellen. Wat de bezoeker wél wil weten is:
 * kan hier mijn eigen opdruk op?
 *
 * Daarom vertalen we de waarde bij het uitlezen naar "Mogelijk op aanvraag".
 * De ruwe SAP-waarde blijft ongemoeid in de database en in het ACF-scherm in
 * wp-admin staan (die gebruikt de onbewerkte waarde, niet format_value), dus
 * de import en de SAP-koppeling merken hier niets van.
 * ------------------------------------------------------------------------ */

/**
 * Is er een échte opdruk bekend? Een gevulde bedrukking is het bewijs dat dit
 * product bedrukt KAN worden — we hebben het immers al eens gedaan.
 * Waardes als "onbedrukt" of "no print" zeggen juist het tegendeel.
 */
function dim_bedrukking_is_gevuld( $post_id ): bool {
    if ( ! is_numeric( $post_id ) ) return false;
    $raw = trim( (string) get_post_meta( (int) $post_id, 'bedrukking', true ) );
    if ( $raw === '' ) return false;
    $niets = [ '.', '-', '0', 'geen', 'geen print', 'blanco', 'no print', 'noprint', 'onbedrukt' ];
    return ! in_array( mb_strtolower( $raw ), $niets, true );
}

add_filter( 'acf/format_value/name=bedrukking', function ( $value, $post_id, $field ) {
    return dim_bedrukking_is_gevuld( $post_id ) ? 'Mogelijk op aanvraag' : '';
}, 20, 3 );
