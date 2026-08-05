(function () {
    if (!window.DIM_PRODUCT_SEARCH) return;

    const { appId, searchKey, indexNameAlgoliaSearch, hitsPerPage, filters, isTaxonomyPage, placeHolder } = window.DIM_PRODUCT_SEARCH;
    const skeletonEl = document.getElementById('dim-product-search-ais-skeleton');
    if (!appId || !searchKey) {
        console.warn('DIM: Algolia keys ontbreken');
        skeletonEl?.remove(); // geen eeuwig shimmerend skelet als de zoeker niet kan starten
        return;
    }

    const { searchBox, refinementList, rangeInput, toggleRefinement, configure, clearRefinements, stats, sortBy, hits, pagination, menuSelect } = instantsearch.widgets;
    const searchClient = algoliasearch(appId, searchKey);

    const search = instantsearch({
        indexName: indexNameAlgoliaSearch,
        searchClient,
        routing: true,
    });

    // NL kleurnaam -> swatch-kleur (transparant krijgt een blokjespatroon via CSS)
    const KLEUR_HEX = {
        wit: '#ffffff', zwart: '#111827', blauw: '#2563eb', grijs: '#9ca3af', oranje: '#f97316',
        groen: '#16a34a', geel: '#facc15', rood: '#dc2626', bruin: '#92400e', paars: '#7c3aed',
        roze: '#ec4899', naturel: '#e7dcc8', zilver: '#c0c0c0', goud: '#d4af37',
    };

    function kleurSwatches(html, kleuren) {
        // kleuren = array met kleur-strings; samengestelde waarden ("geel/groen") splitsen
        const tokens = [...new Set(kleuren.flatMap(k => String(k).split(/[\/,]/)).map(s => s.trim().toLowerCase()).filter(Boolean))];
        if (!tokens.length) return '';
        return html`<div class="dim-card-row"><span class="dim-card-label">Kleur:</span>
            <span class="dim-card-swatches">${tokens.map(t => t === 'transparant'
                ? html`<span class="dim-swatch is-transparant" title="transparant"></span>`
                : html`<span class="dim-swatch" title="${t}" style="background:${KLEUR_HEX[t] || '#d1d5db'}"></span>`)}</span></div>`;
    }

    // Hits template — één kaart per variant-groep (distinct); kaart werkt met het bestaande
    // detailpaneel (.dim-grid-item + data-dim-product => products-overview.js opent het paneel)
    function renderHit(hit, { html }) {
        const isVariant = (hit.variant_count || 1) > 1;
        const title = (isVariant && hit.variant_base_title) ? hit.variant_base_title
            : (hit.post_title || hit.omschrijving || '(zonder titel)');
        const artikelcode = hit.artikelcode || '';
        const kleuren = Array.isArray(hit.variant_kleuren) && hit.variant_kleuren.length
            ? hit.variant_kleuren : (hit.kleuren ? [hit.kleuren] : []);
        const diktes = Array.isArray(hit.variant_diktes) && hit.variant_diktes.length
            ? hit.variant_diktes : (hit.dikte ? [hit.dikte] : []);
        const formaten = Array.isArray(hit.variant_formaten) ? hit.variant_formaten : [];
        const vormen = Array.isArray(hit.variant_vormen) ? hit.variant_vormen : [];
        const verpakt = !isVariant ? (hit.verpakt_per || '') : '';
        const img = hit?.medium_url || placeHolder;
        const pid = hit.post_id || hit.objectID?.split('-')[0] || '';
        const vgroup = hit.variant_group || '';

        return html`
            <article class="dim-ais-hit dim-grid-item" data-dim-product="${pid}" data-product-id="${pid}" data-variant-group="${vgroup}">
                <div class="product-card">
                    <div class="dim-ais-thumb"><img src="${img}" class="product-card__img" alt="" loading="lazy"/></div>
                    <div class="dim-ais-info">
                        <h3>${title}</h3>
                        <div class="dim-ais-meta">
                            <div class="meta-information">
                                ${artikelcode ? html`<span>Art.nr: ${artikelcode}</span>` : ''}
                                ${kleurSwatches(html, kleuren)}
                                ${diktes.length ? html`<div class="dim-card-row"><span class="dim-card-label">Dikte:</span>
                                    <span class="dim-card-diktes">${diktes.map((d, i) => html`${i ? ' | ' : ''}${d}`)}</span></div>` : ''}
                                ${formaten.length > 1 ? html`<div class="dim-card-row"><span class="dim-card-label">Formaat:</span>
                                    <span class="dim-card-diktes">${formaten.map((f, i) => html`${i ? ' | ' : ''}${f}`)}</span></div>` : ''}
                                ${vormen.length > 1 ? html`<div class="dim-card-row"><span class="dim-card-label">Verpakt:</span>
                                    <span class="dim-card-diktes">${vormen.map((v, i) => html`${i ? ' | ' : ''}${v}`)}</span></div>` : ''}
                                ${verpakt ? html`<div class="meta-sub"><span>${verpakt}</span></div>` : ''}
                            </div>

                            <div class="dim-ais-actions">
                                <button class="dim-ais-button" type="button"> Meer informatie </button>
                            </div>
                        </div>
                    </div>
                </div>
            </article>
        `;
    }

    const rl = (container, attribute, extra = {}) => refinementList({
        container, attribute, limit: 8, showMore: true,
        templates: { showMoreText: ({ isShowingMore }) => (isShowingMore ? 'Minder' : 'Meer...') },
        ...extra,
    });

    // Zoekbalk: gebruik de bestaande (Elementor) zoekbalk boven het grid als die er is —
    // stijl en plek blijven dan intact. De linker zoekbox verdwijnt in dat geval.
    const topSearchInput = [...document.querySelectorAll('input[placeholder="Zoeken"], input[placeholder="Zoeken..."], input[type="search"]')]
        .find(i => !i.closest('#dim-products-fsearch') && !i.closest('#wpadminbar') && i.offsetParent !== null);

    const koppelTopSearch = instantsearch.connectors.connectSearchBox((renderOptions, isFirstRender) => {
        const { refine, query } = renderOptions;
        if (isFirstRender) {
            const form = topSearchInput.closest('form');
            if (form) {
                // voorkom dat het Elementor-formulier echt verstuurt
                form.addEventListener('submit', (e) => {
                    e.preventDefault(); e.stopImmediatePropagation();
                    refine(topSearchInput.value);
                }, true);
            }
            let t;
            topSearchInput.addEventListener('input', () => {
                clearTimeout(t);
                t = setTimeout(() => refine(topSearchInput.value), 250);
            });
            // linker zoekbox verbergen — de bovenste neemt het over
            document.querySelector('#dim-product-search-ais-searchbox')?.closest('.filter-wrapper')?.style.setProperty('display', 'none');
        }
        if (document.activeElement !== topSearchInput && (topSearchInput.value || '') !== (query || '')) {
            topSearchInput.value = query || '';
        }
    });

    const widgetsArray = [
        topSearchInput
            ? koppelTopSearch({})
            : searchBox({
                container: '#dim-product-search-ais-searchbox',
                placeholder: 'Zoek op naam of artikelnummer...',
                showSubmit: false,
            }),
        stats({
            container: '#dim-product-search-ais-stats',
            templates: {
                text(data, { html }) {
                    return html`${data.nbHits} resultaten`;
                }
            }
        }),
        sortBy({
            container: '#dim-product-search-ais-sortby',
            items: [
                { value: indexNameAlgoliaSearch, label: 'Standaard' },
                { value: `${indexNameAlgoliaSearch}_priority_asc`, label: 'Prioriteit' },
                { value: `${indexNameAlgoliaSearch}_name_asc`, label: 'Naam A–Z' },
                { value: `${indexNameAlgoliaSearch}_date_desc`, label: 'Nieuwste' },
            ],
        }),
        clearRefinements({
            container: '#dim-product-search-ais-clear',
            templates: {
                resetLabel: 'Wis alle filters'
            },
            cssClasses: {
                root: "clear-filters-btn",
                item: "clear-filters-btn-item",
            }
        }),

        rl('#dim-product-search-filter-toepassing', 'toepassingen'),
        rl('#dim-product-search-filter-type', 'type'),
        rl('#dim-product-search-filter-quality', 'kwaliteit'),
        rl('#dim-product-search-filter-color', 'kleuren'),
        rl('#dim-product-search-filter-brand', 'merk_naam'),
        rangeInput({
            container: '#dim-product-search-filter-width',
            attribute: 'breedte_cm',
            templates: { separatorText: 'tot', submitText: 'OK' },
        }),
        rangeInput({
            container: '#dim-product-search-filter-length',
            attribute: 'lengte_cm',
            templates: { separatorText: 'tot', submitText: 'OK' },
        }),
        // Dikte in 2 stappen: dropdown voor de eenheid (T / my / mm), daarna van...tot.
        // Zo weet de bezoeker bewust in welke eenheid hij de dikte kiest.
        menuSelect({
            container: '#dim-product-search-filter-dikte-eenheid',
            attribute: 'dikte_eenheid',
            templates: { defaultOption: 'Kies eenheid (T / my / mm)' },
        }),
        rangeInput({
            container: '#dim-product-search-filter-dikte-waarde',
            attribute: 'dikte_waarde',
            templates: { separatorText: 'tot', submitText: 'OK' },
        }),
        // Verpakt: rol / los / doos / op koker ...
        rl('#dim-product-search-filter-verpakt', 'vorm'),
        toggleRefinement({
            container: '#dim-product-search-filter-trekband',
            attribute: 'trekband',
            templates: { labelText: 'Met trekband' },
        }),
        toggleRefinement({
            container: '#dim-product-search-filter-geperforeerd',
            attribute: 'geperforeerd',
            templates: { labelText: 'Geperforeerd' },
        }),

        hits({
            container: '#dim-product-search-ais-hits',
            escapeHtml: false,
            templates: {
                item: renderHit,
                empty: ({ query }) => `Geen resultaten voor <strong>${query}</strong>`,
            },
        }),
        pagination({
            container: '#dim-product-search-ais-pagination',
            cssClasses: {
                list: "dim-pagination-list",
                link: "link-item",
            },
            showFirst: false,
            showLast: false,
        }),
    ];

    if (!isTaxonomyPage) {
        widgetsArray.push(
            // distinct: één kaart per variant-groep (attributeForDistinct=variant_group in de index)
            // facetingAfterDistinct: badges in de filters tellen dan óók per variant-groep,
            // zodat het aantal in de badge klopt met het aantal getoonde resultaten.
            // N.B. het Categorie-filter (SAP-groepen) is bewust verwijderd uit de zijbalk;
            // de taxonomie zelf blijft bestaan voor de SAP-mapping.
            configure({ hitsPerPage, distinct: true, facetingAfterDistinct: true })
        );
    } else {
        widgetsArray.push(configure({ hitsPerPage, filters, distinct: true, facetingAfterDistinct: true }));
    }

    search.addWidgets(widgetsArray)
    search.start();

    /* --- Skeleton opruimen + #anker-correctie ------------------------------
       De producten komen async binnen; tot die tijd reserveert een server-side
       skeleton-grid (zie shortcode-PHP) de hoogte, zodat de pagina niet
       verspringt. Na de eerste échte render halen we het skeleton weg.
       Kwam de bezoeker binnen op een #anker (bv. /onze-producten/
       #section-zakkencalculator), dan scrollen we daarna nog één keer netjes
       naar dat anker — behalve als hij intussen zelf al heeft gescrold.
       '#product=123' is de detailpaneel-flow (products-overview.js): overslaan. */
    let ankerEl = null;
    const hash = decodeURIComponent(window.location.hash || '');
    if (hash.length > 1 && !hash.includes('=')) {
        ankerEl = document.getElementById(hash.slice(1));
    }

    let zelfGescrold = false;
    // mousedown vangt ook scrollbar-slepen en klikken: vanaf dat moment is de
    // bezoeker zelf bezig en corrigeren we niet meer
    ['wheel', 'touchmove', 'keydown', 'mousedown'].forEach((evt) =>
        window.addEventListener(evt, () => { zelfGescrold = true; }, { once: true, passive: true }));

    const scrollNaarAnker = () => {
        if (!ankerEl || zelfGescrold) return;
        // scroll-margin-top op [id^="section-"] (child style.css) houdt het
        // anker netjes onder de sticky header vandaan
        ankerEl.scrollIntoView({ behavior: 'auto', block: 'start' });
    };

    let eersteRenderGedaan = false;
    search.on('render', () => {
        if (eersteRenderGedaan) return;
        // pas doorpakken als er écht een Algolia-antwoord ligt (niet de lege init-render)
        if (!search.helper || !search.helper.lastResults) return;
        eersteRenderGedaan = true;
        skeletonEl?.remove();
        // Op meerdere momenten (opnieuw) naar het anker: rAF loopt niet in een
        // achtergrond-tab (link geopend in nieuw tabblad!) en late layout
        // (webfonts/afbeeldingen) kan het anker nog iets verschuiven.
        // zelfGescrold garandeert dat we de bezoeker nooit "terugtrekken".
        requestAnimationFrame(scrollNaarAnker);
        setTimeout(scrollNaarAnker, 300);
        setTimeout(scrollNaarAnker, 1000);
        if (document.readyState !== 'complete') {
            window.addEventListener('load', () => setTimeout(scrollNaarAnker, 50), { once: true });
        }
        if (document.visibilityState === 'hidden') {
            // Tab op de achtergrond (link in nieuw tabblad geopend): zolang een
            // tab hidden is negeert Chrome ALLE programmatische scrolls, dus
            // corrigeren we zodra hij zichtbaar wordt — met een tweede poging
            // voor het geval de layout bij activatie nog even zet.
            document.addEventListener('visibilitychange', () => {
                if (document.visibilityState !== 'visible') return;
                setTimeout(scrollNaarAnker, 50);
                setTimeout(scrollNaarAnker, 500);
            }, { once: true });
        }
    });
    search.on('error', () => skeletonEl?.remove()); // ook bij een Algolia-fout niet blijven shimmeren

    // Pin Filter
    const panel = document.getElementById('dim-ais-filter-container');
    const panelContainer = document.querySelector('.filter-pin-skeleton');
    const panelContainerWidth = panelContainer.getBoundingClientRect().width;

    const productsGrid = document.querySelector('.dim-ais__results');
    const inner = panel.querySelector('.dim-ais-filters-inner-container');
    if (!panel || !inner) return;

    const panelRect = panel.getBoundingClientRect();
    const sidebarLeft = panelRect.left;

    let mode = 'normal';

    // hoogte van de sticky header (menu): filters en detail moeten daaronder blijven hangen
    const headerH = parseInt(getComputedStyle(document.documentElement).getPropertyValue('--header-height')) || 148;

    const updatePosition = () => {
        const scrollY = window.scrollY;
        const gridRect = productsGrid.getBoundingClientRect();
        const gridBottom = scrollY + gridRect.bottom;

        const panelContainerRectTop = panelContainer.getBoundingClientRect().top;

        const panelHeight = panel.offsetHeight;
        const dockPoint = gridBottom - panelHeight - headerH;

        panel.style.setProperty('--sidebar-width', `${panelContainerWidth}px`);

        if (panelContainerRectTop <= headerH + 1 && scrollY < dockPoint) {
            // FIXED (scrolling)
            if (mode !== 'fixed') {
                panel.classList.add('is-fixed');
                panel.classList.remove('is-docked');
                panel.style.setProperty('--sidebar-left', `${sidebarLeft}px`);
                mode = 'fixed';
            }
        } else if (scrollY >= dockPoint) {
            // DOCKED at bottom
            if (mode !== 'docked') {
                panel.classList.add('is-docked');
                panel.classList.remove('is-fixed');
                mode = 'docked';
            }
        } else {
            // NORMAL (above start)
            if (mode !== 'normal') {
                panel.classList.remove('is-fixed', 'is-docked');
                mode = 'normal';
            }
        }
    };

    // Run on scroll and resize
    window.addEventListener('scroll', () => {
        updatePosition();
    });
    window.addEventListener('resize', () => {
        // Recalculate left offset if layout shifts
        const rect = panel.getBoundingClientRect();
        panel.style.setProperty('--sidebar-left', `${rect.left}px`);
        updatePosition();
    });

    updatePosition(); // initial run


    // If your filters expand/collapse:
    document.querySelectorAll('.filter-ais-dropdown-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            this.ariaExpanded = this.ariaExpanded !== 'true';
            const dropdown = this.nextElementSibling;
            const open = !dropdown.classList.contains('open');
            dropdown.classList.toggle('open', open);

            requestAnimationFrame(updatePosition);
        });
    });

    // Also refresh when search starts and after widgets mount
    search.on('render', () => requestAnimationFrame(updatePosition));
    search.on('rendered', () => requestAnimationFrame(updatePosition));

    // Safety net: late image loads
    const ro = new ResizeObserver(() => {
        clearTimeout(ro._t);
        ro._t = setTimeout(() => {
            requestAnimationFrame(updatePosition)
        }, 60);
    });
    ro.observe(productsGrid);
    ro.observe(inner);

})();
