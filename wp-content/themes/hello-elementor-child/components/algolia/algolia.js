(function () {
    const INDEX_NAME = 'wp2_searchable_posts'; // or your custom index, e.g. 'zak'
    // Optional: hard filters to only show your CPT (e.g. 'product' or 'zak')
    const ALGOLIA_FILTERS = 'post_type:product';

    // Ensure we only mount once per popup open
    let mounted = false;
    let search = null;

    const { appId, searchKey, placeHolder } = window.DIM_PRODUCT_SEARCH_MEGA_MENU;
    const { configure, searchBox, hits, hierarchicalMenu } = instantsearch.widgets;

    function mountInstantSearch(root) {
        if (mounted) return;
        mounted = true;

        const searchClient = algoliasearch(appId, searchKey);

        search = instantsearch({
            indexName: INDEX_NAME,
            searchClient,
            insights: false,
            attributesToHighlight: [],
        });

        // Widgets
        search.addWidgets([
            searchBox({
                container: '#dim-ais-searchbox',
                placeholder: 'Zoeken…',
                autoFocus: true,
                showReset: false,
                showSubmit: false,
                showLoadingIndicator: true,
                cssClasses: { input: 'my-input-class' }
            }),

            configure({
                hitsPerPage: 5,
                filters: ALGOLIA_FILTERS, // remove if you want all post types
                typoTolerance: 'min',
                removeWordsIfNoResults: 'allOptional',
            }),

            hierarchicalMenu({
                container: '#mega-menu-dim-hits-categories',
                attributes: [
                    'taxonomies.category_product'
                ],
                templates: {
                    item(hit, {html}) {
                        return html`<a href="category_product=${hit.value}"><h6>${hit.label}</h6></a>`;
                    }
                }
            }),

            hits({
                container: '#dim-ais-hits',
                templates: {
                    item(hit, {html, components}) {
                        const title = components.Highlight({ attribute: 'post_title', hit }) || hit.title || '';
                        const url = hit.permalink || hit.url || '#';
                        const firstCategory = hit.taxonomies?.category_product[0] || '';
                        const img = hit?.medium_url || placeHolder;
                        const imgAlt = `${hit.post_title}-img`;

                        return html`
                            <a href="${url}" class="dim-ais-hit">
                                <article class="dim-ais-hit-container">
                                    <div class="dim-ais-thumb"><img src="${img}" class="product-card__img" alt="${imgAlt}"/></div>
                                    <div class="hit-meta">
                                        <h6>${title}</h6>
                                        ${firstCategory ? ` ${firstCategory}` : ''}
                                    </div>
                              </article>
                            </a>
                        `;
                    },
                    empty(results) {
                        return `Geen resultaten voor <strong>${results.query}</strong>`;
                    }
                }
            }),
        ]);

        search.start();
    }

    // MOUNT search widget to the #dim-ais
    const root = document.getElementById('mega-menu-dim-ais');
    if (root) {
        // delay so DOM is fully loaded
        setTimeout(() => {
            mountInstantSearch(root);
        }, 10);
    }
})();