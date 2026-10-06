/* ---------------------------------------------------------------------------
 * Just Gloves — kleurwissel op de Elementor-opbouw van de pagina zelf.
 *
 *   knoppen  #jg-sw-blue / #jg-sw-black / #jg-sw-white
 *   video    #jg-p-vid (Elementor background-video; de <video> zit erin)
 *   foto's   #jg-p-img-{blue|black|white} — OPTIONEEL: de video is het beeld;
 *            zolang de foto's nog in de opbouw staan wisselen ze gewoon mee,
 *            haal je ze weg dan merkt dit script dat vanzelf.
 *
 * Gedrag: een klik op een kleurknop zet de video van die kleur in en speelt
 * hem precies ÉÉN keer (gedempt); daarna blijft het laatste frame staan —
 * geen loop, geen herstart bij nogmaals klikken op de actieve kleur. Bij het
 * laden speelt Elementors eigen autoplay de ingestelde startvideo één keer;
 * de knop van die kleur staat dan alvast op actief.
 *
 * De actieve knop krijgt class "is-active" en aria-pressed="true" —
 * de styling daarvan doe je zelf in Elementor (bv. custom CSS op de knop:
 * &.is-active { ... }). Dit script zet verder geen enkele opmaak.
 * ------------------------------------------------------------------------- */
(function () {
    'use strict';

    const VIDEOS = {
        blue:  '/wp-content/uploads/2026/04/DZS_handschoen_Glove_blauw_Mat_360_B_2sec.mp4',
        black: '/wp-content/uploads/2026/04/DZS_handschoen_Glove_zwart_Mat_360_B_2sec.mp4',
        white: '/wp-content/uploads/2026/04/DZS_handschoen_Glove_wit_Mat_360_B_2sec.mp4',
    };
    const KLEUREN = Object.keys(VIDEOS);

    const videoWrap = document.getElementById('jg-p-vid');
    const video = videoWrap && videoWrap.querySelector('video');
    if (!video) return;   // niet op deze pagina

    const bron = video.querySelector('source');
    const rustigAan = window.matchMedia('(prefers-reduced-motion: reduce)');

    video.loop = false;          // één keer; daarna blijft het laatste frame staan
    video.muted = true;
    video.playsInline = true;

    /* Foto's zijn optioneel: alleen aansturen wat er daadwerkelijk staat. */
    const fotos = {};
    const eigenDisplay = {};
    KLEUREN.forEach((k) => {
        const el = document.getElementById('jg-p-img-' + k);
        if (!el) return;
        fotos[k] = el;
        const d = getComputedStyle(el).display;
        eigenDisplay[k] = d !== 'none' ? d : 'block';
    });

    /* Startkleur aflezen uit de videobron die in Elementor is ingesteld,
       zodat de juiste knop meteen actief staat. */
    const huidigeBron = () => ((bron ? bron.getAttribute('src') : video.getAttribute('src')) || '');
    let huidig = KLEUREN.find((k) => huidigeBron().includes(VIDEOS[k].split('/').pop())) || 'blue';

    function toon(kleur) {
        KLEUREN.forEach((k) => {
            const knop = document.getElementById('jg-sw-' + k);
            if (knop) {
                knop.classList.toggle('is-active', k === kleur);
                knop.setAttribute('aria-pressed', k === kleur ? 'true' : 'false');
            }
            if (fotos[k]) fotos[k].style.display = (k === kleur) ? eigenDisplay[k] : 'none';
        });
    }
    toon(huidig);

    function kies(kleur) {
        if (kleur === huidig) return;   // zelfde kleur = niets doen (ook geen replay)
        huidig = kleur;
        toon(kleur);

        if (bron) bron.src = VIDEOS[kleur];
        else video.src = VIDEOS[kleur];
        video.load();
        // reduced motion: alleen het stilstaande eerste beeld, geen afspelen
        if (!rustigAan.matches) video.play().catch(() => {});
    }

    // Capture-fase: de knoppen zijn Elementor-containers en die handelen
    // kliks soms zelf eerder af (zelfde les als bij de zakkencalculator).
    document.addEventListener('click', (e) => {
        if (!e.target.closest) return;
        const knop = e.target.closest('#jg-sw-blue, #jg-sw-black, #jg-sw-white');
        if (!knop) return;
        kies(knop.id.replace('jg-sw-', ''));
    }, true);
})();
