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
            <a href="<?php echo esc_url(admin_url('index.php?dim_reindex_producten=1')); ?>">zoekindex verversen</a>
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
add_action('admin_init', function () {
    if (!current_user_can('manage_options')) return;
    if (empty($_GET['dim_reindex_producten'])) return;
    @set_time_limit(0);
    @ini_set('memory_limit', '512M');
    ignore_user_abort(true);
    $q = new WP_Query([
        'post_type' => 'product', 'post_status' => 'publish',
        'posts_per_page' => -1, 'fields' => 'ids', 'no_found_rows' => true,
    ]);
    $n = 0;
    foreach ($q->posts as $pid) {
        wp_update_post([ 'ID' => $pid ]);   // triggert de Algolia-push van de plugin
        $n++;
    }
    wp_die(sprintf('Re-index klaar: %d producten opnieuw naar Algolia gepusht.', $n));
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
