(function () {
    if (!window.DIM_PRODUCT_SEARCH) return;

    const { appId, searchKey, indexNameAlgoliaSearch, hitsPerPage, filters, isTaxonomyPage, placeHolder } = window.DIM_PRODUCT_SEARCH;
    const { refinementList, configure, clearRefinements, stats, sortBy, hits, pagination} = instantsearch.widgets;
    const searchClient = algoliasearch(appId, searchKey);

    const search = instantsearch({
        indexName: indexNameAlgoliaSearch,
        searchClient,
        routing: true,
    });

    // Hits template
    function renderHit(hit, { html }) {
        const title = hit.post_title || hit.title || '(zonder titel)';
        const url = hit.permalink || hit.url || '#';
        const artikelcode = hit.algemene_informatie_artikelcode || '';
        const materialType = hit.materiaalspecificaties_type || '';
        const kwaliteit = hit.materiaalspecificaties_kwaliteit || '';
        const shortDescription = hit.algemene_informatie_omschrijving_kort || '';
        const kleur = hit.bedrukkingsspecificaties_kleuren || '';
        const thickness = hit.materiaalspecificaties_dikte || '';
        const img = hit?.medium_url || placeHolder;

         return html`
            <article class="dim-ais-hit">
                <a class="product-card" href="${url}" >
                    <div class="dim-ais-thumb"><img src="${img}" class="product-card__img" alt=""/></div>
                    <div class="dim-ais-info">
                        <h3>${title}</h3>
                        <div class="dim-ais-meta">
                            <div class="meta-information">
                                ${artikelcode ? html`<span>Art.nr: ${artikelcode}</span>` : ''}
                                <div class="meta-sub">
                                    ${shortDescription ? html`<span>Omschrijving: ${shortDescription}</span>` : ''}
                                    ${materialType ? html`<span>${materialType}</span>` : ''}
                                    ${kwaliteit ? html`<span>| ${kwaliteit}</span>` : ''}
                                    ${thickness ? html`<span>| ${thickness}</span>` : ''}
                                    ${kleur ? html`<span>| ${kleur}</span>` : ''}
                                </div>
                            </div>
            
                            <div class="dim-ais-actions">
                                <button class="dim-ais-button"> Meer informatie </button>
                            </div>
                        </div>
                    </div>
                </a>
            </article>
        `;
    }

    const widgetsArray = [
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

        refinementList({
            container: '#dim-product-search-filter-material-type',
            attribute: 'materiaalspecificaties_type',
        }),
        refinementList({
            container: '#dim-product-search-filter-material-thickness',
            attribute: 'materiaalspecificaties_dikte',
        }),
        refinementList({
            container: '#dim-product-search-filter-color',
            attribute: 'bedrukkingsspecificaties_kleuren',
        }),
        refinementList({
            container: '#dim-product-search-filter-size',
            attribute: 'algemene_informatie_formaat',
        }),
        refinementList({
            container: '#dim-product-search-filter-quality',
            attribute: 'materiaalspecificaties_kwaliteit',
        }),
        refinementList({
            container: '#dim-product-search-filter-recycled-material',
            attribute: 'materiaalspecificaties_gebruik_van_recycled_materiaal',
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
            configure({ hitsPerPage }),

            refinementList({
                container: '#dim-product-search-filter-category',
                attribute: 'taxonomies.category_product',
                transformItems(items, { container, result }) {
                    if (!items) {
                        // container.closest('filter')
                    }
                    return items;
                }
            })
        );
    } else {
        widgetsArray.push(configure({ hitsPerPage, filters }));
    }

    search.addWidgets(widgetsArray)
    search.start();

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

    const updatePosition = () => {
        const scrollY = window.scrollY;
        const gridRect = productsGrid.getBoundingClientRect();
        const gridBottom = scrollY + gridRect.bottom;

        const panelContainerRectTop = panelContainer.getBoundingClientRect().top;

        const panelHeight = panel.offsetHeight;
        const dockPoint = gridBottom - panelHeight;

        panel.style.setProperty('--sidebar-width', `${panelContainerWidth}px`);

        if (panelContainerRectTop <= 1 && scrollY < dockPoint) {
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
    // Wait a tick for images to affect layout, then refresh
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