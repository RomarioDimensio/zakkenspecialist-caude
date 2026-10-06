(function () {
    const layout = document.querySelector('.dim-products-layout');
    if (!layout) return;

    const panelContent = document.querySelector('#dim-product-detail-content');
    if (!panelContent) return;

    const queryParam = (window.dimProductPanel && window.dimProductPanel.queryParam) || 'product';
    const ajaxUrl = (window.dimProductPanel && window.dimProductPanel.ajaxUrl) || '/wp-admin/admin-ajax.php';

    let currentProductId = null;
    const productCache = new Map();          // post_id (string) -> {html}
    const norm = v => (v || '').toString().trim();

    function setOpenState(isOpen) {
        layout.classList.toggle('product-open', isOpen);
        if (isOpen) {
            requestAnimationFrame(markStickyHost);
            zetPaginaVast();
        } else {
            geefPaginaVrij();
        }
    }

    /* --- Scrollen terwijl het detail open staat ----------------------------
       Met het paneel open hoort alleen dat paneel te scrollen. Scrolde de
       pagina mee, dan liep je door een lang productgrid terwijl je een detail
       leest — dat is precies wat er niet fijn aan was.

       Aanpak: eerst het grid netjes onder de header schuiven (dan begint het
       detail altijd bovenaan in beeld), daarna de pagina op slot. Het paneel
       heeft zijn eigen max-height en overflow, dus dat blijft scrollen.

       Het slot zelf staat in de CSS en geldt alleen vanaf 1025px. Daaronder
       neemt het paneel de volle breedte en moet de pagina juist wél scrollen. */
    /* De bovenbalk (Wis alle filters · Terug · Zoeken · Sorteren) is de Elementor-rij
       waar #dim-remove-filters in zit. We hebben hem op twee plekken nodig — als
       scroll-anker en als hoogte voor de CSS — dus zoeken we hem één keer op:
       vanaf de knop omhoog tot de eerste container die vrijwel de volle breedte
       heeft. Dat is stabieler dan een Elementor-id, want die verandert zodra je
       de rij opnieuw opbouwt. */
    function zoekBovenbalk() {
        const knop = document.getElementById('dim-remove-filters');
        let el = knop && knop.closest('.e-con, .elementor-element');
        const vol = layout.getBoundingClientRect().width * 0.9;
        while (el && el.getBoundingClientRect().width < vol && el.parentElement) {
            el = el.parentElement;
        }
        return el;
    }

    /* Hoogtes van header en bovenbalk live meten en als CSS-variabele wegzetten.
       De CSS rekent daarmee uit hoe hoog het paneel en het grid mogen worden.
       Meten in plaats van een vast getal, want de header verandert van hoogte
       met de WP-adminbalk en de bovenbalk met de schermbreedte. */
    function meetHoogtes() {
        const wortel = document.documentElement.style;

        const header = document.querySelector('header.elementor-location-header > .e-con');
        if (header) {
            const h = Math.round(header.getBoundingClientRect().height);
            if (h > 0 && h < 400) wortel.setProperty('--site-header-h', h + 'px');
        }

        const balk = zoekBovenbalk();
        if (balk) {
            const h = Math.round(balk.getBoundingClientRect().height);
            if (h > 0 && h < 400) wortel.setProperty('--dim-balk-h', h + 'px');
        }
    }

    meetHoogtes();
    window.addEventListener('resize', meetHoogtes);

    function headerHoogte() {
        const waarde = parseInt(
            getComputedStyle(document.documentElement).getPropertyValue('--site-header-h'), 10
        );
        return Number.isFinite(waarde) ? waarde : 120;
    }

    function zetPaginaVast() {
        meetHoogtes();

        // Anker is de BOVENBALK, niet het grid: zoeken, sorteren en "Wis alle
        // filters" horen in beeld te blijven terwijl je een product bekijkt.
        // Ankerden we op het grid, dan schoof de balk achter de vaste header.
        const anker = zoekBovenbalk() || document.getElementById('dim-products-fsearch');
        if (anker) {
            const doel = window.scrollY + anker.getBoundingClientRect().top - headerHoogte();
            // instant, niet smooth: we zetten de pagina meteen daarna op slot en
            // een lopende smooth-scroll zou daar halverwege in blijven steken
            window.scrollTo(0, Math.max(0, doel));
        }
        document.documentElement.classList.add('dim-detail-vast');
    }

    function geefPaginaVrij() {
        document.documentElement.classList.remove('dim-detail-vast');
    }

    // Maak de detail-KOLOM (direct kind van .dim-products-layout) de sticky host,
    // zodat het paneel bij scrollen onder de header (148px) blijft hangen.
    function markStickyHost() {
        if (document.querySelector('.dim-sticky-host')) return;   // al gezet
        const shell = document.querySelector('.dim-product-panel-shell');
        if (!shell) return;
        let el = shell;
        while (el.parentElement && el.parentElement !== layout && el.parentElement !== document.body) {
            el = el.parentElement;
        }
        const host = (el.parentElement === layout) ? el : shell;
        host.classList.add('dim-sticky-host');
    }

    function setActiveCard(productId) {
        document.querySelectorAll('.dim-grid-item.is-active').forEach(el => {
            el.classList.remove('is-active');
        });

        if (!productId) return;

        const active = document.querySelector(`.dim-grid-item[data-product-id="${productId}"]`);
        if (active) {
            active.classList.add('is-active');
        }
    }

    async function fetchProductHtml(productId) {
        const url = new URL(ajaxUrl, window.location.origin);
        url.searchParams.set('action', 'dim_get_product_detail');
        url.searchParams.set('product_id', productId);
        // startsWith: vangt ook een gewijzigde slug als "just-gloves-2" op
        const product_slug = location.pathname.split('/')[1] || '';
        url.searchParams.set('template_id', product_slug.startsWith('just-gloves') ? 3626 : 1498);
        const response = await fetch(url.toString(), {
            method: 'GET',
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
        });
        const data = await response.json();
        return (data.success && data.data && data.data.html) ? data.data : null;
    }

    // Skeleton: bootst de detail-layout na terwijl de HTML laadt, zodat de
    // pagina niet "leeg valt" (vervangt de draaiende spinner).
    function skeletonHtml() {
        const rij = '<div class="dim-sk-rij"><span class="dim-sk dim-sk-label"></span><span class="dim-sk dim-sk-waarde"></span></div>';
        return '<div class="dim-skeleton" aria-hidden="true">' +
            '<div class="dim-sk dim-sk-topbar"></div>' +
            '<div class="dim-sk dim-sk-foto"></div>' +
            '<div class="dim-sk dim-sk-titel"></div>' +
            '<div class="dim-sk dim-sk-tekst"></div>' +
            '<div class="dim-sk-kolommen"><div>' + rij.repeat(7) + '</div><div>' + rij.repeat(7) + '</div></div>' +
            '</div>';
    }

    async function loadProduct(productId, pushUrl = true) {
        if (!productId || String(productId) === currentProductId) return;

        const id = String(productId);
        currentProductId = id;
        setOpenState(true);
        setActiveCard(id);

        if (productCache.get(id)) {
            panelContent.innerHTML = productCache.get(id).html;
            pushUrlChange(pushUrl, id);
            initBarCodes();
            renderVariants(id);
            return;
        }

        panelContent.innerHTML = skeletonHtml();

        try {
            const data = await fetchProductHtml(id);
            if (!data) {
                panelContent.innerHTML = '<div class="dim-product-error">Could not load product.</div>';
                return;
            }

            panelContent.innerHTML = data.html;
            productCache.set(id, data);

            pushUrlChange(pushUrl, id);

            initBarCodes();
            renderVariants(id);
        } catch (error) {
            panelContent.innerHTML = '<div class="dim-product-error">Something went wrong.</div>';
            console.error(error);
        }
    }

    /* Van buitenaf een product openen (zakkencalculator-popup): zelfde route
       als een kaart-klik, inclusief URL-hash en vastzetten van de pagina.
       dimProductPanel is het settings-object uit PHP (wp_localize_script);
       we hangen de functie daaraan zodat er maar één naamruimte is. */
    window.dimProductPanel = Object.assign(window.dimProductPanel || {}, {
        open: (id) => loadProduct(String(id), true),
    });

    function closeProductPanel(pushUrl = true) {
        currentProductId = null;
        setOpenState(false);
        setActiveCard(null);

        if (pushUrl) {
            const newUrl = new URL(window.location.href);
            newUrl.hash = '';
            history.pushState({}, '', newUrl);
        }
    }

    document.addEventListener('click', (event) => {
        const card = event.target.closest('.dim-grid-item');
        if (card) {
            event.preventDefault();

            const productId = card.dataset.dimProduct;
            if (productId) {
                loadProduct(productId, true);
            }
            return;
        }

        const closeBtn = event.target.closest('.dim-product-close');
        if (closeBtn) {
            event.preventDefault();
            closeProductPanel(true);
        }
    });

    /* --- Ankerlinks ín het detailpaneel --------------------------------------
       "Offerte aanvragen" is in Elementor een gewone ankerlink naar het
       formulier (#product-quation-form). Laat je de browser dat afhandelen, dan
       wordt de hash #product=5837 overschreven — en dáár leest het paneel uit
       welk product open staat. De deeplink is dan weg, en de popstate-listener
       hieronder sluit het paneel omdat hij geen product meer vindt.

       Daarom vangen we elke ankerlink binnen het paneel af waarvan het doel óók
       in het paneel staat. De URL blijft zoals hij is en we scrollen het paneel
       zelf: dat is de scroll-container, niet de pagina (die ligt op slot).
       De vaste kopbalk met Terug en de breadcrumb ligt over de bovenkant heen,
       dus die hoogte gaat eraf.

       Capture-fase op document: Elementor handelt klikken op zijn eigen
       elementen anders eerder af. Bewust generiek en niet alleen voor dit ene
       id — een tweede ankerlink in het template werkt dan vanzelf goed. */
    document.addEventListener('click', (e) => {
        const link = e.target.closest && e.target.closest('a[href*="#"]');
        if (!link || !panelContent.contains(link)) return;
        if (!link.hash || link.hash.length < 2) return;

        const doel = document.getElementById(decodeURIComponent(link.hash.slice(1)));
        if (!doel || !panelContent.contains(doel)) return;

        e.preventDefault();
        e.stopImmediatePropagation();

        const paneel = panelContent.closest('.dim-right-product-detail-panel');
        if (!paneel) return;

        const kop = paneel.querySelector('.dim-detail-topbar');
        const kopHoogte = kop ? kop.getBoundingClientRect().height : 0;
        const top = doel.getBoundingClientRect().top - paneel.getBoundingClientRect().top
                  + paneel.scrollTop - kopHoogte - 16;

        const rustig = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        paneel.scrollTo({ top: Math.max(0, top), behavior: rustig ? 'auto' : 'smooth' });
    }, true);

    window.addEventListener('popstate', () => {
        const url = new URL(window.location.href);
        const productId = url.searchParams.get(queryParam);

        if (productId) {
            loadProduct(productId, false);
        } else {
            closeProductPanel(false);
        }
    });

    // Initial load from URL
    const initialProductId = getIdFromUrl();

    if (initialProductId) {
        loadProduct(initialProductId, false);
    }

    /* --- 360-video op de kaart: draait zolang de muis erop staat -----------
       Een kaart krijgt alleen een <video> als het product er een heeft
       (hit.video_url — zie renderHit in algolia-product-search.js en het
       veld 360_video_view in algolia.php). Zichtbaar maken gebeurt puur met
       CSS (:hover op de kaart); hier alleen starten en stoppen.

       pointerover/pointerout in plaats van enter/leave: die bubbelen wél,
       dus één listener op document is genoeg voor kaarten die InstantSearch
       telkens opnieuw rendert. relatedTarget-check voorkomt dat bewegen
       BINNEN de kaart de video telkens opnieuw laat beginnen. */
    const CARD_SELECTOR = '.dim-ais-hit';
    const rustigAan = window.matchMedia('(prefers-reduced-motion: reduce)');

    document.addEventListener('pointerover', (e) => {
        if (rustigAan.matches) return;
        const card = e.target?.closest?.(CARD_SELECTOR);
        if (!card || (e.relatedTarget && card.contains(e.relatedTarget))) return;

        const video = card.querySelector('video.dim-kaart-video');
        if (!video) return;

        video.muted = true;        // zonder muted weigert de browser autoplay
        video.playsInline = true;
        video.play().catch(() => {});
    });

    document.addEventListener('pointerout', (e) => {
        const card = e.target?.closest?.(CARD_SELECTOR);
        if (!card || (e.relatedTarget && card.contains(e.relatedTarget))) return;

        const video = card.querySelector('video.dim-kaart-video');
        if (!video) return;

        video.pause();
        video.currentTime = 0;     // volgende hover begint gewoon vooraan
    });

    function pushUrlChange(pushUrl = true, productId) {
        if (pushUrl) {
            const newUrl = new URL(window.location.href);
            newUrl.hash = `${queryParam}=${productId}`;
            history.pushState({ productId }, '', newUrl);

            /* Vangnet tegen de InstantSearch-router: die berekent zijn nieuwe
               URL op het moment van een filterwijziging en schrijft hem pas
               ~400ms later. Zet de zakkencalculator filters en opent hij
               direct daarna een detail, dan overschrijft die uitgestelde
               momentopname onze verse #product-hash. We kijken daarom even
               later nog één keer: is de hash weg terwijl dit product nog
               open staat, dan zetten we hem terug (replaceState — geen
               extra stap in de browsergeschiedenis). */
            setTimeout(() => {
                if (String(currentProductId) === String(productId) && !window.location.hash) {
                    history.replaceState(history.state, '',
                        window.location.pathname + window.location.search + `#${queryParam}=${productId}`);
                }
            }, 800);
        }
    }

    function getIdFromUrl() {
        const hash = window.location.hash.replace(/^#/, '');
        const params = new URLSearchParams(hash);
        return params.get('product');
    }

    function initBarCodes() {
        const barcodesEl = document.querySelectorAll('.dim-ean-barcode');
        if (!barcodesEl.length) return;

        document.querySelectorAll('.dim-ean-barcode').forEach(el => {
            const ean = el.dataset.ean;
            if (!ean) return;

            const svg = el.querySelector('.ean-barcode-svg');
            JsBarcode(svg, ean, {
                format: "CODE128",
                lineColor: "#000",
                width: 2,
                height: 35,
                displayValue: true,
                fontSize: 12,
                textMargin: 4
            });
        });
    }

    /* ---------------------------------------------------------------------
     * Lichte variant-switch: bij een kleur/dikte/lengte-klik NIET het hele
     * paneel herladen, maar alleen de velden verversen die per artikel
     * verschillen (artikelcode, foto, EAN's, omschrijving, colli, ...).
     * Oud en nieuw detail komen uit hetzelfde Elementor-template, dus
     * widgets zijn 1-op-1 te matchen op data-id; we vervangen alleen wat
     * afwijkt. Chips blijven staan — alleen de active-state wisselt.
     * ------------------------------------------------------------------- */
    function morphPanel(nieuwHtml, baseTitle) {
        const tpl = document.createElement('div');
        tpl.innerHTML = nieuwHtml;
        const vers = new Map();
        // kaal [data-id]: atomic bladeren (e-image, e-paragraph, e-heading)
        // dragen GEEN class "elementor-element" — alleen containers doen dat
        tpl.querySelectorAll('[data-id]').forEach(w => vers.set(w.dataset.id, w));
        let vergeleken = 0;
        /* Alleen BLAD-elementen vergelijken (zonder eigen data-id-kinderen).
           Het 2026-template bestaat vrijwel volledig uit atomic elementen
           (e-flexbox / e-heading / ...), niet meer uit .elementor-widget.
           Een bovenliggende container vervangen zou ook ons eigen
           #dim-varianten-blok weggooien; de bladeren zijn precies de losse
           teksten, foto's en de specs-shortcode — dat is wat per artikel
           verschilt. */
        panelContent.querySelectorAll('[data-id]').forEach(w => {
            if (w.querySelector('[data-id]')) return;            // container, geen blad
            if (w.closest('#dim-varianten')) return;             // fallback-blok (zonder specs-tabel) is van ons
            // N.B. de specs-tabel wordt hier bewust WEL ververst, inclusief onze
            // chip-rijen: switchVariant bouwt de kiezers daarna opnieuw op
            // (plaatsKiezers), met de waarden van het nieuwe artikel.
            const tekst = (w.textContent || '').trim();
            if (baseTitle && tekst === baseTitle) return;        // basistitel niet terugzetten naar volledige naam
            const nieuw = vers.get(w.dataset.id);
            if (!nieuw) return;
            vergeleken++;
            if (nieuw.innerHTML !== w.innerHTML) w.innerHTML = nieuw.innerHTML;
            /* Ook de ATTRIBUTEN gelijktrekken. De productfoto is in het
               2026-template een atomic <img> die zelf het data-id draagt:
               geen innerHTML, het verschil zit in src/srcset. Zelfde geldt
               voor een eventuele <video src> of een background-image in een
               style-attribuut. */
            [...w.attributes].forEach(a => { if (!nieuw.hasAttribute(a.name)) w.removeAttribute(a.name); });
            [...nieuw.attributes].forEach(a => { if (w.getAttribute(a.name) !== a.value) w.setAttribute(a.name, a.value); });
        });
        return vergeleken;
    }

    async function switchVariant(target, ctx) {
        const id = String(target.post_id);
        panelContent.classList.add('dim-variant-loading');
        try {
            let data = productCache.get(id);
            if (!data) {
                data = await fetchProductHtml(id);
                if (!data) throw new Error('detail-html laden mislukt');
                productCache.set(id, data);
            }
            if (!morphPanel(data.html, ctx.baseTitle)) throw new Error('geen vergelijkbare widgets gevonden');

            // selectie-state bijwerken (zelfde object als in de klik-closures,
            // zodat findVariant blijft scoren t.o.v. de actuele keuze)
            Object.assign(ctx.cur, {
                color: target.kleuren || '',
                dikte: target.dikte || '',
                formaat: target.formaat || '',
                vorm: norm(target.vorm),
                verpakking: norm(target.verpakt_per),
            });
            // kiezers opnieuw opbouwen: de morph heeft de specs-tabel net
            // ververst met de kale waarden van het nieuwe artikel, dus onze
            // chip-rijen zijn daarbij weggeveegd — zo blijven ze altijd in
            // lijn met wat er in de tabel staat
            plaatsKiezers();
            plaatsDetailVideo(target.video_url || '');

            // verborgen artikel-meta (voor de offerte-flow) mee laten wisselen
            const meta = panelContent.querySelector('.dim-artikel-meta');
            if (meta) {
                meta.dataset.artikelcode = target.artikelcode || '';
                meta.dataset.postId = id;
                const inpCode = meta.querySelector('input[name="dim_artikelcode"]');
                if (inpCode) inpCode.value = target.artikelcode || '';
                const inpId = meta.querySelector('input[name="dim_post_id"]');
                if (inpId) inpId.value = id;
            }

            currentProductId = id;
            setActiveCard(id);
            pushUrlChange(true, id);
            initBarCodes();
        } catch (err) {
            // vangnet: als de lichte route niet kan, alsnog volledig laden
            console.error('variant-switch valt terug op volledige reload:', err);
            currentProductId = null;
            loadProduct(id, true);
        } finally {
            panelContent.classList.remove('dim-variant-loading');
        }
    }

    /* ---------------------------------------------------------------------
     * Varianten: toon in de detailview de beschikbare kleuren + diktes van
     * hetzelfde basisproduct (variant_group). Elke keuze verwijst naar het
     * juiste artikel (post_id) — de artikelcode blijft dus altijd correct.
     * ------------------------------------------------------------------- */
    const variantCache = new Map(); // variant_group -> [siblings]

    async function algoliaQuery(params) {
        const AG = window.DIM_PRODUCT_SEARCH || null;   // lui uitlezen: pas gedefinieerd na de search-shortcode
        if (!AG || !AG.appId || !AG.searchKey) return null;
        const url = `https://${AG.appId}-dsn.algolia.net/1/indexes/${AG.indexNameAlgoliaSearch}/query`;
        const res = await fetch(url, {
            method: 'POST',
            headers: { 'X-Algolia-Application-Id': AG.appId, 'X-Algolia-API-Key': AG.searchKey, 'Content-Type': 'application/json' },
            body: JSON.stringify(params),
        });
        return res.ok ? res.json() : null;
    }

    // NL kleurnaam -> swatch-kleur (zelfde map als in de grid-kaarten)
    const KLEUR_HEX = {
        wit: '#ffffff', zwart: '#111827', blauw: '#2563eb', grijs: '#9ca3af', oranje: '#f97316',
        groen: '#16a34a', geel: '#facc15', rood: '#dc2626', bruin: '#92400e', paars: '#7c3aed',
        roze: '#ec4899', naturel: '#e7dcc8', zilver: '#c0c0c0', goud: '#d4af37',
    };

    function swatchKnop(kleur, actief) {
        const token = String(kleur).split(/[\/,]/)[0].trim().toLowerCase();
        const isTrans = token === 'transparant';
        const bg = isTrans ? '' : ` style="background:${KLEUR_HEX[token] || '#d1d5db'}"`;
        return `<button type="button" class="dim-swatch dim-swatch--groot${isTrans ? ' is-transparant' : ''}${actief ? ' is-active' : ''}"` +
            ` title="${kleur}" data-kind="color" data-value="${encodeURIComponent(kleur)}"${bg}></button>`;
    }

    // formaat splitsen in breedte + lengte ("58/2x22 x 63cm" -> ["58/2x22", "63cm"])
    const splitsFormaat = (f) => {
        const delen = String(f || '').split(' x ');
        if (delen.length < 2) return null;
        const lengte = delen[delen.length - 1].trim();
        if (!/^\d+(?:[.,]\d+)?\s*(cm|mm)$/.test(lengte)) return null;
        return { breedte: delen.slice(0, -1).join(' x ').trim(), lengte };
    };

    /* --- De kiezers: rijen ín de specs-tabel --------------------------------
       De variantkeuzes (kleur, dikte, lengte, vorm, verpakt per) staan in
       dezelfde tabelrijen als de andere specs — de kale tekstwaarde in de
       <td> wordt vervangen door swatches of chips. plaatsKiezers() wordt op
       twee momenten aangeroepen: bij het openen van een detail én na elke
       variantwissel (de morph ververst de tabel en veegt de chips mee weg).
       kiezerCtx onthoudt daarvoor alles wat nodig is. */
    let kiezerCtx = null;

    function plaatsKiezers() {
        if (!kiezerCtx) return;
        const { colors, thicknesses, formaten, vormen, verpakkingen, cur, klik } = kiezerCtx;

        const chipsMaken = (waarden, huidig, kind, cls) => {
            const el = document.createElement('div');
            el.className = cls;
            el.innerHTML = waarden.map(w =>
                `<button type="button" class="dim-variant-chip${w === huidig ? ' is-active' : ''}"` +
                ` data-kind="${kind}" data-value="${encodeURIComponent(w)}">${w}</button>`).join('');
            el.addEventListener('click', klik);
            return el;
        };
        const swatchesMaken = () => {
            const sw = document.createElement('div');
            sw.className = 'dim-detail-swatches';
            sw.innerHTML = colors.map(c => swatchKnop(c, c === cur.color)).join('');
            sw.addEventListener('click', klik);
            return sw;
        };

        const tabel = panelContent.querySelector('.dim-specs__tabel');
        if (tabel) {
            // eerder door ons toegevoegde rijen eerst weg (idempotent herbouwen)
            tabel.querySelectorAll('.dim-spec-rij--dim').forEach(r => r.remove());

            const rij = (veld) => tabel.querySelector(`tr[data-veld="${veld}"]`);
            // ontbreekt een rij (veld was leeg bij dit artikel, maar broertjes
            // bieden wél een keuze), dan maken we hem in dezelfde tabelvorm bij
            const maakRij = (veld, label, naRij) => {
                const tr = document.createElement('tr');
                tr.className = 'dim-spec-rij dim-spec-rij--dim';
                tr.dataset.veld = veld;
                const th = document.createElement('th');
                th.scope = 'row';
                th.textContent = label;
                tr.append(th, document.createElement('td'));
                if (naRij) naRij.insertAdjacentElement('afterend', tr);
                else (tabel.tBodies[0] || tabel).appendChild(tr);
                return tr;
            };
            const vulTd = (tr, el) => {
                tr.classList.add('dim-spec-rij--kiezer');
                tr.querySelector('td').replaceChildren(el);
            };

            if (colors.length) {
                const r = rij('kleuren') || maakRij('kleuren', 'Kleuren', rij('omschrijving') || rij('artikelcode'));
                vulTd(r, swatchesMaken());
            }
            if (thicknesses.length > 1) {
                const r = rij('dikte') || maakRij('dikte', 'Dikte', rij('kleuren'));
                vulTd(r, chipsMaken(thicknesses, cur.dikte, 'dikte', 'dim-detail-diktes'));
            }
            const gesplitst = splitsFormaat(cur.formaat);
            if (gesplitst) {
                // breedte is per variantgroep vast en staat al als tekst in de
                // tabel; alleen de lengte is een echte keuze
                const lengtes = formaten.map(f => ({ f, s: splitsFormaat(f) })).filter(x => x.s);
                if (lengtes.length > 1) {
                    const el = document.createElement('div');
                    el.className = 'dim-detail-diktes dim-detail-lengtes';
                    el.innerHTML = lengtes.map(x =>
                        `<button type="button" class="dim-variant-chip${x.f === cur.formaat ? ' is-active' : ''}"` +
                        ` data-kind="formaat" data-value="${encodeURIComponent(x.f)}">${x.s.lengte}</button>`).join('');
                    el.addEventListener('click', klik);
                    const r = rij('lengte_cm') || maakRij('lengte_cm', 'Lengte (cm)', rij('breedte_cm') || rij('formaat'));
                    vulTd(r, el);
                }
            } else if (formaten.length > 1) {
                const r = rij('formaat') || maakRij('formaat', 'Formaat', rij('dikte') || rij('kleuren'));
                vulTd(r, chipsMaken(formaten, cur.formaat, 'formaat', 'dim-detail-diktes dim-detail-formaten'));
            }
            if (vormen.length > 1) {
                const r = rij('vorm') || maakRij('vorm', 'Vorm (rol/los)', rij('lengte_cm') || rij('formaat') || rij('dikte'));
                vulTd(r, chipsMaken(vormen, cur.vorm, 'vorm', 'dim-detail-diktes dim-detail-vormen'));
            }
            if (verpakkingen.length > 1) {
                const r = rij('verpakt_per') || maakRij('verpakt_per', 'Verpakt per', rij('vorm') || rij('lengte_cm'));
                vulTd(r, chipsMaken(verpakkingen, cur.verpakking, 'verpakking', 'dim-detail-diktes dim-detail-verpakkingen'));
            }
            return;
        }

        /* Geen specs-tabel in dit template (bv. handschoenen): gestapeld blok,
           vlak voor het offerteformulier. */
        let houder = panelContent.querySelector('#dim-varianten');
        if (!houder) {
            houder = document.createElement('div');
            houder.id = 'dim-varianten';
            houder.className = 'dim-varianten-schakelaar';
            const anker = panelContent.querySelector('#product-quation-form');
            if (anker) anker.insertAdjacentElement('beforebegin', houder);
            else panelContent.appendChild(houder);
        }
        houder.hidden = false;
        houder.textContent = '';
        const rijMaken = (labelTekst, inhoud) => {
            const r = document.createElement('div');
            r.className = 'dim-variant-rij';
            const l = document.createElement('span');
            l.className = 'dim-variant-label';
            l.textContent = labelTekst;
            r.append(l, inhoud);
            houder.appendChild(r);
        };
        if (colors.length) rijMaken('Kleur', swatchesMaken());
        if (thicknesses.length > 1) rijMaken('Dikte', chipsMaken(thicknesses, cur.dikte, 'dikte', 'dim-detail-diktes'));
        if (formaten.length > 1) rijMaken('Formaat', chipsMaken(formaten, cur.formaat, 'formaat', 'dim-detail-diktes dim-detail-formaten'));
        if (vormen.length > 1) rijMaken('Verpakt', chipsMaken(vormen, cur.vorm, 'vorm', 'dim-detail-diktes dim-detail-vormen'));
        if (verpakkingen.length > 1) rijMaken('Verpakt per', chipsMaken(verpakkingen, cur.verpakking, 'verpakking', 'dim-detail-diktes dim-detail-verpakkingen'));
        if (!houder.children.length) houder.hidden = true;
    }

    /* --- 360-video in het detail: speelt één keer over de foto heen ---------
       Daarna fade hij weg en blijft de gewone productfoto staan — geen loop,
       geen herstart bij hover. Bij een variantwissel komt er (als die variant
       een video heeft) een verse, die ook weer precies één keer draait. */
    function plaatsDetailVideo(url) {
        panelContent.querySelectorAll('.dim-detail-video').forEach(v => v.remove());
        if (!url || rustigAan.matches) return;
        const foto = panelContent.querySelector('img.e-image-base, img[src*="/renders/"], img[data-id]');
        if (!foto || !foto.parentElement) return;
        const ouder = foto.parentElement;
        ouder.classList.add('dim-detail-media');
        const video = document.createElement('video');
        video.className = 'dim-detail-video';
        video.src = url;
        video.muted = true;
        video.playsInline = true;
        video.preload = 'auto';
        video.addEventListener('ended', () => {
            video.classList.add('is-klaar');                 // fade (CSS)
            setTimeout(() => video.remove(), 500);           // daarna echt weg
        });
        ouder.appendChild(video);
        video.play().catch(() => video.remove());
    }

    async function renderVariants(productId) {
        // bestaande injecties opruimen
        kiezerCtx = null;
        panelContent.querySelectorAll('.dim-variant-selector, .dim-artikel-meta, .dim-detail-swatches, .dim-detail-diktes, .dim-injected-row, .dim-spec-rij--dim, .dim-detail-video').forEach(el => el.remove());

        // topbar (pijltje) blijft in beeld terwijl je door het detail scrolt
        const tb = panelContent.querySelector('.dim-product-close');
        /* De sticky kopbalk moet de RIJ zijn waar Terug én de breadcrumb in staan,
           niet de Terug-knop zelf. Die knop is in Elementor ook een .e-con, dus
           closest('.e-con') gaf hem terug — en een sticky element kan alleen
           binnen zijn eigen ouder blijven plakken. Die ouder was 165px hoog, dus
           na 115px scrollen schoof de balk alsnog uit beeld. Vanaf de ouder
           zoeken lost dat op: die rij zit in het volledige documentblok en heeft
           dus de hele paneelhoogte om in te blijven staan. */
        if (tb) {
            const rij = (tb.parentElement && tb.parentElement.closest('.e-con')) || tb.parentElement || tb;
            rij.classList.add('dim-detail-topbar');
        }
        // Echte headerhoogte meten: de <header>-wrapper is 0px hoog, de fixed
        // .e-con erin is de zichtbare balk. Zo pint de topbar exact onder de
        // header (incl. WP-adminbalk als je ingelogd bent).
        const vasteHeader = document.querySelector('header.elementor-location-header > .e-con');
        if (vasteHeader) {
            const onderkant = Math.round(vasteHeader.getBoundingClientRect().bottom);
            if (onderkant > 0 && onderkant < 400) {
                document.documentElement.style.setProperty('--site-header-h', onderkant + 'px');
            }
        }
        // sticky host zeker stellen (rAF-route via setOpenState blijkt niet altijd te lopen)
        markStickyHost();

        if (!window.DIM_PRODUCT_SEARCH) return;

        // 1) haal het record van dit product op (basisvelden + eigen kleur/dikte)
        // distinct:false is nodig — de index heeft attributeForDistinct=variant_group voor het
        // grid, maar hier willen we juist ALLE records (alle kleur/dikte-varianten) zien.
        const self = await algoliaQuery({ query: '', hitsPerPage: 1, distinct: false, numericFilters: [`post_id=${productId}`],
            attributesToRetrieve: ['post_id', 'post_title', 'artikelcode', 'variant_group', 'variant_base_title', 'kwaliteit', 'type', 'vorm', 'formaat', 'merk_naam', 'kleuren', 'dikte', 'verpakt_per', 'video_url'] });
        const me = self && self.hits && self.hits[0];
        if (!me) return;

        // ALTIJD (ook zonder varianten): de geselecteerde artikelcode verborgen in het paneel,
        // zodat een offerte-aanvraag straks het juiste artikel meestuurt.
        const metaEl = document.createElement('div');
        metaEl.className = 'dim-artikel-meta';
        metaEl.style.display = 'none';
        metaEl.dataset.artikelcode = me.artikelcode || '';
        metaEl.dataset.postId = String(me.post_id || productId);
        metaEl.innerHTML = `<input type="hidden" name="dim_artikelcode" value="${me.artikelcode || ''}">` +
            `<input type="hidden" name="dim_post_id" value="${me.post_id || productId}">`;
        panelContent.appendChild(metaEl);

        // 360-video over de foto — ook voor producten zónder varianten
        plaatsDetailVideo(me.video_url || '');

        // N.B. geen harde eis meer op kwaliteit/type/formaat: handschoenen hebben
        // bv. geen 'type' maar wél varianten (maten). variant_group is de bron.
        const group = me.variant_group || '';
        if (!group) return;

        // 2) broertjes/zusjes: query op gefacetteerde velden (materiaal + type),
        //    daarna client-side exact op variant_group (bron van waarheid uit de admin-actie).
        //    Velden die leeg zijn (handschoenen hebben geen 'type') slaan we over;
        //    is er níets te filteren, dan direct op variant_group (filterOnly-facet).
        let siblings = variantCache.get(group);
        if (!siblings) {
            const ff = [];
            if (me.kwaliteit) ff.push([`kwaliteit:${me.kwaliteit}`]);
            if (me.type) ff.push([`type:${me.type}`]);
            if (!ff.length) ff.push([`variant_group:${group}`]);
            const sib = await algoliaQuery({ query: '', hitsPerPage: 500, distinct: false, facetFilters: ff,
                attributesToRetrieve: ['post_id', 'variant_group', 'vorm', 'formaat', 'kleuren', 'dikte', 'artikelcode', 'verpakt_per', 'video_url'] });
            siblings = ((sib && sib.hits) || []).filter(s => s.variant_group === group);
            variantCache.set(group, siblings);
        }

        const laatsteGetal = s => { const m = String(s).match(/(\d+(?:[.,]\d+)?)\s*(?:cm|mm)?\s*$/); return m ? parseFloat(m[1].replace(',', '.')) : 0; };
        const eersteGetal = s => { const m = String(s).match(/[\d.,]+/); return m ? parseFloat(m[0].replace(',', '.')) : 0; };
        // Handschoen-maten (S/M/L/XL) hebben geen getal — sorteer die op maat-volgorde.
        const MAAT_INDEX = { 'xs': 1, 'extra small': 1, 's': 2, 'small': 2, 'm': 3, 'medium': 3,
            'l': 4, 'large': 4, 'xl': 5, 'extra large': 5, 'xxl': 6, '2xl': 6, 'xxxl': 7, '3xl': 7 };
        const maatIdx = s => MAAT_INDEX[String(s).trim().toLowerCase()] ?? null;
        const colors = [...new Set(siblings.map(s => s.kleuren).filter(Boolean))];
        const thicknesses = [...new Set(siblings.map(s => s.dikte).filter(Boolean))].sort((a, b) => eersteGetal(a) - eersteGetal(b));
        const formaten = [...new Set(siblings.map(s => s.formaat).filter(Boolean))].sort((a, b) => {
            const ma = maatIdx(a), mb = maatIdx(b);
            if (ma !== null && mb !== null) return ma - mb;
            return laatsteGetal(a) - laatsteGetal(b);
        });
        const vormen = [...new Set(siblings.map(s => norm(s.vorm)).filter(Boolean))].sort();
        const verpakkingen = [...new Set(siblings.map(s => norm(s.verpakt_per)).filter(Boolean))].sort((a, b) => eersteGetal(a) - eersteGetal(b));

        // Huidige selectie als mutable object: switchVariant werkt dit bij,
        // zodat de klik-closures (findVariant-score) actueel blijven.
        const cur = {
            color: me.kleuren || '',
            dikte: me.dikte || '',
            formaat: me.formaat || '',
            vorm: norm(me.vorm),
            verpakking: norm(me.verpakt_per),
        };

        // vind het juiste artikel bij een keuze. De varianten vormen geen volledige matrix,
        // dus: kies binnen de gevraagde dimensie het artikel dat op de OVERIGE dimensies
        // het meest lijkt op het huidige artikel (score-match).
        const findVariant = (kind, value) => {
            const eis = {
                color:      s => s.kleuren === value,
                dikte:      s => s.dikte === value,
                formaat:    s => s.formaat === value,
                vorm:       s => norm(s.vorm) === value,
                verpakking: s => norm(s.verpakt_per) === value,
            }[kind];
            const kandidaten = siblings.filter(eis);
            if (!kandidaten.length) return null;
            const score = s =>
                (s.kleuren === cur.color ? 1 : 0) + (s.dikte === cur.dikte ? 1 : 0) +
                (s.formaat === cur.formaat ? 1 : 0) + (norm(s.vorm) === cur.vorm ? 1 : 0) +
                (norm(s.verpakt_per) === cur.verpakking ? 1 : 0);
            return kandidaten.sort((a, b) => score(b) - score(a))[0];
        };

        const baseTitle = (me.variant_base_title || '').trim();

        // TITEL blijft de basistitel (zonder kleur/dikte) — vervang de heading die de
        // volledige productnaam toont, zodat die niet wisselt bij een variant-keuze.
        let headingReplaced = false;
        if (baseTitle && me.post_title) {
            const full = me.post_title.trim();
            panelContent.querySelectorAll('h1, h2, h3, h4').forEach(h => {
                if (h.closest('.dim-variant-selector')) return;
                if (h.textContent.trim() === full) { h.textContent = baseTitle; headingReplaced = true; }
            });
        }

        const klik = (e) => {
            const btn = e.target.closest('[data-kind]');
            if (!btn) return;
            e.preventDefault();
            const target = findVariant(btn.dataset.kind, decodeURIComponent(btn.dataset.value));
            if (target && target.post_id && String(target.post_id) !== String(currentProductId)) {
                switchVariant(target, { cur, baseTitle });   // lichte route, geen volledige reload
            }
        };

        // Alles staat klaar: context bewaren en de kiezers in de specs-tabel
        // zetten. switchVariant herbouwt ze met dezelfde context (met een
        // bijgewerkte cur) na elke wissel.
        kiezerCtx = { colors, thicknesses, formaten, vormen, verpakkingen, cur, klik };
        plaatsKiezers();
    }
})();