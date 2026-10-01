(function () {
    if (!window.DIM_PRODUCT_SEARCH) return;

    const { appId, searchKey, indexNameAlgoliaSearch, hitsPerPage, filters, isTaxonomyPage, placeHolder } = window.DIM_PRODUCT_SEARCH;
    const skeletonEl = document.getElementById('dim-product-search-ais-skeleton');
    if (!appId || !searchKey) {
        console.warn('DIM: Algolia keys ontbreken');
        skeletonEl?.remove(); // geen eeuwig shimmerend skelet als de zoeker niet kan starten
        return;
    }

    const { searchBox, refinementList, rangeInput, toggleRefinement, configure, stats, hits, pagination, menuSelect } = instantsearch.widgets;
    const searchClient = algoliasearch(appId, searchKey);

    /* --- Nette, stabiele filter-URL's --------------------------------------
       Standaard zet InstantSearch de INDEXNAAM in elke URL
       (?wp2_searchable_posts[refinementList][kwaliteit][0]=LDPE&...). Dat is
       lang én breekt zodra de index-prefix ooit wijzigt (lokaal wp2_, live
       waarschijnlijk anders). Daarom een eigen, korte querytaal die WIJ
       beheren — deeplinks blijven daardoor altijd werken, bv.:
         /onze-producten/?materiaal=LDPE~HDPE
         /onze-producten/?q=ldpe&kleur=blauw~zwart&sorteer=naam
       Meerdere waarden scheid je met een ~ (tilde). Ranges: breedte=60-90. */
    const RL_PARAMS = [
        ['toepassing', 'toepassingen'], ['type', 'type'], ['materiaal', 'kwaliteit'],
        ['kleur', 'kleuren'], ['merk', 'merk_naam'], ['verpakt', 'vorm'],
    ];
    const RANGE_PARAMS = [
        ['breedte', 'breedte_cm'], ['lengte', 'lengte_cm'], ['dikte', 'dikte_waarde'],
        // omtrek heeft geen zichtbaar filter in de zijbalk; het is de taal die de
        // zakkencalculator gebruikt om hierheen te deeplinken (?omtrek=211-264).
        // De bijbehorende range-widget wordt verderop verborgen gemount, want
        // InstantSearch negeert een range-state zonder widget voor dat attribuut.
        ['omtrek', 'omtrek_cm'],
    ];
    const URL_NAAR_SORT = {
        prioriteit: `${indexNameAlgoliaSearch}_priority_asc`,
        naam: `${indexNameAlgoliaSearch}_name_asc`,
        nieuwste: `${indexNameAlgoliaSearch}_date_desc`,
    };
    const SORT_NAAR_URL = Object.fromEntries(Object.entries(URL_NAAR_SORT).map(([k, v]) => [v, k]));

    const stateMapping = {
        stateToRoute(uiState) {
            const ui = uiState[indexNameAlgoliaSearch] || {};
            const route = {};
            if (ui.query) route.q = ui.query;
            RL_PARAMS.forEach(([param, attr]) => {
                const w = ui.refinementList && ui.refinementList[attr];
                if (w && w.length) route[param] = w.join('~');
            });
            RANGE_PARAMS.forEach(([param, attr]) => {
                const w = ui.range && ui.range[attr];
                if (w) route[param] = String(w).replace(':', '-');
            });
            if (ui.menu && ui.menu.dikte_eenheid) route.dikte_eenheid = ui.menu.dikte_eenheid;
            if (ui.toggle && ui.toggle.trekband) route.trekband = '1';
            if (ui.toggle && ui.toggle.geperforeerd) route.geperforeerd = '1';
            if (ui.toggle && ui.toggle.bedrukking_mogelijk) route.bedrukking = '1';
            if (ui.sortBy && SORT_NAAR_URL[ui.sortBy]) route.sorteer = SORT_NAAR_URL[ui.sortBy];
            if (ui.page && ui.page > 1) route.pagina = ui.page;
            return route;
        },
        routeToState(route = {}) {
            const ui = { refinementList: {}, range: {}, menu: {}, toggle: {} };
            if (route.q) ui.query = String(route.q);
            RL_PARAMS.forEach(([param, attr]) => {
                if (route[param]) ui.refinementList[attr] = String(route[param]).split('~');
            });
            RANGE_PARAMS.forEach(([param, attr]) => {
                if (route[param]) ui.range[attr] = String(route[param]).replace('-', ':');
            });
            if (route.dikte_eenheid) ui.menu.dikte_eenheid = String(route.dikte_eenheid);
            if (route.trekband) ui.toggle.trekband = true;
            if (route.geperforeerd) ui.toggle.geperforeerd = true;
            if (route.bedrukking) ui.toggle.bedrukking_mogelijk = true;
            if (route.sorteer && URL_NAAR_SORT[route.sorteer]) ui.sortBy = URL_NAAR_SORT[route.sorteer];
            if (route.pagina) ui.page = Number(route.pagina) || 1;
            return { [indexNameAlgoliaSearch]: ui };
        },
    };

    const search = instantsearch({
        indexName: indexNameAlgoliaSearch,
        searchClient,
        routing: { stateMapping },
    });

    // De header-zoek (components/header-search) verfijnt hiermee live zodra de
    // bezoeker al op een pagina met de productenzoeker staat - scheelt een reload.
    window.DIM_AIS = search;

    // NL kleurnaam -> swatch-kleur (transparant krijgt een blokjespatroon via CSS)
    const KLEUR_HEX = {
        wit: '#ffffff', zwart: '#111827', blauw: '#2563eb', grijs: '#9ca3af', oranje: '#f97316',
        groen: '#16a34a', geel: '#facc15', rood: '#dc2626', bruin: '#92400e', paars: '#7c3aed',
        roze: '#ec4899', naturel: '#e7dcc8', zilver: '#c0c0c0', goud: '#d4af37',
    };

    function swatchesKlein(html, kleuren) {
        // kleuren = array met kleur-strings; samengestelde waarden ("geel/groen") splitsen
        const tokens = [...new Set(kleuren.flatMap(k => String(k).split(/[\/,]/)).map(s => s.trim().toLowerCase()).filter(Boolean))];
        return tokens.map(t => t === 'transparant'
            ? html`<span class="dim-swatch is-transparant" title="transparant"></span>`
            : html`<span class="dim-swatch" title="${t}" style="background:${KLEUR_HEX[t] || '#d1d5db'}"></span>`);
    }

    // Hits template — één kaart per variant-groep (distinct); kaart werkt met het bestaande
    // detailpaneel (.dim-grid-item + data-dim-product => products-overview.js opent het paneel).
    // DESIGN 2026: hele kaart klikbaar (geen knop), naam + artikelcode linksonder,
    // specs (kleur / formaat / materiaal) rechtsonder — zoals website_DZS_producten.pdf.
    function renderHit(hit, { html }) {
        const isVariant = (hit.variant_count || 1) > 1;
        const title = (isVariant && hit.variant_base_title) ? hit.variant_base_title
            : (hit.post_title || hit.omschrijving || '(zonder titel)');
        const artikelcode = hit.artikelcode || '';
        const kleuren = Array.isArray(hit.variant_kleuren) && hit.variant_kleuren.length
            ? hit.variant_kleuren : (hit.kleuren ? [hit.kleuren] : []);
        const formaten = Array.isArray(hit.variant_formaten) && hit.variant_formaten.length
            ? hit.variant_formaten : (hit.formaat ? [hit.formaat] : []);
        const img = hit?.medium_url || placeHolder;
        const pid = hit.post_id || hit.objectID?.split('-')[0] || '';
        const vgroup = hit.variant_group || '';

        // rechterkolom: kleur als tekst bij één kleur, swatches bij meer;
        // formaat alleen als hij eenduidig is (de range zit al in de titel)
        const kleurSpec = kleuren.length === 1
            ? html`<span class="dim-spec">${kleuren[0]}</span>`
            : (kleuren.length ? html`<span class="dim-spec dim-spec-swatches">${swatchesKlein(html, kleuren)}</span>` : '');
        const formaatSpec = formaten.length === 1
            ? html`<span class="dim-spec">${formaten[0]}</span>`
            : (formaten.length ? html`<span class="dim-spec">${formaten.length} maten</span>` : '');

        return html`
            <article class="dim-ais-hit dim-grid-item" data-dim-product="${pid}" data-product-id="${pid}" data-variant-group="${vgroup}">
                <div class="product-card">
                    <div class="dim-ais-thumb"><img src="${img}" class="product-card__img" alt="" loading="lazy"/></div>
                    <div class="dim-ais-info">
                        <div class="dim-ais-naam">
                            <h3>${title}</h3>
                            ${artikelcode ? html`<span class="dim-ais-code">${artikelcode}</span>` : ''}
                        </div>
                        <div class="dim-ais-specs">
                            ${kleurSpec}
                            ${formaatSpec}
                            ${hit.kwaliteit ? html`<span class="dim-spec">${hit.kwaliteit}</span>` : ''}
                        </div>
                    </div>
                </div>
            </article>
        `;
    }

    // Widgets alleen registreren als hun container in de DOM staat — de shortcode kan
    // "kaal" draaien (toon_filters="nee" / toon_balk="nee", bv. op just-gloves) en
    // InstantSearch gooit een fout bij een ontbrekende container.
    const wanneer = (selector, maak) => (document.querySelector(selector) ? maak() : null);

    const rl = (container, attribute, extra = {}) => wanneer(container, () => refinementList({
        // design 2026: ALLE waarden direct tonen, geen "Meer..."-knop
        container, attribute, limit: 100, showMore: false,
        ...extra,
    }));

    // Zoekbalk: gebruik de bestaande (Elementor) zoekbalk boven het grid als die er is —
    // stijl en plek blijven dan intact. De linker zoekbox verdwijnt in dat geval.
    const topSearchInput = [...document.querySelectorAll('input[placeholder="Zoeken"], input[placeholder="Zoeken..."], input[type="search"]')]
        .find(i => !i.closest('#dim-products-fsearch') && !i.closest('#wpadminbar')
                && !i.closest('.dzs-hzoek') && i.offsetParent !== null);

    // het kruisje in de zoekbalk; wordt bij de eerste render aangemaakt
    let zoekWissenKnop = null;

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
                // direct meeschakelen, niet pas na de debounce
                if (zoekWissenKnop) zoekWissenKnop.hidden = !topSearchInput.value;
                clearTimeout(t);
                t = setTimeout(() => refine(topSearchInput.value), 250);
            });
            // linker zoekbox verbergen — de bovenste neemt het over
            document.querySelector('#dim-product-search-ais-searchbox')?.closest('.filter-wrapper')?.style.setProperty('display', 'none');

            /* Kruisje in de zoekbalk, alleen zichtbaar als er iets in staat.
               We hangen hem in de ouder van het veld en zetten die op relative;
               de knop zelf ligt rechts in het veld (opmaak staat in de CSS).
               type="button", anders verstuurt hij het Elementor-formulier. */
            const houder = topSearchInput.parentElement;
            if (houder && !houder.querySelector('.dim-zoek-wissen')) {
                houder.classList.add('dim-zoek-houder');

                zoekWissenKnop = document.createElement('button');
                zoekWissenKnop.type = 'button';
                zoekWissenKnop.className = 'dim-zoek-wissen';
                zoekWissenKnop.setAttribute('aria-label', 'Zoekopdracht wissen');
                zoekWissenKnop.hidden = true;

                zoekWissenKnop.addEventListener('click', () => {
                    topSearchInput.value = '';
                    refine('');
                    topSearchInput.focus();
                });

                houder.appendChild(zoekWissenKnop);
            }
        }

        if (document.activeElement !== topSearchInput && (topSearchInput.value || '') !== (query || '')) {
            topSearchInput.value = query || '';
        }
        // het kruisje volgt wat er in het veld staat, niet wat Algolia terugstuurt:
        // tijdens het typen loopt de query 250ms achter
        if (zoekWissenKnop) zoekWissenKnop.hidden = !topSearchInput.value;
    });

    /* --- Sorteeropties -------------------------------------------------------
       NIET dynamisch, en dat kan ook niet: elke optie is een eigen Algolia-index
       (een replica), aangemaakt door components/algolia/algolia.php via
       ?dim_push_algolia_replicas=1. De zoeksleutel in de browser mag geen
       indexen opvragen, dus de lijst hoort hier te staan.

       Komt er een sorteervolgorde bij? Dan twee plekken: de replica in
       algolia.php, en een regel hieronder. */
    const SORTEER_OPTIES = [
        { value: indexNameAlgoliaSearch, label: 'Standaard' },
        { value: `${indexNameAlgoliaSearch}_priority_asc`, label: 'Prioriteit' },
        { value: `${indexNameAlgoliaSearch}_name_asc`, label: 'Naam A–Z' },
        { value: `${indexNameAlgoliaSearch}_date_desc`, label: 'Nieuwste' },
    ];

    /* Vertaling van wat er in de Elementor-dropdown staat naar de index.
       We kijken eerst naar de waarde van de optie en anders naar de tekst,
       allebei kleingeschreven. Zo werkt "prio", "Prioriteit" en "priority"
       allemaal, en kun je in Elementor een optie bijzetten zonder hier iets
       te wijzigen — zolang de replica in algolia.php maar bestaat. */
    const SORTEER_PER_SLEUTEL = {
        'standaard':  indexNameAlgoliaSearch,
        'default':    indexNameAlgoliaSearch,
        '':           indexNameAlgoliaSearch,
        'prio':       `${indexNameAlgoliaSearch}_priority_asc`,
        'prioriteit': `${indexNameAlgoliaSearch}_priority_asc`,
        'priority':   `${indexNameAlgoliaSearch}_priority_asc`,
        'naam':       `${indexNameAlgoliaSearch}_name_asc`,
        'naam a–z':   `${indexNameAlgoliaSearch}_name_asc`,
        'naam a-z':   `${indexNameAlgoliaSearch}_name_asc`,
        'name':       `${indexNameAlgoliaSearch}_name_asc`,
        'nieuwste':   `${indexNameAlgoliaSearch}_date_desc`,
        'nieuw':      `${indexNameAlgoliaSearch}_date_desc`,
        'datum':      `${indexNameAlgoliaSearch}_date_desc`,
    };

    const indexVoorOptie = (optie) => optie && (
        SORTEER_PER_SLEUTEL[(optie.value || '').trim().toLowerCase()]
        || SORTEER_PER_SLEUTEL[(optie.text || '').trim().toLowerCase()]
    );

    /* "Wis alle filters" is een Elementor-knop met id "dim-remove-filters". Dat
       id staat op de tekst-span ín de knop, dus we zoeken de klikbare <a>/<button>
       eromheen — anders vangt de knop de klik nog voordat de span hem ziet.
       Let op: dit wist de filters, niet de zoekterm. Dat is ook wat er op staat. */
    /* "Wis alle filters" staat op twee plekken: in de bovenbalk op desktop
       (#dim-remove-filters, een Elementor-knop) en in de kopbalk van het
       filterscherm op klein scherm (.dim-filter-wissen, uit de shortcode).
       Allebei doen hetzelfde, dus één listener die op beide matcht.

       Op document in de capture-fase, net als de filterknop: Elementor vangt
       klikken op zijn eigen elementen anders eerder af. */
    const WIS_KNOPPEN = '#dim-remove-filters, .dim-filter-wissen';

    const koppelWisFilters = instantsearch.connectors.connectClearRefinements(
        (renderOptions, isFirstRender) => {
            const { refine } = renderOptions;
            if (!isFirstRender) return;

            document.addEventListener('click', (e) => {
                if (!e.target.closest || !e.target.closest(WIS_KNOPPEN)) return;
                e.preventDefault();
                e.stopImmediatePropagation();

                // Eerst de zoekterm leeg, dan de filters. refine() doet de
                // zoekopdracht, dus dit gaat samen in één ronde naar Algolia.
                if (search.helper) search.helper.setQuery('');
                refine();

                // Op klein scherm is wissen het einde van de handeling: terug
                // naar de resultaten.
                if (window.matchMedia('(max-width: 1023px)').matches) {
                    document.documentElement.classList.remove('dim-filters-open');
                    const t = document.getElementById('mobile-filter-toggle');
                    if (t) t.setAttribute('aria-expanded', 'false');
                }
            }, true);

            // Bewust geen uitgeschakelde staat: de knop blijft altijd klikbaar,
            // ook als er niets te wissen valt. Scheelt een balk die verspringt
            // zodra je je eerste filter aanzet.
        }
    );

    /* --- Filterscherm onder 1024 -------------------------------------------
       Daar staan filter, overzicht en detail niet naast elkaar maar over
       elkaar. Het filtericoon (#mobile-filter-toggle) schuift het filterscherm
       vanaf links in beeld; de CSS doet de beweging, hier alleen de schakelaar.

       De class staat op <html> en niet op het paneel zelf, omdat de pagina
       eronder tegelijk op slot moet — anders scroll je door het overzicht
       terwijl je in de filters staat. */
    const filterKnop = document.getElementById('mobile-filter-toggle');

    if (filterKnop) {
        const zetFilters = (open) => {
            document.documentElement.classList.toggle('dim-filters-open', open);
            filterKnop.setAttribute('aria-expanded', open ? 'true' : 'false');
        };

        filterKnop.setAttribute('aria-expanded', 'false');

        // Luisteren op DOCUMENT in de capture-fase, niet op de knop zelf.
        // Elementor hangt eigen afhandeling aan zijn elementen en die kwam er
        // soms eerder bij; dezelfde reden als bij de Bereken-knop van de
        // zakkencalculator. Zo zijn we altijd eerst.
        document.addEventListener('click', (e) => {
            if (!e.target.closest) return;

            if (e.target.closest('#mobile-filter-toggle')) {
                e.preventDefault();
                e.stopImmediatePropagation();
                zetFilters(!document.documentElement.classList.contains('dim-filters-open'));
                return;
            }

            // het kruisje rechtsboven in het filterscherm
            if (e.target.closest('.dim-filter-sluiten')) {
                e.preventDefault();
                e.stopImmediatePropagation();
                zetFilters(false);
            }
        }, true);

        // Escape sluit, net als bij de header-zoek
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') zetFilters(false);
        });

        // Van klein naar groot scherm: het scherm hoort dan niet open te blijven
        // staan, want daar is de filterbalk gewoon een kolom.
        window.addEventListener('resize', () => {
            if (window.matchMedia('(min-width: 1024px)').matches) zetFilters(false);
        });
    }

    /* De sorteer-dropdown is een gewone Elementor Select met id "sort-products".
       We laten hem staan zoals hij is — opmaak en opties beheer jij daar — en
       hangen er alleen gedrag aan. Staat hij er niet, dan gebeurt er niets. */
    const sorteerSelect = document.getElementById('sort-products');

    const koppelSorteerSelect = sorteerSelect && instantsearch.connectors.connectSortBy(
        (renderOptions, isFirstRender) => {
            const { refine, currentRefinement } = renderOptions;

            if (isFirstRender) {
                sorteerSelect.addEventListener('change', () => {
                    const index = indexVoorOptie(sorteerSelect.options[sorteerSelect.selectedIndex]);
                    if (index) refine(index);
                    else console.warn('DIM: onbekende sorteeroptie', sorteerSelect.value);
                });
            }

            // Terug de andere kant op: bij een deeplink (?sorteer=naam) of de
            // terug-knop van de browser moet de dropdown de actieve stand tonen.
            const passend = [...sorteerSelect.options].find((o) => indexVoorOptie(o) === currentRefinement);
            if (passend && sorteerSelect.value !== passend.value) {
                sorteerSelect.value = passend.value;
            }
        }
    );

    const widgetsArray = [
        topSearchInput
            ? koppelTopSearch({})
            : wanneer('#dim-product-search-ais-searchbox', () => searchBox({
                container: '#dim-product-search-ais-searchbox',
                placeholder: 'Zoek op naam of artikelnummer...',
                showSubmit: false,
            })),
        koppelSorteerSelect ? koppelSorteerSelect({ items: SORTEER_OPTIES }) : null,
        koppelWisFilters ? koppelWisFilters({}) : null,
        /* Teller bovenaan de filterlijst. Staat waar eerder de reset-link
           "Alle zakken" zat: wissen gebeurt nu via "Wis alle filters". */
        wanneer('#dim-product-search-ais-stats', () => stats({
            container: '#dim-product-search-ais-stats',
            templates: {
                text: (data, { html }) => html`${data.nbHits} resultaten`,
            },
        })),

        rl('#dim-product-search-filter-toepassing', 'toepassingen'),
        rl('#dim-product-search-filter-type', 'type'),
        rl('#dim-product-search-filter-quality', 'kwaliteit'),
        rl('#dim-product-search-filter-color', 'kleuren'),
        rl('#dim-product-search-filter-brand', 'merk_naam'),
        wanneer('#dim-product-search-filter-width', () => rangeInput({
            container: '#dim-product-search-filter-width',
            attribute: 'breedte_cm',
            templates: { separatorText: 'tot', submitText: 'OK' },
        })),
        wanneer('#dim-product-search-filter-length', () => rangeInput({
            container: '#dim-product-search-filter-length',
            attribute: 'lengte_cm',
            templates: { separatorText: 'tot', submitText: 'OK' },
        })),
        // Dikte in 2 stappen: dropdown voor de eenheid (T / my / mm), daarna van...tot.
        // Zo weet de bezoeker bewust in welke eenheid hij de dikte kiest.
        wanneer('#dim-product-search-filter-dikte-eenheid', () => menuSelect({
            container: '#dim-product-search-filter-dikte-eenheid',
            attribute: 'dikte_eenheid',
            templates: { defaultOption: 'Kies eenheid (T / my / mm)' },
        })),
        wanneer('#dim-product-search-filter-dikte-waarde', () => rangeInput({
            container: '#dim-product-search-filter-dikte-waarde',
            attribute: 'dikte_waarde',
            templates: { separatorText: 'tot', submitText: 'OK' },
        })),
        // Verpakt: rol / los / doos / op koker ...
        rl('#dim-product-search-filter-verpakt', 'vorm'),
        wanneer('#dim-product-search-filter-trekband', () => toggleRefinement({
            container: '#dim-product-search-filter-trekband',
            attribute: 'trekband',
            templates: { labelText: 'Met trekband' },
        })),
        wanneer('#dim-product-search-filter-geperforeerd', () => toggleRefinement({
            container: '#dim-product-search-filter-geperforeerd',
            attribute: 'geperforeerd',
            templates: { labelText: 'Geperforeerd' },
        })),
        // Bedrukking is een dienst, geen producteigenschap: aangevinkt toont het
        // de zakken waarvan we weten dat er een opdruk op kan.
        wanneer('#dim-product-search-filter-bedrukking', () => toggleRefinement({
            container: '#dim-product-search-filter-bedrukking',
            attribute: 'bedrukking_mogelijk',
            templates: { labelText: 'Bedrukking mogelijk' },
        })),

        hits({
            container: '#dim-product-search-ais-hits',
            escapeHtml: false,
            templates: {
                item: renderHit,
                empty: ({ query }) => `Geen resultaten voor <strong>${query}</strong>`,
            },
        }),
        wanneer('#dim-product-search-ais-pagination', () => pagination({
            container: '#dim-product-search-ais-pagination',
            cssClasses: {
                list: "dim-pagination-list",
                link: "link-item",
            },
            templates: {
                previous: '&lt; Vorige',
                next: 'Volgende &gt;',
            },
            showFirst: false,
            showLast: false,
        })),
    ];

    // distinct: één kaart per variant-groep (attributeForDistinct=variant_group in de index)
    // facetingAfterDistinct: badges in de filters tellen dan óók per variant-groep,
    // zodat het aantal in de badge klopt met het aantal getoonde resultaten.
    // filters komt uit de shortcode: taxonomie-pagina's krijgen hun term-filter,
    // gewone pagina's standaard post_type:product, just-gloves post_type:handschoen.
    // N.B. het Categorie-filter (SAP-groepen) is bewust verwijderd uit de zijbalk;
    // de taxonomie zelf blijft bestaan voor de SAP-mapping.
    /* --- Verborgen omtrek-range -------------------------------------------
       De zakkencalculator stuurt bezoekers hierheen met ?omtrek=211-264. Zonder
       een gemounte widget voor omtrek_cm laat InstantSearch die state vallen,
       dus hangen we er een rangeInput aan in een verborgen container. De
       bezoeker ziet hem niet; het actieve filter blijkt uit de resultaten en de
       "Alle zakken"-reset ruimt hem gewoon mee op. */
    (function mountOmtrekRange() {
        if (document.getElementById('dim-product-search-filter-omtrek')) return;
        const host = document.querySelector('#dim-product-search-ais-hits') || document.body;
        const el = document.createElement('div');
        el.id = 'dim-product-search-filter-omtrek';
        el.hidden = true;
        host.parentNode.insertBefore(el, host);
    })();
    widgetsArray.push(wanneer('#dim-product-search-filter-omtrek', () => rangeInput({
        container: '#dim-product-search-filter-omtrek',
        attribute: 'omtrek_cm',
    })));

    widgetsArray.push(configure({
        hitsPerPage,
        ...(filters ? { filters } : {}),
        distinct: true,
        facetingAfterDistinct: true,
    }));

    search.addWidgets(widgetsArray.filter(Boolean))
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

    /* --- Tellers in de filters: de puls bij een wijziging -------------------
       De getallen zelf komen uit InstantSearch (RefinementList rendert ze
       standaard). Wat hier gebeurt is puur de beleving: na elke render
       vergelijken we elk getal met de vorige waarde en geven we alleen de
       veranderde tellers kort een groen accent. Zo zie je in één oogopslag
       welk effect je filterkeuze had op de rest van de filters. */
    const vorigeTellers = new Map();
    search.on('render', () => {
        document
            .querySelectorAll('.filter-dropdown .ais-RefinementList-item, .filter-dropdown .ais-ToggleRefinement')
            .forEach((rij) => {
                const teller = rij.querySelector('.ais-RefinementList-count, .ais-ToggleRefinement-count');
                const label  = rij.querySelector('.ais-RefinementList-labelText, .ais-ToggleRefinement-labelText');
                if (!teller || !label) return;

                // sleutel = filter + waarde, zodat 'Zwart' in Kleur niet botst
                // met 'Zwart' in een ander filter
                const filterId = rij.closest('[id^="dim-product-search-filter-"]')?.id || '';
                const sleutel  = filterId + '::' + label.textContent.trim();
                const nu       = teller.textContent.trim();
                const was      = vorigeTellers.get(sleutel);
                vorigeTellers.set(sleutel, nu);

                if (was === undefined || was === nu) return;  // eerste render of ongewijzigd
                teller.classList.remove('is-gewijzigd');
                void teller.offsetWidth;                      // reflow: animatie opnieuw starten
                teller.classList.add('is-gewijzigd');
            });
    });

    // Bij paginawissel terug naar de bovenkant van het grid — de nieuwe pagina
    // rendert anders buiten beeld (scroll-margin-top staat in de CSS).
    document.getElementById('dim-product-search-ais-pagination')?.addEventListener('click', (e) => {
        if (!e.target.closest('.ais-Pagination-link')) return;
        setTimeout(() => {
            document.getElementById('dim-products-fsearch')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }, 60);
    });

    // DESIGN 2026: de filters scrollen NIET meer mee — geen fixed/docked pin.
    // De filterbalk zit vast aan het grid (één blok); wordt de filterlijst
    // hoger dan het scherm, dan scrollt hij INTERN (CSS: max-height +
    // overflow-y op .dim-ais-filters-inner-container). updatePosition blijft
    // als no-op bestaan voor de dropdown-handlers hieronder.
    const updatePosition = () => {};
    if (!document.getElementById('dim-ais-filter-container')) return; // kale modus: geen filters


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

    // eerste filterblok standaard open, zoals in het design (website_DZS_producten.pdf)
    const eersteFilterBtn = document.querySelector('.filter-ais-dropdown-btn');
    if (eersteFilterBtn && eersteFilterBtn.nextElementSibling) {
        eersteFilterBtn.ariaExpanded = 'true';
        eersteFilterBtn.nextElementSibling.classList.add('open');
    }

})();
