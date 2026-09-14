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
        if (isOpen) requestAnimationFrame(markStickyHost);
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
        const product_slug = location.pathname.split('/')[1];
        url.searchParams.set('template_id', product_slug === 'just-gloves' ? 3626 : 1498);
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

    const CARD_SELECTOR = '.product-media';

    document.addEventListener('pointerenter', (e) => {
        // Auto play video on hover
        const card = e.target?.closest?.(CARD_SELECTOR);
        if (!card) return;

        const video = card.querySelector('video');
        if (!video) return;

        // Make sure it can autoplay on hover
        video.muted = true;
        video.playsInline = true;

        // Try play (may fail on some browsers if not allowed)
        video.play().catch(() => {});
    }, true);

    document.addEventListener('pointerleave', (e) => {
        const card = e.target?.closest?.(CARD_SELECTOR);
        if (!card) return;

        const video = card.querySelector('video');
        if (!video) return;

        video.pause();
        // Optional: reset so it always starts from beginning
        video.currentTime = 0;
    }, true);

    function pushUrlChange(pushUrl = true, productId) {
        if (pushUrl) {
            const newUrl = new URL(window.location.href);
            newUrl.hash = `${queryParam}=${productId}`;
            history.pushState({ productId }, '', newUrl);
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
    const DIM_CHIP_SELECTOR = '.dim-detail-swatches, .dim-detail-diktes, .dim-variant-selector';

    function morphPanel(nieuwHtml, baseTitle) {
        const tpl = document.createElement('div');
        tpl.innerHTML = nieuwHtml;
        const vers = new Map();
        tpl.querySelectorAll('.elementor-widget[data-id]').forEach(w => vers.set(w.dataset.id, w));
        let vergeleken = 0;
        panelContent.querySelectorAll('.elementor-widget[data-id]').forEach(w => {
            if (w.querySelector(DIM_CHIP_SELECTOR)) return;      // door ons beheerde chips
            const h = w.querySelector('.elementor-heading-title');
            const tekst = h ? h.textContent.trim() : '';
            if (baseTitle && tekst === baseTitle) return;        // basistitel niet terugzetten naar volledige naam
            if (tekst === 'Breedte:') return;                    // hernoemd "Formaat:"-label behouden
            const nieuw = vers.get(w.dataset.id);
            if (!nieuw) return;
            vergeleken++;
            if (nieuw.innerHTML !== w.innerHTML) w.innerHTML = nieuw.innerHTML;
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
            const doelen = {
                color: ctx.cur.color, dikte: ctx.cur.dikte, formaat: ctx.cur.formaat,
                vorm: ctx.cur.vorm, verpakking: ctx.cur.verpakking,
            };
            panelContent.querySelectorAll('[data-kind][data-value]').forEach(b => {
                b.classList.toggle('is-active', decodeURIComponent(b.dataset.value) === doelen[b.dataset.kind]);
            });

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

    async function renderVariants(productId) {
        // bestaande injecties opruimen
        panelContent.querySelectorAll('.dim-variant-selector, .dim-artikel-meta, .dim-detail-swatches, .dim-detail-diktes, .dim-injected-row').forEach(el => el.remove());

        // topbar (pijltje) blijft in beeld terwijl je door het detail scrolt
        const tb = panelContent.querySelector('.dim-product-close');
        if (tb) (tb.closest('.e-con') || tb.parentElement || tb).classList.add('dim-detail-topbar');
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
            attributesToRetrieve: ['post_id', 'post_title', 'artikelcode', 'variant_group', 'variant_base_title', 'kwaliteit', 'type', 'vorm', 'formaat', 'merk_naam', 'kleuren', 'dikte', 'verpakt_per'] });
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
                attributesToRetrieve: ['post_id', 'variant_group', 'vorm', 'formaat', 'kleuren', 'dikte', 'artikelcode', 'verpakt_per'] });
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

        // formaat splitsen in breedte + lengte ("58/2x22 x 63cm" -> ["58/2x22", "63cm"])
        const splitsFormaat = (f) => {
            const delen = String(f || '').split(' x ');
            if (delen.length < 2) return null;
            const lengte = delen[delen.length - 1].trim();
            if (!/^\d+(?:[.,]\d+)?\s*(cm|mm)$/.test(lengte)) return null;
            return { breedte: delen.slice(0, -1).join(' x ').trim(), lengte };
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

        // Hulp: vind de spec-rij (Elementor flex-container met space-between) bij een label.
        // Retourneert de rij + het waarde-deel (de widget náást het label), indien aanwezig.
        const vindRij = (labelRegex) => {
            const label = [...panelContent.querySelectorAll('.elementor-heading-title')]
                .find(el => labelRegex.test(el.textContent.trim()));
            if (!label) return null;
            const rij = label.closest('.e-con') || label.parentElement;
            const labelWidget = [...rij.children].find(ch => ch.contains(label)) || label;
            const valueWidget = [...rij.children].find(ch => ch !== labelWidget);
            return { rij, labelWidget, valueWidget };
        };

        // KLEUR: klikbare swatches rechts op de "Kleuren:"-regel (zoals de andere waarden).
        // Ook bij één kleur tonen we de swatch (de tekst-waarde is uit de template gehaald).
        if (colors.length) {
            const sw = document.createElement('div');
            sw.className = 'dim-detail-swatches';
            sw.innerHTML = colors.map(c => swatchKnop(c, c === cur.color)).join('');
            sw.addEventListener('click', klik);
            const spot = vindRij(/^Kleuren:?$/);
            if (spot) {
                if (spot.valueWidget) spot.valueWidget.replaceChildren(sw);
                else spot.rij.appendChild(sw);   // space-between duwt de swatches naar rechts
            }
        }

        // DIKTE: chips rechts op de "Dikte:"-regel — klikbaar, net als de kleuren.
        const chipsRij = (waarden, huidig, kind, cls) => {
            const el = document.createElement('div');
            el.className = cls;
            el.innerHTML = waarden.map(w =>
                `<button type="button" class="dim-variant-chip${w === huidig ? ' is-active' : ''}"` +
                ` data-kind="${kind}" data-value="${encodeURIComponent(w)}">${w}</button>`).join('');
            el.addEventListener('click', klik);
            return el;
        };
        if (thicknesses.length > 1) {
            const dk = chipsRij(thicknesses, cur.dikte, 'dikte', 'dim-detail-diktes');
            const spot = vindRij(/^Dikte:?$/);
            if (spot) {
                if (spot.valueWidget) spot.valueWidget.replaceChildren(dk);
                else spot.rij.appendChild(dk);
            }
        }

        // FORMAAT-regel wordt BREEDTE + LENGTE: label hernoemen naar "Breedte:" met één
        // (vaste) chip, en een eigen "Lengte:"-rij met klikbare lengte-knoppen eronder.
        const gesplitst = splitsFormaat(cur.formaat);
        const formaatSpot = vindRij(/^Formaat:?$/);
        let lengteRij = null;
        if (gesplitst && formaatSpot) {
            const eenheid = (gesplitst.lengte.match(/cm|mm/) || [''])[0];
            // label "Formaat:" -> "Breedte:"
            const labelEl = [...formaatSpot.labelWidget.querySelectorAll('.elementor-heading-title'), formaatSpot.labelWidget]
                .find(el => /^Formaat:?$/.test(el.textContent.trim()));
            if (labelEl) labelEl.textContent = 'Breedte:';
            // breedte: één vaste chip (breedte is per variant-groep altijd gelijk)
            const br = document.createElement('div');
            br.className = 'dim-detail-diktes dim-detail-breedte';
            br.innerHTML = `<button type="button" class="dim-variant-chip is-active is-static">${gesplitst.breedte} ${eenheid}</button>`;
            if (formaatSpot.valueWidget) formaatSpot.valueWidget.replaceChildren(br);
            else formaatSpot.rij.appendChild(br);
            // lengte: klikbare knoppen (chips tonen "110cm", klik kiest het volledige formaat)
            const lengtes = formaten.map(f => ({ f, s: splitsFormaat(f) })).filter(x => x.s);
            const lg = document.createElement('div');
            lg.className = 'dim-detail-diktes dim-detail-lengtes';
            lg.innerHTML = lengtes.map(x =>
                `<button type="button" class="dim-variant-chip${x.f === cur.formaat ? ' is-active' : ''}${lengtes.length > 1 ? '' : ' is-static'}"` +
                ` data-kind="formaat" data-value="${encodeURIComponent(x.f)}">${x.s.lengte}</button>`).join('');
            lg.addEventListener('click', klik);
            lengteRij = document.createElement('div');
            lengteRij.className = 'dim-injected-row';
            const ll = document.createElement('span');
            ll.className = 'dim-injected-label';
            ll.textContent = 'Lengte:';
            lengteRij.append(ll, lg);
            formaatSpot.rij.insertAdjacentElement('afterend', lengteRij);
        } else if (formaten.length > 1 && formaatSpot) {
            // niet-splitsbaar formaat (bv. 3 maten): dan gewone formaat-chips op de regel
            const fm = chipsRij(formaten, cur.formaat, 'formaat', 'dim-detail-diktes dim-detail-formaten');
            if (formaatSpot.valueWidget) formaatSpot.valueWidget.replaceChildren(fm);
            else formaatSpot.rij.appendChild(fm);
        }

        // VERPAKT: eigen rij (rol / los / op koker ...) onder de Lengte-rij.
        if (vormen.length > 1) {
            const vp = chipsRij(vormen, cur.vorm, 'vorm', 'dim-detail-diktes dim-detail-vormen');
            const rij = document.createElement('div');
            rij.className = 'dim-injected-row';
            const label = document.createElement('span');
            label.className = 'dim-injected-label';
            label.textContent = 'Verpakt:';
            rij.append(label, vp);
            const anker = lengteRij || (formaatSpot && formaatSpot.rij) || (vindRij(/^Dikte:?$/) || {}).rij;
            if (anker) anker.insertAdjacentElement('afterend', rij);
            else panelContent.appendChild(rij);
        }

        // VERPAKKING (eenheid): chips op de bestaande "Verpakt per:"-regel
        // (bv. "10 rol à 20 stuks" vs "25 rol à 20 stuks").
        if (verpakkingen.length > 1) {
            const ve = chipsRij(verpakkingen, cur.verpakking, 'verpakking', 'dim-detail-diktes dim-detail-verpakkingen');
            const spot = vindRij(/^Verpakt per:?$/);
            if (spot) {
                if (spot.valueWidget) spot.valueWidget.replaceChildren(ve);
                else spot.rij.appendChild(ve);
            }
        }
    }
})();