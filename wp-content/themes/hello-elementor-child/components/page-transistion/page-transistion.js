(function(){
    if (typeof gsap === 'undefined') return;

    //Grid to pdp script
    document.addEventListener('click', (e) => {
        const link = e.target.closest('a.product-card');
        if (!link) return;

        // only plain left-clicks
        if (e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;

        const img  = link.querySelector('.product-card__img');
        const href = link.getAttribute('href');
        if (!img || !href) return;

        e.preventDefault();

        // 1) capture rect + src
        const r = img.getBoundingClientRect();
        const data = {
            href,
            src: img.currentSrc || img.src,
            rect: { left:r.left, top:r.top, width:r.width, height:r.height },
            vw: window.innerWidth, vh: window.innerHeight, ts: Date.now()
        };
        sessionStorage.setItem('dimTransition', JSON.stringify(data));

        // 2) optional: brief “micro-zoom” on the grid to feel responsive
        const ghost = img.cloneNode(true);
        ghost.className = 'dim-ghost';
        ghost.style.left   = r.left + 'px';
        ghost.style.top    = r.top + 'px';
        ghost.style.width  = r.width + 'px';
        ghost.style.height = r.height + 'px';
        document.body.appendChild(ghost);

        const tl = gsap.timeline({
            defaults:{ ease:'power2.out' },
            onComplete(){ window.location.assign(href); }
        });

        tl
            .to(ghost, { duration:0.16, scale:1.06 }, 0)
            .to('#dim-products-fsearch', { opacity: 0, duration: 0.3 }, 0.2)

    }, { capture:true });

    // nice-to-have: prefetch detail on hover
    document.addEventListener('mouseover', (e) => {
        const a = e.target.closest('a.product-card');
        if (!a || !a.href) return;
        const l = document.createElement('link');
        l.rel='prefetch';
        l.href=a.href;
        document.head.appendChild(l);
    });

    //pdp script
    document.addEventListener('DOMContentLoaded', () => {
        const raw = sessionStorage.getItem('dimTransition');
        const hero = document.querySelector('.product-hero__img');

        if ( !raw && !hero ) {
            return
        } else if ( !raw && hero ) {
            gsap.to(hero, { opacity: 1, duration: 0.18, ease: 'power1.out' });
        }

        sessionStorage.removeItem('dimTransition');

        let data; try { data = JSON.parse(raw); } catch { return; }
        if (!hero || !data || !data.rect) return;

        // guard against big viewport change during nav
        if (Math.abs((data.vw||0) - window.innerWidth) > 60) {
            gsap.to(hero, { opacity:1, duration:0.12 }); return;
        }

        // 1) build ghost at OLD screen coordinates (from grid)
        const g = new Image();
        g.src = data.src;
        g.className = 'dim-ghost';
        g.style.left   = data.rect.left + 'px';
        g.style.top    = data.rect.top + 'px';
        g.style.width  = data.rect.width + 'px';
        g.style.height = data.rect.height + 'px';
        document.body.appendChild(g);

        // 2) measure destination hero rect (1152px centered container / 350x350 image)
        const hr = hero.getBoundingClientRect();

        // 3) compute transform FROM old rect TO hero rect (exact fit)
        const scaleX = hr.width  / data.rect.width;
        const scaleY = hr.height / data.rect.height;
        const tx = (hr.left + hr.width/2)  - (data.rect.left + data.rect.width/2);
        const ty = (hr.top  + hr.height/2) - (data.rect.top  + data.rect.height/2);

        // 4) animate ghost into place, then reveal real hero
        gsap.set(hero, { opacity:0 });
        gsap.timeline({
            defaults:{ ease:'power2.out' },
            onComplete(){
                g.remove();
                gsap.to(hero, { opacity:1, duration:0.18, ease:'power1.out' });
            }
        }).to(g, { duration:0.32, x:tx, y:ty, scaleX, scaleY });
    });
})();