document.addEventListener('DOMContentLoaded', () => {
    const body = document.body;
    const headerWrapper = document.getElementById('dim-mega-menu-wrapper');

    const panelMegaMenu = document.getElementById('dim-header-mega-menu');
    const dimMenu = document.getElementById('dim-nav-main-menu');

    const searchElementorContainer = document.getElementById('dim-mega-menu-search-container');
    const searchInput = searchElementorContainer.querySelector('#dim-mega-menu-search-input-field');

    const btnOpenSearch = document.getElementById('dim-toggle-search-in-menu');     // open as search
    const btnClose = document.getElementById('dim-close-menu-button');

    if (!panelMegaMenu) return;

    // CONFIG
    const BASE_WIDTH = 1400;  // your design width when in "items" mode
    const NORMAL_WIDTH = `${BASE_WIDTH}px`; // '1400px'

    let state = 'closed'; // 'closed' | 'search'

    gsap.set(panelMegaMenu, {
        visibility: 'hidden',
        opacity: 0
    });

    // --- HELPERS -------------------------------------------------------------

    function cleanupActive(el) {
        // return active node to normal flow
        gsap.set(el, { clearProps: 'position,left,top,width' });
    }

    function hideNode(el) {
        gsap.set(el, { display: 'none', visibility: 'hidden', opacity: 0 });
    }

    function showNode(el) {
        gsap.set(el, { display: 'block', visibility: 'visible', opacity: 1 });
    }

    function heightOf(el) {
        return el.scrollHeight; // number (px)
    }

    const headerOffsetHeight = headerWrapper.offsetHeight || 72;
    document.body.style.setProperty('--header-h', headerOffsetHeight + 'px');

    // --- TIMELINES -----------------------------------------------------------
    // Timeline to show/hide header
    const showTL = gsap.timeline({ paused: true })
        .to(headerWrapper, { y: 0,  duration: 0.22, ease: 'power2.out' });
    const hideTL = gsap.timeline({ paused: true })
        .to(headerWrapper, { y: -headerOffsetHeight, duration: 0.22, ease: 'power2.in' });

    // Config
    const MIN_Y = 8;        // ignore tiny scroll jitters
    const LOCK_TOP = 40;     // always show when near top
    let lastY = window.scrollY;
    let hidden = false;

    ScrollTrigger.create({
        start: 0,
        end: "max",
        invalidateOnRefresh: true,
        onUpdate(self) {
            const y = window.scrollY;
            const dy = y - lastY;

            lastY = y;

            // Always show near the very top
            if (y <= LOCK_TOP) {
                if (hidden) { showTL.restart(); hidden = false; }
                return;
            }

            // Ignore small deltas to prevent flicker
            if (Math.abs(dy) < MIN_Y) return;

            // Direction: 1 = scrolling down, -1 = up
            if (self.direction === 1) {
                // Down: hide
                if (!hidden) { hideTL.restart(); hidden = true; }
            } else {
                // Up: show
                if (hidden) { showTL.restart(); hidden = false; }
            }
        }
    });

    // Open from closed → chosenMode: 'items' | 'search'
    const openTl = gsap.timeline({ paused: true });

    // we’ll build the open each time (invalidate) by running a "prep" function:
    function openAs(chosenMode) {
        if (chosenMode === 'search') {
            body.classList.add('menu-open-search');
            body.classList.remove('menu-open-items');
            showNode(searchInput);

            focusSearchInput()
        } else {
            body.classList.add('menu-open-items');
            body.classList.remove('menu-open-search');

            hideNode(searchInput);
        }


        // 40 is the manual padding we give
        const manualPadding = 30;
        // add some extra spacing
        const leftForInput = btnOpenSearch.offsetWidth + manualPadding + 8;
        const widthForInput = dimMenu.offsetWidth - (leftForInput + manualPadding);
        const HeightForInput = dimMenu.offsetHeight - 18;

        // Animation timeline
        openTl.clear().add([
            // reveal panel
            gsap.set(panelMegaMenu, { display: 'flex', visibility: 'visible' }),
            gsap.to(panelMegaMenu, { opacity: 1, duration: 0.2, ease: 'power2.out' }, 0),

            gsap.to(body, { "--dg-before-visible": "visible", "--dg-blur": "blur(8px)", duration: 0.2}, 0),

            gsap.set(searchElementorContainer, {
                display: 'flex',
                left: `${leftForInput}px`,
                height: `${HeightForInput}px`,
            }),

            gsap.to(searchElementorContainer, {
                width: `${widthForInput}px`,
                opacity: 1,

                duration: 0.28,
                ease: 'power2.out'
            }),

            // width/height to the target of chosen content
            gsap.to(panelMegaMenu, { height: '50vh', duration: 0.45, ease: 'power2.out' }, 0),

        ]).add(() => state = chosenMode);

        openTl.play(0);
    }

    function closePanel() {
        if (state === 'closed') return;

        const closeTl = gsap.timeline();
        closeTl
            .to(panelMegaMenu, {
                height: 0,
                opacity: 0,

                duration: 0.22,
                ease: 'power2.in'
            }, 0)
            .to(searchElementorContainer, {
                width: '0',

                duration: 0.22,
                ease: 'power2.in'
            }, 0.1)
            .to(searchElementorContainer, {
                opacity: 0,

                duration: 0.22,
                ease: 'power2.in'
            }, 0.117)
            .to(body, { "--dg-before-visible": "hidden", "--dg-blur": " ", duration: 0.2}, 0)
            .set([panelMegaMenu, searchElementorContainer], { display: 'none', opacity: 0, visibility: 'hidden' }, 0.8)
            .add(() => { state = 'closed'; });

        body.classList.remove('menu-open-items', 'menu-open-search');
    }

    function getTargetWidth(chosenMode) {
        if (window.outerWidth < BASE_WIDTH) {
            return `${window.outerWidth - 16}px`;
        }

        return (chosenMode === 'search') ? window.outerWidth + 'px' : NORMAL_WIDTH
    }

    function focusSearchInput() {
        if(searchInput) {
            setTimeout(() => {
                const input = searchInput.querySelector('input[type="search"], .ais-SearchBox-input');
                if (input) input.focus();
            }, 10);
        }
    }

    // --- HOOK UP BUTTONS -----------------------------------------------------

    if (btnOpenSearch) {
        btnOpenSearch.addEventListener('click', e => {
            e.preventDefault();
            if (state === 'search') return;

            openAs('search');

            if (searchInput.value.trim()) {
                searchWordPress(input.value).then();
            } else {
                closePanel();
            }

            searchInput.focus();
        });
    }

    if (btnClose)      btnClose.addEventListener('click',      e => { e.preventDefault(); closePanel(); });

    // Click-outside to close (optional)
    document.addEventListener('click', (e) => {
        if (state === 'closed') return;
        if (e.target.closest('#dim-header-mega-menu') ||
            e.target.closest('#button-show-mega-menu') ||
            e.target.closest('#dim-nav-main-menu') ||
            e.target.closest('#dim-toggle-search-in-menu') ||
            e.target.closest('.js-search-results') ||
            e.target.closest('#dim-search-button-in-mega-menu') ||
            e.target.closest('#dim-menu-search-control') ||
            e.target.closest('#dim-mega-menu-search-container') ||
            e.target.closest('#dim-mega-menu-search-input-field'))
            return;
        closePanel();
    });

    // ---------------------------------- SEARCH Javascript code ----------------------------------

    const resultsInner = panelMegaMenu.querySelector('.js-search-results-inner');

    function renderResults(items) {
        if (!items.length) {
            resultsInner.innerHTML = '<div class="search-result-item">Geen resultaten gevonden.</div>';
            openAs('search');
            return;
        }

        resultsInner.innerHTML = items.map(item => `
            <div class="search-result-item">
                <a href="${item.url}">
                    <h4>${item.title}</h4>
                </a>
            </div>
        `).join('');

        openAs('search');
    }

    async function searchWordPress(query) {
        const q = query.trim();

        if (!q) {
            resultsInner.innerHTML = '';
            renderResults([]);
        }

        try {
            const response = await fetch(`/wp-json/wp/v2/search?search=${encodeURIComponent(q)}&per_page=10&subtype[]=page&subtype[]=product&subtype[]=handschoen`);
            const data = await response.json();

            const mapped = data.map(item => {
                const map = {
                    product: 'onze-producten',
                    handschoen: 'just-gloves'
                };

                const custom_url = map[item.subtype]
                    ? `${location.origin}/${map[item.subtype]}/#product=${item.id}`
                    : item.url;

                return {
                    title: item.title,
                    url: custom_url,
                    excerpt: item.type
                }
            });

            renderResults(mapped);
        } catch (error) {
            resultsInner.innerHTML = '<div class="search-result-item">Er ging iets mis.</div>';
        }
    }

    searchInput.addEventListener('input', (e) => {
        searchWordPress(e.target.value).then();
    });

    searchInput.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            closePanel();
            searchInput.blur();
        }
    });

});