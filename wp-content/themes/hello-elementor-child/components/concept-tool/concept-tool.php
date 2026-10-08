<?php
/**
 * Eenmalige opschoonactie (okt 2026): vervallen artikelen op Concept zetten.
 *
 * De lijst hieronder = de Excel van Romario (article_set_on_concept.xlsx)
 * plus alle artikelcodes die eindigen op -970 (53 stuks, feedback collega's).
 * Concept i.p.v. verwijderen: alle data (foto's, video's, specs) blijft
 * bewaard voor als een artikel ooit terugkomt.
 *
 * Gebruik, ingelogd als beheerder:
 *   ?dim_artikelen_concept=1&dry=1   eerst kijken — verandert niets
 *   ?dim_artikelen_concept=1         uitvoeren
 *
 * Daarna in deze volgorde:
 *   1. ?dim_opruim_algolia=1&dry=1   (controle)
 *   2. ?dim_opruim_algolia=1         (haalt ze uit de zoekindex)
 *   3. LiteSpeed Cache -> Toolbox -> Purge All
 *
 * Dit bestand mag na de actie weer van de server (doet niets zonder de URL).
 */

add_action('admin_init', function () {
    if (!current_user_can('manage_options')) return;
    if (empty($_GET['dim_artikelen_concept'])) return;
    @set_time_limit(0);
    @ini_set('memory_limit', '512M');
    ignore_user_abort(true);

    $codes = [
        '1001094-970', '1001304', '1001310-970', '1001313-970', '1001314', '1001315-970',
        '1001400', '1001623', '1001703', '1002040-970', '1002070-970', '1002075-970',
        '1002139', '1002220-970', '1002243', '1002321-970', '1002328-970', '1002351-970',
        '1002420-970', '1002460-970', '1002500-970', '1002503-970', '1002573-970', '1011059',
        '1011385', '1011544', '1011557', '1011588-970', '1011684', '1011767',
        '1011839', '1012022', '1012024', '1012230-970', '1012372', '1012582',
        '1012610-970', '2001013-970', '2002020-970', '2002094-970', '2002124-970', '2002130-970',
        '2002131-970', '2002132-970', '2002133-970', '2002134-970', '2002135-970', '2002136-970',
        '2002137-970', '2002138-970', '2002139-970', '2002142-970', '2002167', '2002216-970',
        '2002230-970', '2002251', '2002252', '2002253', '2002254', '2002256',
        '2002257', '2002258', '2002259', '2002300-970', '2002311-970', '2002351',
        '2002360-970', '2002410-970', '2002411-970', '2002412-002-970', '2002412-003-970', '2002414-970',
        '2002460', '2002502-970', '2002520-970', '2002522-970', '2002534', '2002591',
        '2012028', '2012044', '2012093', '2012164-970', '2012333', '2012405-970',
        '2012675-970', '2302051-970', '2302057-970', '2302161-970', '9102373', '9103111',
        '9103203',
    ];

    $dry = !empty($_GET['dry']);
    $op_concept = []; $al_weg = []; $niet_gevonden = [];

    foreach ($codes as $code) {
        $posts = get_posts([
            'post_type' => 'product', 'post_status' => 'any',
            'posts_per_page' => -1, 'fields' => 'ids',
            'meta_key' => 'artikelcode', 'meta_value' => $code,
        ]);
        if (!$posts) { $niet_gevonden[] = $code; continue; }
        foreach ($posts as $pid) {
            $status = get_post_status($pid);
            if ($status !== 'publish') { $al_weg[] = $code . ' (' . $status . ')'; continue; }
            if (!$dry) {
                wp_update_post([ 'ID' => $pid, 'post_status' => 'draft' ]);
            }
            $op_concept[] = $code;
        }
    }

    $kop = $dry ? 'DROOGLOOP — er is niets gewijzigd.' : 'Uitgevoerd.';
    wp_die(sprintf(
        '%s<br><br><strong>%d op concept%s</strong>:<br>%s<br><br><strong>%d al niet gepubliceerd</strong>: %s<br><br><strong>%d niet gevonden</strong>: %s<br><br>Vervolg: draai ?dim_opruim_algolia=1&dry=1, dan zonder dry, en purge daarna LiteSpeed (Toolbox -> Purge All).',
        $kop,
        count($op_concept), $dry ? ' te zetten' : ' gezet',
        esc_html(implode(', ', $op_concept)),
        count($al_weg), esc_html(implode(', ', $al_weg) ?: '-'),
        count($niet_gevonden), esc_html(implode(', ', $niet_gevonden) ?: '-')
    ));
});
