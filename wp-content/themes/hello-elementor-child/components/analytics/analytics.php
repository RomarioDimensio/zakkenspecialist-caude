<?php
/**
 * GA4 + LeadInfo, AVG-proof via Google Consent Mode v2.
 *
 * Werking:
 *  - Vul hieronder de twee ID's in; zolang een ID leeg is laadt dat script
 *    helemaal niet (veilig op elke omgeving, ook lokaal).
 *  - Consent staat standaard op 'denied'. Complianz deblokkeert scripts na
 *    "accepteren" maar stuurt zelf GEEN Consent Mode-update; het bruggetje
 *    in het inline script hieronder vertaalt de Complianz-keuze daarom zelf
 *    naar gtag-consent. Vóór akkoord verstuurt GA4 alleen cookieloze pings
 *    en plaatst het géén cookies — precies wat de AVG wil.
 *  - Ingelogde redacteuren/beheerders en de Elementor-editor meten niet mee.
 *  - LeadInfo laadt ALTIJD (data-cmplz-ignore), óók zonder akkoord. Dat is
 *    alleen verdedigbaar zolang in het LeadInfo-portal (Instellingen ->
 *    Tracking) "cookieless tracking" AAN staat: dan is het B2B-herkenning
 *    op IP-basis zonder toestemmingsplichtige cookies. Zet je cookieless
 *    ooit uit, haal dan ook data-cmplz-ignore hieronder weg.
 */

const DIM_GA4_ID      = 'G-51T1J779HF';   // GA4-property "De zakkenspecialist"
const DIM_LEADINFO_ID = 'LI-5E4292AAE5D4B';   // bv. 'LI-XXXXXXXXXXXXX' — LeadInfo: Instellingen -> Tracking code

add_action('wp_head', function () {
    if (is_user_logged_in() && current_user_can('edit_posts')) return;
    if (isset($_GET['elementor-preview'])) return;

    // Alleen meten op de echte site: op localhost/staging laadt er niets,
    // ook al staan de ID's ingevuld — zo kan hetzelfde bestand overal heen
    // zonder dat lokale testklikken de cijfers vervuilen.
    if (strpos(home_url(), 'dezakkenspecialist.nl') === false) return;

    if (DIM_GA4_ID !== '') { ?>
<!-- GA4 met Consent Mode v2: alles 'denied' tot de bezoeker in de banner kiest -->
<script>
window.dataLayer = window.dataLayer || [];
function gtag(){dataLayer.push(arguments);}
gtag('consent', 'default', {
    ad_storage: 'denied',
    ad_user_data: 'denied',
    ad_personalization: 'denied',
    analytics_storage: 'denied',
    functionality_storage: 'granted',
    security_storage: 'granted',
    wait_for_update: 500
});
gtag('js', new Date());
gtag('config', '<?php echo esc_js(DIM_GA4_ID); ?>');

/* Brug Complianz -> Consent Mode. Zonder dit blijft elke hit op 'denied'
   staan (gcs=G100): geen _ga-cookies en een leeg Realtime-rapport, ook ná
   accepteren. Vuurt bij de keuze in de banner en, voor terugkerende
   bezoekers, bij elke paginalading. */
function dimCmplzNaarGtag() {
    if (typeof cmplz_has_consent !== 'function') return;
    var stats = cmplz_has_consent('statistics');
    var marketing = cmplz_has_consent('marketing');
    gtag('consent', 'update', {
        analytics_storage: stats ? 'granted' : 'denied',
        ad_storage: marketing ? 'granted' : 'denied',
        ad_user_data: marketing ? 'granted' : 'denied',
        ad_personalization: marketing ? 'granted' : 'denied'
    });
}
document.addEventListener('cmplz_enable_category', dimCmplzNaarGtag);
document.addEventListener('cmplz_status_change', dimCmplzNaarGtag);
document.addEventListener('cmplz_run_after_all_scripts', dimCmplzNaarGtag);
</script>
<script async src="https://www.googletagmanager.com/gtag/js?id=<?php echo esc_attr(DIM_GA4_ID); ?>"></script>
<?php }

    if (DIM_LEADINFO_ID !== '') { ?>
<!-- LeadInfo (B2B-bedrijfsherkenning). data-cmplz-ignore = altijd laden;
     mag alleen zolang "cookieless tracking" in het LeadInfo-portal AAN staat -->
<script data-cmplz-ignore>
(function(l,e,a,d,i,n,f,o){if(!l[i]){l.GlobalLeadinfoNamespace=l.GlobalLeadinfoNamespace||[];
l.GlobalLeadinfoNamespace.push(i);l[i]=function(){(l[i].q=l[i].q||[]).push(arguments)};l[i].t=l[i].t||n;
l[i].q=l[i].q||[];o=e.createElement(a);f=e.getElementsByTagName(a)[0];o.async=1;o.src=d;f.parentNode.insertBefore(o,f);}}
(window,document,'script','https://cdn.leadinfo.net/ping.js','leadinfo','<?php echo esc_js(DIM_LEADINFO_ID); ?>'));
</script>
<?php }
}, 4);
