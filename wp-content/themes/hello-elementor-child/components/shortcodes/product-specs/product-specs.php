<?php
/**
 * [dim_product_specs] — de ACF-velden van een product als specificatielijst.
 *
 * Volgorde en labels komen uit ACF zelf, dus je sleept een veld in de ACF-UI
 * naar boven en deze lijst volgt. Geen code aanpassen.
 *
 * WELKE VELDEN: alleen de veldgroepen die je meegeeft in `groepen` (standaard
 * "Product" en "Handschoenen"). Techniek die de bezoeker niets zegt —
 * Algolia-hulpvelden als dikte_waarde/dikte_eenheid, de SAP-mapping `groep`,
 * `prioriteit`, `custom_product_url` — hoort thuis in een aparte groep
 * "Product – intern". Die noem je hier gewoon niet, en dan verschijnt hij
 * nooit. Bewust géén exclude-lijst: die zou betekenen dat élk nieuw veld uit
 * de SAP-import automatisch publiek wordt, en dat is precies verkeerd om.
 *
 * LEGE REGELS: worden overgeslagen. Van de 51 productvelden staan er ~21 op
 * geen enkel product gevuld, dus dat scheelt het meeste.
 *
 * EENHEDEN: staan in het ACF-label tussen haakjes — "Breedte (cm)" wordt
 * label "Breedte" met waarde "60 cm". Zo hoef je hier nooit een lijstje met
 * eenheden bij te houden: je typt hem in ACF en het klopt.
 */

if (!defined('ABSPATH')) exit;

/**
 * Is deze waarde leeg voor de specificatielijst?
 *
 * Let op de 0: "Breedte: 0 cm" of "Inhoud: 0 liter" zegt niets, dus die telt
 * als leeg. Wil je 0 ooit wél tonen, dan is dit de plek.
 */
function dim_spec_is_leeg($waarde): bool {
    if ($waarde === null || $waarde === false || $waarde === '') return true;
    if (is_array($waarde))  return count($waarde) === 0;
    if (is_string($waarde)) return trim($waarde) === '';
    if (is_numeric($waarde)) return (float) $waarde === 0.0;
    return false;
}

/**
 * Haal de eenheid uit een ACF-label: "Breedte (cm)" -> ['Breedte', 'cm'].
 * Geen haakjes? Dan label ongewijzigd en geen eenheid.
 */
function dim_spec_label_en_eenheid(string $label): array {
    if (preg_match('/^(.*?)\s*\(([^)]+)\)\s*$/u', $label, $m)) {
        return [trim($m[1]), trim($m[2])];
    }
    return [$label, ''];
}

/**
 * Eén ACF-veld naar een tekstwaarde, of null als de regel weg moet.
 *
 * @param array $veld   ACF-velddefinitie (acf_get_fields)
 * @param mixed $waarde geformatteerde waarde uit get_field()
 */
function dim_spec_waarde(array $veld, $waarde): ?string {
    $type = $veld['type'] ?? 'text';

    switch ($type) {
        // Beeld en bestanden horen in het template, niet in een specificatierij.
        case 'image':
        case 'file':
        case 'gallery':
            return null;

        // Alleen tonen wat een zak WÉL heeft: "Trekband: Nee" is ruis.
        case 'true_false':
            return $waarde ? 'Ja' : null;

        // ACF geeft hier met format_value het label terug, niet de opgeslagen
        // waarde. Meervoudige keuzes komen als array binnen.
        case 'select':
        case 'radio':
        case 'checkbox':
        case 'button_group':
            if (is_array($waarde)) {
                $labels = array_map(
                    fn($v) => is_array($v) ? ($v['label'] ?? $v['value'] ?? '') : $v,
                    $waarde
                );
                return implode(', ', array_filter($labels));
            }
            return is_array($waarde) ? '' : (string) $waarde;

        case 'taxonomy':
            $termen = is_array($waarde) ? $waarde : [$waarde];
            $namen  = array_map(
                fn($t) => $t instanceof WP_Term ? $t->name : (string) $t,
                $termen
            );
            return implode(', ', array_filter($namen));

        case 'color_picker':
            // Het rondje zegt meer dan de hexcode; die zetten we in de title.
            $hex = esc_attr((string) $waarde);
            return '<span class="dim-spec-kleur" style="background:' . $hex . '" title="' . $hex . '"></span>';

        default:
            return is_array($waarde) ? implode(', ', array_filter($waarde)) : (string) $waarde;
    }
}

add_shortcode('dim_product_specs', function ($atts = []) {
    if (!function_exists('acf_get_field_groups')) return '';

    $atts = shortcode_atts([
        // Welke ACF-veldgroepen publiek zijn. Alles daarbuiten blijft onzichtbaar.
        'groepen' => 'Product,Handschoenen',
        // Incidentele uitzondering voor één template; de structurele keuze
        // maak je in ACF door het veld in de interne groep te zetten.
        'exclude' => '',
        'titel'   => '',
    ], $atts);

    $post_id = get_the_ID();
    if (!$post_id) return '';

    $publiek  = array_filter(array_map('trim', explode(',', $atts['groepen'])));
    $overslaan = array_filter(array_map('trim', explode(',', $atts['exclude'])));

    $rijen = [];
    foreach (acf_get_field_groups(['post_id' => $post_id]) as $groep) {
        if (!in_array($groep['title'], $publiek, true)) continue;

        foreach ((array) acf_get_fields($groep['key']) as $veld) {
            $naam = $veld['name'] ?? '';
            if (!$naam || in_array($naam, $overslaan, true)) continue;

            $ruw = get_field($naam, $post_id);
            if (dim_spec_is_leeg($ruw)) continue;

            $waarde = dim_spec_waarde($veld, $ruw);
            if ($waarde === null || trim($waarde) === '') continue;

            // Haakjes in het label zijn óf een eenheid ("Breedte (cm)") óf een
            // hint voor de invoerder ("Vorm (rol/los)"). Alleen bij een getal is
            // het een eenheid; anders zou "Vorm: los" onzin worden als
            // "los rol/los". Het haakje zelf halen we altijd uit het label —
            // dat is redactie-info, niet iets voor de bezoeker.
            [$label, $haakje] = dim_spec_label_en_eenheid((string) ($veld['label'] ?? $naam));
            if ($haakje !== '' && ($veld['type'] ?? '') === 'number') {
                $waarde .= ' ' . $haakje;
            }

            $rijen[] = ['label' => $label, 'waarde' => $waarde, 'veld' => $naam, 'type' => $veld['type']];
        }
    }

    if (!$rijen) return '';

    ob_start(); ?>
    <div class="dim-specs">
        <?php if ($atts['titel'] !== '') : ?>
            <h3 class="dim-specs__titel"><?php echo esc_html($atts['titel']); ?></h3>
        <?php endif; ?>
        <?php // Een echte tabel, geen <dl>: zo bepaalt het langste label vanzelf
              // de breedte van de linkerkolom en staan alle waardes uitgelijnd. ?>
        <table class="dim-specs__tabel">
            <tbody>
            <?php foreach ($rijen as $rij) : ?>
                <tr class="dim-spec-rij" data-veld="<?php echo esc_attr($rij['veld']); ?>">
                    <th scope="row"><?php echo esc_html($rij['label']); ?></th>
                    <td><?php
                        // color_picker levert bewust HTML (het kleurrondje); de rest is tekst.
                        echo $rij['type'] === 'color_picker' ? $rij['waarde'] : esc_html($rij['waarde']);
                    ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
    return ob_get_clean();
});
