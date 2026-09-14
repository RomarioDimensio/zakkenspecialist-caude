/* =====================================================================
   Header-zoek
   Klik op het vergrootglas in de header -> een zoekbalk wipet van rechts
   (vanaf het icoon) naar links over de menu-items heen. Enter zoekt in de
   productenzoeker: staat de bezoeker al op /onze-producten/, dan verfijnen
   we de bestaande InstantSearch-instantie zonder herladen; anders sturen
   we hem naar /onze-producten/?q=<term>.

   Het veld zit in een echt <form action="/onze-producten/" method="get">
   met name="q", dus zonder JS werkt het ook gewoon (submit = de juiste URL).
   ===================================================================== */
(function () {
    'use strict';

    const cfg = window.DZS_HEADER_SEARCH || {};
    const PRODUCTEN_URL = cfg.productenUrl || '/onze-producten/';

    const header = document.querySelector('header.elementor-location-header');
    if (!header) return;

    // Het vergrootglas is een Elementor icon-widget in de rechter container
    // (dezelfde container als het menu). Die container wordt ons referentiekader.
    const iconWidget = header.querySelector('.elementor-widget-icon');
    const host = iconWidget && iconWidget.closest('.e-con');
    if (!iconWidget || !host) return;
    if (host.querySelector('.dzs-hzoek')) return;   // dubbel initialiseren voorkomen

    host.classList.add('dzs-hzoek-host');
    iconWidget.classList.add('dzs-hzoek-knop');

    /* --- paneel bouwen --------------------------------------------------- */
    const paneel = document.createElement('div');
    paneel.className = 'dzs-hzoek';

    const form = document.createElement('form');
    form.className = 'dzs-hzoek__form';
    form.setAttribute('role', 'search');
    form.action = PRODUCTEN_URL;
    form.method = 'get';

    const label = document.createElement('label');
    label.className = 'dzs-hzoek__label';
    label.htmlFor = 'dzs-hzoek-input';
    label.textContent = cfg.label || 'Zoek een product';

    const input = document.createElement('input');
    input.className = 'dzs-hzoek__input';
    input.id = 'dzs-hzoek-input';
    // BEWUST type="text" en niet type="search": algolia-product-search.js zoekt
    // de zoekbalk boven het grid op met o.a. input[type="search"] en zou dan
    // deze header-input kapen als InstantSearch-searchbox.
    input.type = 'text';
    input.name = 'q';
    input.inputMode = 'search';
    input.autocomplete = 'off';
    input.placeholder = cfg.placeholder || 'Zoek een product...';

    form.append(label, input);
    paneel.appendChild(form);
    host.appendChild(paneel);

    // Staat er al een zoekterm in de URL (/onze-producten/?q=...)? Zet die in het
    // veld, zodat de header laat zien waarop op dit moment gezocht wordt.
    const huidigeTerm = new URLSearchParams(window.location.search).get('q');
    if (huidigeTerm) input.value = huidigeTerm;

    /* De menu-container is iets minder hoog dan de headerbalk zelf. Zonder
       correctie blijft er een lichte strook boven/onder het paneel zichtbaar,
       dus rekken we het paneel op tot de balkhoogte. */
    // de headerbalk = de vaste (position:fixed) container in de header; valt
    // die weg, dan de buitenste container van de header
    const balk = (() => {
        let el = host.parentElement;
        while (el && el !== header) {
            if (getComputedStyle(el).position === 'fixed') return el;
            el = el.parentElement;
        }
        return header.querySelector('.e-con');
    })();
    const stelHoogteIn = () => {
        if (!balk) return;
        const b = balk.getBoundingClientRect();
        const h = host.getBoundingClientRect();
        if (!b.height || !h.height) return;
        paneel.style.top = (b.top - h.top) + 'px';
        paneel.style.bottom = (h.bottom - b.bottom) + 'px';
    };
    stelHoogteIn();
    window.addEventListener('resize', stelHoogteIn);

    /* Menu-items faden weg in de looprichting van de wipe (rechts -> links).
       Het rechtse item wordt als eerste bedekt en verdwijnt dus als eerste;
       bij sluiten draait de volgorde om. De vertragingen staan als custom
       properties op de li's, de fade zelf zit in de CSS. */
    const MAX_STAGGER = 260;
    // per menu apart: Elementor rendert naast het desktopmenu ook een
    // (verborgen) kopie voor de burger-dropdown
    host.querySelectorAll('ul.elementor-nav-menu').forEach((ul) => {
        const items = [...ul.children];
        const laatste = items.length - 1;
        items.forEach((li, i) => {
            const vanRechts = laatste ? (laatste - i) / laatste : 0;   // 0 = meest rechts
            li.style.setProperty('--dzs-d', Math.round(vanRechts * MAX_STAGGER) + 'ms');
            li.style.setProperty('--dzs-drev', Math.round((1 - vanRechts) * MAX_STAGGER) + 'ms');
        });
    });

    /* --- openen / sluiten ------------------------------------------------ */
    iconWidget.setAttribute('role', 'button');
    iconWidget.setAttribute('tabindex', '0');
    iconWidget.setAttribute('aria-label', 'Zoeken');
    iconWidget.setAttribute('aria-expanded', 'false');
    iconWidget.setAttribute('aria-controls', 'dzs-hzoek-input');

    let open = false;
    const zetOpen = (nieuw) => {
        open = nieuw;
        if (open) stelHoogteIn();
        host.classList.toggle('dzs-hzoek-open', open);
        iconWidget.setAttribute('aria-expanded', open ? 'true' : 'false');
        iconWidget.setAttribute('aria-label', open ? 'Zoeken sluiten' : 'Zoeken');
        if (open) {
            // focus pas als de wipe loopt; direct focussen scrollt sommige
            // browsers naar het veld toe
            requestAnimationFrame(() => { input.focus(); input.select(); });
        } else {
            input.blur();
        }
    };

    iconWidget.addEventListener('click', (e) => {
        e.preventDefault();
        zetOpen(!open);
    });
    iconWidget.addEventListener('keydown', (e) => {
        if (e.key !== 'Enter' && e.key !== ' ') return;
        e.preventDefault();
        zetOpen(!open);
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && open) zetOpen(false);
    });

    document.addEventListener('click', (e) => {
        if (!open) return;
        if (paneel.contains(e.target) || iconWidget.contains(e.target)) return;
        zetOpen(false);
    });

    /* --- zoeken ---------------------------------------------------------- */
    form.addEventListener('submit', (e) => {
        const term = input.value.trim();

        if (!term) {                    // leeg: niets doen, wel gefocust blijven
            e.preventDefault();
            input.focus();
            return;
        }

        // Staan we al op een pagina met de productenzoeker? Dan verfijnen we
        // live (window.DIM_AIS wordt gezet in algolia-product-search.js) en
        // scheelt dat een paginalading. Routing zet ?q= zelf in de URL.
        const zoeker = window.DIM_AIS;
        if (zoeker && zoeker.helper) {
            e.preventDefault();
            zoeker.helper.setQuery(term).setPage(0).search();
            zetOpen(false);
            document.getElementById('dim-products-fsearch')
                ?.scrollIntoView({ behavior: 'smooth', block: 'start' });
            return;
        }

        // Anders: gewone GET-submit -> /onze-producten/?q=<term>
    });
})();
