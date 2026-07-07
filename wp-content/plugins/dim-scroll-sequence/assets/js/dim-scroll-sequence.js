(function () {
    // Prevent browser from restoring scroll position automatically
    if ('scrollRestoration' in history) {
        history.scrollRestoration = 'manual';
    }

    // Ensure plugin
    if (typeof ScrollTrigger !== 'undefined') {
        gsap.registerPlugin(ScrollTrigger);
    }

    const PX_PER_FRAME = 55;    // scroll distance per frame
    const MIN_SEQUENCE = 1200;   // minimum scroll for a sequence
    const CONTENT_HOLD = 900;   // non-sequence panel hold
    const PANEL_TRANSITION = 500; // slide up transition
    const PANEL_HOLD = 0.5; // slide up transition
    const HORIZONTAL_DURATION = 3;

    const initSequence = ($scope) => {
        const root = $scope[0].querySelector('.dim-sequence');
        let init = false;
        if (!root) return;

        // Avoid double-init
        if (root.dataset.dimInited === '1') return;
        root.dataset.dimInited = '1';

        const cfgRaw = root.getAttribute('data-dim-config') || '{}';
        let cfg = {};
        try { cfg = JSON.parse(cfgRaw); } catch (e) { cfg = {}; }

        const base_folder = cfg['baseUrl'];

        const pinEl = root.querySelector(".pin");
        const panels = Array.from(root.querySelectorAll('.content-wrapper'));

        if (!pinEl || !panels) return;

        gsap.set(panels, { yPercent: 100 });
        gsap.set(panels[0], { yPercent: 0, visibility: 'visible' });

        let baseEndTimeLine = 0;
        let extraHorizontalScroll = 0;
        let totalEndTimeLine = 0;

        // Cap DPR for memory/perf
        const getDpr = () => Math.min(Math.max(1, window.devicePixelRatio || 1), 2);

        // Resize canvas to container
        const resize = (sequenceItem) => {
            const canvasContainer = sequenceItem.elContainer.querySelector('.canvas-container');
            const canvas = canvasContainer.querySelector('.image-sequence-canvas');
            const rect = canvasContainer.getBoundingClientRect();
            const dpr = getDpr();

            canvas.width = Math.max(1, Math.floor(rect.width * dpr));
            canvas.height = Math.max(1, Math.floor(rect.height * dpr));
            canvas.style.width = rect.width + 'px';
            canvas.style.height = rect.height + 'px';

            // redraw current frame after resize
            if (sequenceItem.images[sequenceItem.frame]) {
                drawCover(sequenceItem.images[sequenceItem.frame], canvasContainer);
            }
        };

        // "cover" draw (like object-fit: cover)
        const drawCover = (img, canvasContainer) => {
            const canvas = canvasContainer.querySelector('.image-sequence-canvas');
            const context = canvas?.getContext?.("2d");

            if(!context) return;

            const cw = canvas.width, ch = canvas.height;
            const iw = img.naturalWidth || img.width;
            const ih = img.naturalHeight || img.height;
            if (!iw || !ih) return;

            const scale = Math.max(cw / iw, ch / ih);
            const w = iw * scale;
            const h = ih * scale;
            const x = (cw - w) / 2;
            const y = (ch - h) / 2;

            context.clearRect(0, 0, cw, ch);
            context.drawImage(img, x, y, w, h);
        };

        let sequenceContainer = [];

        cfg['timeLine'].forEach((item, index) => {
            const images = new Array(item.frames);
            const elContainer = root.querySelector(`.${item.canvasId}`);

            if (isMobileHorizontalWidth() && index === (cfg['timeLine'].length - 1)) {
                console.log('remove last panel');
                panels.pop();
                return;
            }

            if (isMobileHorizontalWidth()) {

            }

            sequenceContainer.push({ ...item, frame: 0, frameAmount: item.frames, images, elContainer  });
        });

        let activeSeq = sequenceContainer[0];

        baseEndTimeLine += sequenceContainer.length > 1 ? CONTENT_HOLD : 0

        sequenceContainer.forEach((seq, index) => {
            for (let i = 0; i < seq.frameAmount; i++) {
                preloadImages(seq, i);
            }

            const horizontal = getHorizontalData(seq.elContainer);

            if (horizontal) {
                gsap.set(horizontal.track, { x: 0 });
            }

            // calculate how much space we give for scrolling
            if (seq.content_type === "sequence") {
                const pxPerFrame = seq?.px_per_frame ?? PX_PER_FRAME
                const seqPx = Math.max(MIN_SEQUENCE, seq.frameAmount * pxPerFrame);
                baseEndTimeLine += seqPx;
            } else {
                baseEndTimeLine += CONTENT_HOLD;
            }

            // transition to next panel
            if (index < sequenceContainer.length - 1) {
                baseEndTimeLine += PANEL_TRANSITION;
            }
        });

        totalEndTimeLine = baseEndTimeLine;

        let ro = null;

        const animationTimeLine = gsap.timeline({
            scrollTrigger: {
                trigger: pinEl,      // ✅ scoped element (not ".canvas-container")
                start: "top top",
                end: '+=' + totalEndTimeLine,
                pin: pinEl, // ✅ scoped pin element (not ".pin")
                pinSpacing: true,
                invalidateOnRefresh: true,
                scrub: 1,
                anticipatePin: 1
            }
        });

        const maybeStart = (sequenceItem) => {
            // Wait for at least the first image to be ready so we can draw immediately
            if (!sequenceItem.images[0] || init) return;

            // Reasonable end distance based on frames
            let timeLineCursor = 0;
            const lastSequence = sequenceContainer[sequenceContainer.length - 1];
            const horizontalData = lastSequence ? getHorizontalData(lastSequence.elContainer) : null;

            extraHorizontalScroll = horizontalData ? Math.max(900, horizontalData.distance) : 0;
            totalEndTimeLine = baseEndTimeLine + extraHorizontalScroll;

            sequenceContainer.forEach((sequence, index) => {
                // Part 1: frames (use 0 -> urls.length-1 over most of the scroll)
                const nextPanel = panels[index + 1 ];
                const stepLabel = `step-${index}`;
                const panelTransition = 0.5;

                const stepTimeline = gsap.timeline();

                animationTimeLine.addLabel(stepLabel, timeLineCursor);

                if (sequence.content_type === 'sequence') {

                    stepTimeline.to(sequence, {
                        frame: sequence.frameAmount,
                        snap: "frame",
                        ease: "none",
                        duration: sequence?.duration ?? 2, // timeline normalized duration
                        onUpdate: () => {
                            activeSeq = sequence;
                            const f = Math.round(sequence.frame);

                            const img = sequence.images[f];
                            if (img) {
                                const canvasContainer = activeSeq.elContainer.querySelector('.canvas-container');
                                drawCover(img, canvasContainer);
                            }
                        }
                    });

                    const elementsToFadeInOut = sequence.elContainer.querySelectorAll('.dim-column-fade-in');

                    stepTimeline.to(elementsToFadeInOut, {
                        autoAlpha: 0,
                        duration: 0.18,
                        ease: 'power1.out'
                    }, 0.05);

                    stepTimeline.to({}, { duration: 0.2 });

                    // added some marge when trying to show next element
                    timeLineCursor += stepTimeline.duration();
                } else {
                    const localOffset = `${stepLabel}-=0.8`
                    const slideInUpDuration = fullSlideInUp(stepTimeline, sequence.elContainer, localOffset);

                    let fadeDelay = slideInUpDuration || 0.45;

                    const fadeInDuration = contentFadeIn(stepTimeline, sequence.elContainer, stepLabel, fadeDelay);

                    timeLineCursor += (fadeInDuration + slideInUpDuration);
                }

                const isLastPanel = index === sequenceContainer.length - 1;
                const horizontal = getHorizontalData(sequence.elContainer);

                if (isLastPanel && horizontal && horizontal.distance > 0) {
                    setHorizontalScrolling(stepTimeline, animationTimeLine, `step-${index + 1}`, horizontal,sequence.elContainer, timeLineCursor)
                }

                if (nextPanel) {
                    stepTimeline.to({}, { duration: PANEL_HOLD });

                    const stepDuration = stepTimeline.duration();
                    const overlay = panels[index].querySelector('.content-transition-overlay');

                    const leftCol = nextPanel.querySelector('.dim-panel-col-left');
                    const rightCol = nextPanel.querySelector('.dim-panel-col-right');
                    const isTwoColumn = leftCol && rightCol;

                    if (isTwoColumn) {
                        gsap.set(nextPanel, { yPercent: 0 });
                        gsap.set(leftCol, { xPercent: -100 });
                        gsap.set(rightCol, { xPercent: 100 });

                        stepTimeline.to(nextPanel, {
                            visibility: 'visible',
                        }, stepDuration);

                        stepTimeline.to(leftCol, {
                            xPercent: 0,
                            ease: "none",
                            duration: panelTransition,
                        }, stepDuration);

                        stepTimeline.to(rightCol, {
                            xPercent: 0,
                            ease: "none",
                            duration: panelTransition,
                        }, stepDuration);
                    } else {
                        stepTimeline.to(nextPanel, {
                            visibility: 'visible',
                            yPercent: 0,
                            ease: "none",
                            duration: panelTransition,
                        }, stepDuration);
                    }

                    if (overlay) {
                        stepTimeline.to(overlay, {
                            opacity: 0.8,
                            ease: "none",
                        }, stepDuration);
                    }

                    timeLineCursor += panelTransition + 0.4;
                }

                // add to master timeline
                animationTimeLine.add(stepTimeline, stepLabel);
            });

            ScrollTrigger.refresh(true);

            // keep canvas responsive
            let resizeRefreshTimer = null;

            ro = new ResizeObserver(() => {
                if (activeSeq) {
                    resize(activeSeq);
                }

                clearTimeout(resizeRefreshTimer);
                resizeRefreshTimer = setTimeout(() => {
                    ScrollTrigger.refresh();
                }, 120);
            });

            ro.observe(pinEl);

            // If Elementor re-renders this widget, clean up
            root.addEventListener('dim:destroy', () => {
                ro?.disconnect();
                animationTimeLine?.scrollTrigger?.kill();
                animationTimeLine?.kill();
                root.dataset.dimInited = '0';
            }, { once: true });

            init = true;
        };

        // preload all frames
        function preloadImages (sequence, i) {
            const img = new Image();
            img.decoding = "async";
            img.loading = "eager";

            img.onload = () => {
                sequence.images[i] = img;

                if (i === 0) {
                    initCanvas(sequence);
                }

                // Start as soon as first frame is loaded
                if (i === 0 && sequence === sequenceContainer[0]) {
                    document.body.classList.add('dim-sequence-fade');

                    setTimeout(() => {
                        document.body.classList.remove('dim-sequence-fade');
                        document.body.classList.add('dim-sequence-loaded');
                    }, 200)
                    maybeStart(sequence);
                }
            };

            img.onerror = () => {
                sequence.images[i] = null;
                throw Error('The image can\'t be loaded');
            };

            img.src = `${base_folder}/${sequence.slug}/${String(i + 1).padStart(3, '0')}.${sequence.extension}`;
        }

        function initCanvas(sequence) {
            const canvasContainer = sequence.elContainer.querySelector('.canvas-container');

            resize(sequence);
            drawCover(sequence.images[0], canvasContainer);

            // Ensure we start at frame 0 visually
            sequence.frame = 0;
            drawCover(sequence.images[0], canvasContainer);
        }
    };

    function setHorizontalScrolling(stepTimeline, mainTimeLine, stepLabel, horizontal, element, cursor) {
        const horizontalTL = gsap.timeline();
        stepTimeline.to({}, { duration: 0.2 });

        mainTimeLine.addLabel(stepLabel, cursor);

        horizontalTL.to(horizontal.track, {
            x: () => -getHorizontalData(element).distance,
            ease: "none",
            duration: HORIZONTAL_DURATION,
        }, 0);

        const imagesPanel = horizontal.track.querySelector('.images-panel');
        const elementsToFollowRight = imagesPanel.querySelectorAll('.side-line');
        const elementsToFollowLeft = imagesPanel.querySelectorAll('.main-line');
        const logoDzs = horizontal.track.querySelector('.logo-dzs');

        horizontalTL.to(elementsToFollowRight, {
            x: () => (horizontal.distance * 0.025),
            ease: "none",
            duration: HORIZONTAL_DURATION,
        }, 0);

        horizontalTL.to(elementsToFollowLeft, {
            x: () => -(horizontal.distance * 0.015),
            ease: "none",
            duration: HORIZONTAL_DURATION,
        }, 0);

        const logoStart = HORIZONTAL_DURATION * 0.55;
        const logoDuration = HORIZONTAL_DURATION - logoStart;

        horizontalTL.to(logoDzs, {
            rotation: 0,
            ease: "none",
            duration: logoDuration,
        }, logoStart);

        const finalPanel = element.querySelector('.final-panel-zoom-in');

        finalZoomInPanel(horizontalTL, finalPanel);
        contentFadeIn(horizontalTL, finalPanel, null, null, '.dim-column-fade-in-last');

        horizontalTL.to({}, { duration: PANEL_HOLD });

        stepTimeline.add(horizontalTL, stepTimeline.duration());

        return stepTimeline;
    }

    function getHorizontalData(panel) {
        const track = panel.querySelector('.dim-horizontal-track');

        if (!track) return null;

        const textContent = track.querySelector('.text-content');

        const distance = Math.max(0, (track.scrollWidth - panel.offsetWidth) + textContent.offsetWidth);

        return { panel, track, distance };
    }

    function fullSlideInUp(tl, element) {
        const elementsToSlideUp = element.querySelectorAll('.full-slide-up');

        if (!elementsToSlideUp.length) return 0;

        const slideTimeLine = gsap.timeline();

        gsap.set(elementsToSlideUp, { y: '50vh' });

        // Part 2: slide-in the content in the current panel
        slideTimeLine.to(elementsToSlideUp, {
            y: 0,
            ease: "none",
            duration: 0.4
        });

        tl.add(slideTimeLine);

        return slideTimeLine.duration() || 0;
    }

    function contentFadeIn(tl, element, labelTiming = null, delay = null, selector = '.dim-column-fade-in') {
        const elementsToFadeIn = element.querySelectorAll(selector);

        if (!elementsToFadeIn.length) return 0;

        const fadeInTimeLine = gsap.timeline();
        gsap.set(elementsToFadeIn, {opacity: 0, y: 20});

        fadeInTimeLine.to(elementsToFadeIn, {
            y: 0,
            opacity: 1,
            ease: "none",
            duration: 0.18
        });

        tl.add(fadeInTimeLine);

        return fadeInTimeLine.duration() || 0;
    }

    function finalZoomInPanel(horizontalTL, finalPanel) {
        const video = finalPanel.querySelectorAll('.final-panel-zoom-in .last-video');

        horizontalTL.to(finalPanel, {
            visibility: 'visible',
            ease: "none",
        }, 0);

        horizontalTL.to(video, {
            display: 'block',
            scale: 1,
            ease: "none",
            duration: 0.8,
        }, 2.2);

        horizontalTL.to(finalPanel, {
            zIndex: 1,
        }, 2.7);

        return horizontalTL.duration() || 0;
    }

    function animateWordsUpAndStickLastWord(tl, elContainer, timeLineFrame) {
        const wordTl = gsap.timeline();

        const container = elContainer.querySelector('.dim-animation-word-stack');
        if (!container) return;

        const words = Array.from(container.querySelectorAll('.dim-animation-word'));
        if (!words.length) return;

        const firstWordHeight = words[0].getBoundingClientRect().height;
        const lineStep = firstWordHeight * 1.05; // spacing between stacked words

        // start all words below
        gsap.set(words, {
            y: 80,
            opacity: 0
        });

        words.forEach((word, index) => {
            const isLast = index === words.length - 1;

            // when this word enters
            const enterAt = index * 0.18;

            // all words up to current one
            const visibleWords = words.slice(0, index + 1);
            const stackWords = isLast ? visibleWords.slice(0, -1) : visibleWords;

            // each visible word gets its stacked slot
            // newest word at bottom, oldest word highest
            wordTl.to(stackWords, {
                y: (i, target) => {
                    const wordIndex = visibleWords.indexOf(target);
                    const stackIndexFromBottom = visibleWords.length - 1 - wordIndex;

                    return -(stackIndexFromBottom * lineStep);
                },
                opacity: 1,
                ease: "none",
                duration: 0.22
            }, enterAt);

            // last word stays visible and moves slightly left
            if (isLast) {
                wordTl.to(word, {
                    y:-firstWordHeight,
                    opacity: 1,
                    ease: "none",
                    duration: 0.22
                }, `${enterAt}+=0.2`);

                // 2️⃣ AFTER that, slide slightly left
                wordTl.to(word, {
                    xPercent: -60,
                    ease: "none",
                    duration: 0.2
                });
            }
        });

        const totalStackHeight = (words.length - 1) * lineStep;
        const extraExitDistance = lineStep * 1.2; // little more so top word really leaves

        wordTl.to(words.slice(0, -1) , {
            y: `-=${totalStackHeight + extraExitDistance}`,
            opacity: 0,
            ease: "none",
            duration: 0.35
        }, words.length * 0.18);

        tl.add(wordTl, timeLineFrame);

        return wordTl.duration();
    }

    function destroySequence($scope) {
        const root = $scope[0].querySelector('.dim-sequence');
        if (!root) return;

        // kill only triggers inside this widget
        ScrollTrigger.getAll().forEach(st => {
            if (root.contains(st.trigger)) {
                st.kill();
            }
        });

        gsap.killTweensOf(root.querySelectorAll("*"));

        // reset styles GSAP touched
        gsap.set(root.querySelectorAll("*"), { clearProps: "all" });

        root.dataset.dimInited = '0';
    }

    function isMobileHorizontalWidth() {
        return window.matchMedia("(max-width: 767px)").matches;
    }

    // Elementor frontend hook
    // Use element_ready so it works on frontend + editor.
    jQuery(window).on('elementor/frontend/init', function () {
        elementorFrontend.hooks.addAction(
            'frontend/element_ready/dim_scroll_sequence.default',
            initSequence
        );
    });
})();