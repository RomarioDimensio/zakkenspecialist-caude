/* ---------------------------------------------------------------------------
 * Lees meer / lees minder — voor elk element met class "readmore".
 *
 * Onder 576px wordt de tekst op een WOORDGRENS ingekort, met direct achter
 * het laatste woord: "..... lees meer" — op dezelfde regel, zoals in het
 * ontwerp. Geen max-height of overlay: wat niet getoond wordt, staat echt
 * verborgen (display:none), dus de bloklengte klopt vanzelf.
 *
 * Hoe de kniplijn wordt bepaald:
 *   1. hoogtebudget = --readmore-hoogte op het element (standaard 400px);
 *   2. per tekstblok (p, li, kop) weten we waar elke regel eindigt; de
 *      laatste regelgrens die binnen het budget valt is de kniplijn;
 *   3. binnen dat tekstblok zoeken we (binair, via Range-metingen) het
 *      laatste woord dat vóór die lijn eindigt, knippen daar, en halen er
 *      desnoods nog een paar woorden af tot "..... lees meer" op dezelfde
 *      regel past.
 *
 * Uitklappen toont alles, met aan het einde een inline "lees minder".
 * Inklappen meet en knipt opnieuw (fonts kunnen intussen geladen zijn) en
 * scrolt terug naar de bovenkant van het blok. Bij resize of het wisselen
 * van schermbreedte wordt de knip ook opnieuw gelegd.
 *
 * Budget per element aanpassen: zet in Elementor --readmore-hoogte: 300px;
 * De knopjes stijl je met .rm-leesmeer / .rm-leesminder (staan in de CSS).
 * ------------------------------------------------------------------------- */
