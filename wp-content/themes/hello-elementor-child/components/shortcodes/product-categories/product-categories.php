<?php

function get_bag_categories() {
    $taxonomyName = 'category_product';
    $baseLinkUrl = "/?$taxonomyName";

    $terms = get_terms([
        'taxonomy' => $taxonomyName,
        'hide_empty' => false,
    ]);
    ob_start();
    ?>
    <style>
        .dg-categorie-grid {
            justify-content: center;
            display: flex;
            flex-wrap: wrap;
        }
        .dg-categorie-grid .categorie-item {
            position: relative;
            display: flex;
            flex-direction: column;
            align-items: center;
            width: 150px;
            padding: 8px 16px;
            border-radius: 10px;

            opacity: 0;
            transform: translateY(20px);
        }
        .dg-categorie-grid .categorie-item:after {
            content: " ";
            position: absolute;
            height: 70%;
            border-right: solid 1px #f7f7f7;
            right: 0;
            top: 50%;
            transform: translateY(-50%);
        }
        .dg-categorie-grid .categorie-item:last-child:after {
            content: none;
        }


        .dg-categorie-grid .categorie-item.in-view {
            animation: fadeInUp 0.6s forwards;
        }

        @keyframes fadeInUp {
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .dg-categorie-grid .product-category-image {
            width: 75px;
            height: auto;
        }

        .dg-categorie-grid .dg-button-link {
            position: relative;
            overflow: hidden;
            display: flex;
            padding: 8px 16px;
            font-size: 14px;
            /*box-shadow: 9px 7px 11px rgba(0, 0, 0, 0.21);*/
            z-index: 2;
        }

        .dg-categorie-grid .dg-clickable-link-container {
            position: absolute;
            top:0;
            left: 0;
            right: 0;
            bottom: 0;
        }
        .ripple {
            position: absolute;
            border-radius: 50%;
            background: rgba(255,255,255,0.4); /* or rgba(0,0,0,0.2) for dark ripple */
            pointer-events: none;
            width: 20px; height: 20px;
            transform: scale(0);
            animation: ripple-effect 0.6s linear;
            z-index: 10;
        }

        @keyframes ripple-effect {
            to {
                opacity: 0;
                transform: scale(6);
            }
        }
    </style>
    <?php

    echo '<div class="dg-categorie-grid">';
    foreach ($terms as $term) {
        $image = get_field('category_featured_image', $taxonomyName . '_' . $term->term_id);
        $categoryLinkUrl = "$baseLinkUrl=$term->slug";

        if ($image) {
            echo '<div class="categorie-item">';
            echo '<a class="dg-clickable-link-container" href="'. $categoryLinkUrl .'"></a>';
            echo '<img class="product-category-image" src=' . esc_url($image['url']) .' alt='. $term->slug .'>';
            echo '<a class="dg-button-link" href="'. $categoryLinkUrl .'"><span class="dg-custom-button">' . esc_html($term->name) . '</span></a>';
        }

        echo '</div>';
    }
    echo '</div>';
    ?>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const gridItems = document.querySelectorAll('.dg-categorie-grid .categorie-item');

            const observer = new IntersectionObserver((entries, observer) => {
                entries.forEach((entry, idx) => {
                    if (entry.isIntersecting) {
                        entry.target.style.animationDelay = `${0.2 + idx * 0.2}s`;
                        entry.target.classList.add('in-view');
                        observer.unobserve(entry.target);
                    }
                });
            }, { threshold: 0.1 });

            gridItems.forEach(item => {
                observer.observe(item);
                item.addEventListener('click', rippleEffectOnClick);

                const tl = gsap.timeline({ paused: true });

                // animation when hovered
                tl.to(item, {
                    y: -8,                // lift up
                    scale: 1.04,          // grow a bit
                    boxShadow: "0px 10px 50px rgba(0 0 0 0.07)",
                    duration: 0.25,
                    ease: "power2.out"
                });

                // play on hover in, reverse on hover out
                item.addEventListener('mouseenter', () => tl.play());
                item.addEventListener('mouseleave', () => tl.reverse());
            });

            const buttons = document.querySelectorAll('.dg-categorie-grid .dg-button-link');
            buttons.forEach(item => item.addEventListener('click', rippleEffectOnClick));

            function rippleEffectOnClick(e) {
                e.stopPropagation();

                // Remove old ripple if still there
                const oldRipple = this.querySelector('.ripple');
                if (oldRipple) oldRipple.remove();

                // Calculate position of click
                const rect = this.getBoundingClientRect();
                const ripple = document.createElement('span');
                ripple.className = 'ripple';
                ripple.style.left = (e.clientX - rect.left) + 'px';
                ripple.style.top = (e.clientY - rect.top) + 'px';

                // Optional: Adjust size for bigger buttons
                const size = Math.max(this.offsetWidth, this.offsetHeight) * 1.2;
                ripple.style.width = ripple.style.height = size + 'px';
                ripple.style.marginLeft = (-size/2) + 'px';
                ripple.style.marginTop = (-size/2) + 'px';

                this.appendChild(ripple);

                // Remove after animation
                ripple.addEventListener('animationend', () => ripple.remove());
            }
        });

    </script>

    <?php
    return ob_get_clean();

}
add_shortcode('bag_categories', 'get_bag_categories');