(function () {
    const layout = document.querySelector('.dim-products-layout');
	console.log('layout', layout);
    if (!layout) return;

    const panelContent = document.querySelector('#dim-product-detail-content');
	console.log('panel', panelContent);
    if (!panelContent) return;

    const queryParam = (window.dimProductPanel && window.dimProductPanel.queryParam) || 'product';
    const ajaxUrl = (window.dimProductPanel && window.dimProductPanel.ajaxUrl) || '/wp-admin/admin-ajax.php';

    let currentProductId = null;
    const productCache = new Map();

    function setOpenState(isOpen) {
        layout.classList.toggle('product-open', isOpen);
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

    async function loadProduct(productId, pushUrl = true) {
        if (!productId || String(productId) === currentProductId) return;

        currentProductId = String(productId);
        setOpenState(true);
        setActiveCard(currentProductId);

        if (productCache.get(productId)) {
            panelContent.innerHTML = productCache.get(productId).html;
            pushUrlChange(pushUrl, productId);
            return
        }

        try {
            const url = new URL(ajaxUrl, window.location.origin);
            url.searchParams.set('action', 'dim_get_product_detail');
            url.searchParams.set('product_id', productId);

            const product_slug = location.pathname.split('/')[1]
            const template_id =  product_slug === 'just-gloves' ? 3626 : 1498

            url.searchParams.set('template_id', template_id);

            const response = await fetch(url.toString(), {
                method: 'GET',
                credentials: 'same-origin',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                }
            });

            const data = await response.json();

            if (!data.success || !data.data || !data.data.html) {
                panelContent.innerHTML = '<div class="dim-product-error">Could not load product.</div>';
                return;
            }

            panelContent.innerHTML = data.data.html;
            productCache.set(productId, data.data);

            pushUrlChange(pushUrl, productId)

            initBarCodes();
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
		console.log('active');
        const card = event.target.closest('.dim-grid-item');
		console.log('card', card);
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
})();