(function () {
    'use strict';

    const boxes = document.querySelectorAll('.readmore');
    if (!boxes.length) return;

    const mq = window.matchMedia('(max-width: 576px)');
    const rustigAan = window.matchMedia('(prefers-reduced-motion: reduce)');
    const TEKSTBLOKKEN = 'p, li, h1, h2, h3, h4, blockquote';

    boxes.forEach((box) => {
        if (box.dataset.dimReadmore) return;
        box.dataset.dimReadmore = '1';

        // origineel van het geknipte tekstblok, zodat we altijd schoon
        // opnieuw kunnen beginnen
        let knipBlok = null;
        let knipBlokHtml = '';

        /* n-de teken binnen een element -> {tekstnode, offset} (voor Range) */
        function tekenPositie(el, n) {
            const loper = document.createTreeWalker(el, NodeFilter.SHOW_TEXT);
            let som = 0, node;
            while ((node = loper.nextNode())) {
                const len = node.data.length;
                if (som + len >= n) return { node, offset: n - som };
                som += len;
            }
            return node ? { node, offset: node.data.length } : null;
        }

        function herstel() {
            box.querySelectorAll('.rm-suffix, .rm-leesminder-houder').forEach((el) => el.remove());
            if (knipBlok && knipBlokHtml) knipBlok.innerHTML = knipBlokHtml;
            knipBlok = null;
            knipBlokHtml = '';
            box.querySelectorAll('.rm-verborgen').forEach((el) => el.classList.remove('rm-verborgen'));
            box.classList.remove('rm-dicht', 'is-open');
        }

        function knip() {
            // Meten met de handrem op de animaties: Elementor-interactions
            // zetten transforms/opacity op de tekstblokken (entrance bij
            // scroll) en dan kloppen de regelposities niet. De class rm-meet
            // (zie CSS) zet dat tijdelijk recht; na afloop pakt de animatie
            // zijn eigen inline-styles gewoon weer op.
            box.classList.add('rm-meet');
            try {
                knipEcht();
            } finally {
                box.classList.remove('rm-meet');
            }
        }

        function knipEcht() {
            const budget = parseFloat(getComputedStyle(box).getPropertyValue('--readmore-hoogte')) || 400;
            const boxTop = box.getBoundingClientRect().top;
            const grensMax = boxTop + budget;

            if (box.scrollHeight <= budget + 24) return;   // past (vrijwel) helemaal: niets doen

            // 1. kniplijn: de laatste volledige tekstregel binnen het budget
            const blokken = [...box.querySelectorAll(TEKSTBLOKKEN)];
            let doel = null, grens = 0;
            blokken.forEach((el) => {
                const r = el.getBoundingClientRect();
                if (!r.height) return;
                const lh = parseFloat(getComputedStyle(el).lineHeight) || 27;
                const regels = Math.max(1, Math.round(r.height / lh));
                for (let k = 1; k <= regels; k++) {
                    const g = r.top + k * lh;
                    if (g <= grensMax + 1 && g > grens) { grens = g; doel = el; }
                }
            });
            if (!doel) return;

            // 2. laatste teken dat vóór de kniplijn eindigt (binair zoeken)
            const totaal = doel.textContent.length;
            const past = (n) => {
                const pos = tekenPositie(doel, n);
                if (!pos) return false;
                const r = document.createRange();
                r.setStart(pos.node, 0);
                r.setEnd(pos.node, pos.offset);
                const rect = r.getBoundingClientRect();
                return rect.bottom <= grens + 2;
            };
            let lo = 1, hi = totaal, beste = totaal;
            if (!past(totaal)) {
                while (lo <= hi) {
                    const mid = (lo + hi) >> 1;
                    if (past(mid)) { beste = mid; lo = mid + 1; }
                    else { hi = mid - 1; }
                }
            }

            // terug naar de laatste woordgrens
            const tekst = doel.textContent;
            while (beste > 0 && !/\s/.test(tekst[beste - 1])) beste--;
            while (beste > 0 && /\s/.test(tekst[beste - 1])) beste--;
            if (beste < 20) return;   // onzinnig korte knip: dan maar niet knippen

            // 3. knippen: de rest van dit blok in een verborgen span
            knipBlok = doel;
            knipBlokHtml = doel.innerHTML;
            const pos = tekenPositie(doel, beste);
            const rest = document.createRange();
            rest.setStart(pos.node, pos.offset);
            rest.setEnd(doel, doel.childNodes.length);
            const restSpan = document.createElement('span');
            restSpan.className = 'rm-verborgen rm-rest';
            restSpan.appendChild(rest.extractContents());

            const suffix = document.createElement('span');
            suffix.className = 'rm-suffix';
            suffix.innerHTML = ' ..... <button type="button" class="rm-leesmeer" aria-expanded="false">lees meer</button>';

            doel.appendChild(suffix);
            doel.appendChild(restSpan);

            // alles ná het geknipte blok ook verbergen (volgende alinea's,
            // afbeeldingen, noem maar op) — op containerniveau
            let tak = doel;
            while (tak && tak !== box) {
                let buur = tak.nextElementSibling;
                while (buur) { buur.classList.add('rm-verborgen'); buur = buur.nextElementSibling; }
                tak = tak.parentElement;
            }

            // 4. past "..... lees meer" niet meer op de regel? dan nog een
            //    paar woorden inleveren tot het wel zo is
            let pogingen = 16;
            while (suffix.getBoundingClientRect().bottom > grens + 2 && pogingen--) {
                const loper = document.createTreeWalker(doel, NodeFilter.SHOW_TEXT);
                let laatste = null, node;
                while ((node = loper.nextNode())) {
                    if (restSpan.contains(node) || suffix.contains(node)) continue;
                    if (node.data.trim()) laatste = node;
                }
                if (!laatste) break;
                laatste.data = laatste.data.replace(/\s*\S+\s*$/, '');
            }

            // "lees minder" voor de uitgeklapte stand: inline, aan het einde
            const minder = document.createElement('span');
            minder.className = 'rm-leesminder-houder';
            minder.innerHTML = ' <button type="button" class="rm-leesminder" aria-expanded="true">lees minder</button>';
            const laatsteBlok = blokken[blokken.length - 1];
            (laatsteBlok || box).appendChild(minder);

            box.classList.add('rm-dicht');
        }

        const check = () => {
            if (box.classList.contains('is-open')) return;   // open laten staan
            herstel();
            if (mq.matches) knip();
        };

        box.addEventListener('click', (e) => {
            if (e.target.closest('.rm-leesmeer')) {
                box.classList.remove('rm-dicht');
                box.classList.add('is-open');
            } else if (e.target.closest('.rm-leesminder')) {
                box.classList.remove('is-open');
                check();                                      // opnieuw meten en knippen
                box.scrollIntoView({ behavior: rustigAan.matches ? 'auto' : 'smooth', block: 'start' });
            }
        });

        check();
        mq.addEventListener('change', check);
        if (document.readyState === 'complete') check();
        else window.addEventListener('load', check, { once: true });

        // andere breedte = andere regelafbraak: opnieuw knippen (rustig aan)
        let wacht = null;
        let vorigeBreedte = window.innerWidth;
        window.addEventListener('resize', () => {
            if (window.innerWidth === vorigeBreedte) return;
            vorigeBreedte = window.innerWidth;
            clearTimeout(wacht);
            wacht = setTimeout(check, 200);
        });
    });
})();
