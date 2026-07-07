<?php

use Elementor\Widget_Base;

if (!defined('ABSPATH')) exit;

class Dim_Scroll_Sequence_Widget extends Widget_Base {

    public function get_name(): string
    {
        return 'dim_scroll_sequence';
    }

    public function get_title(): ?string
    {
        return __('DIM Scroll Sequence', 'dim');
    }

    public function get_icon(): string
    {
        return 'eicon-slides';
    }

    public function get_categories(): array
    {
        return ['general'];
    }

    public function get_script_depends(): array
    {
        return ['dim-scroll-sequence'];
    }

    public function get_style_depends(): array
    {
        return ['dim-scroll-sequence'];
    }

    private function dim_list_sequence_folders(): array {
        $upload = wp_upload_dir();
        $base = trailingslashit($upload['basedir']) . 'sequences';

        if (!is_dir($base)) {
            return [];
        }

        $folders = [];
        foreach (scandir($base) as $name) {
            if ($name === '.' || $name === '..') continue;
            $path = $base . DIRECTORY_SEPARATOR . $name;
            if (is_dir($path)) {
                $folders[$name] = $name; // value => label
            }
        }

        ksort($folders);
        return $folders;
    }

    protected function register_controls(): void
    {

        $this->start_controls_section(
            'section_content',
            [
                'label' => __('Content', 'dim'),
                'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
            ]
        );

        $repeater = new \Elementor\Repeater();

        $repeater->add_control(
            'content_type',
            [
                'label' => __('Type', 'dim'),
                'type' => \Elementor\Controls_Manager::SELECT,
                'options' => [
                        'sequence' => __('Image sequence', 'dim'),
                        'video'    => __('Video background', 'dim'),
                        'image'    => __('Image background', 'dim'),
                    ],
                'default' => ['sequence'],
            ]
        );

        $repeater->add_control(
            'sequence',
            [
                'label' => __('De afbeelding sequence', 'dim'),
                'type' => \Elementor\Controls_Manager::SELECT,
                'options' => $this->dim_list_sequence_folders(),
                'default' => [],
                'condition' => ['content_type' => 'sequence'],
            ]
        );

        $repeater->add_control(
            'frames',
            [
                'label' => __('Hoeveel frames', 'dim'),
                'type' => \Elementor\Controls_Manager::NUMBER,
                'default' => 150,
                'condition' => ['content_type' => 'sequence'],
            ]
        );

        $repeater->add_control(
            'extension',
            [
                'label' => __('image extensie', 'dim'),
                'type' => \Elementor\Controls_Manager::SELECT,
                'options' => [
                    'webp' => __('webp', 'dim'),
                    'jpg' => __('jpg', 'dim'),
                    'avif' => __('avif', 'dim'),
                ],
                'default' => ['webp'],
                'condition' => ['content_type' => 'sequence'],
            ]
        );

        $repeater->add_control(
            'px_per_frame',
            [
                'label' => __('Scroll amount per frame', 'dim'),
                'type' => \Elementor\Controls_Manager::NUMBER,
                'default' => [55],
                'condition' => ['content_type' => 'sequence'],
            ]
        );

        $repeater->add_control(
            'sequence_duration',
            [
                'label' => __('Duration sequence timeline', 'dim'),
                'type' => \Elementor\Controls_Manager::NUMBER,
                'default' => [2],
                'condition' => ['content_type' => 'sequence'],
            ]
        );

        $repeater->add_control(
            'video',
            [
                'label' => __('Video url', 'dim'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => [],
                'condition' => ['content_type' => 'video'],
            ]
        );

        $repeater->add_control(
            'image',
            [
                'label' => __('image', 'dim'),
                'type' => \Elementor\Controls_Manager::MEDIA,
                'default' => [],
                'condition' => ['content_type' => 'image'],
            ]
        );

        $repeater->add_control(
            'title',
            [
                'label' => __('Titel', 'dim'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => '',
            ]
        );

        $repeater->add_control(
            'template_id',
            [
                'label' => __( 'Choose Template', 'text-domain' ),
                'type' => \Elementor\Controls_Manager::SELECT,
                'default' => '',
                // Fetch templates dynamically
                'options' => $this->get_available_templates(),
            ]
        );

        $this->add_control(
        'steps',
            [
                'label' => __('Steps', 'dim'),
                'type' => \Elementor\Controls_Manager::REPEATER,
                'fields' => $repeater->get_controls(),
                'default' => [
                        ['content_type' => 'sequence'],
                        ['content_type' => 'content'],
                        ['content_type' => 'sequence'],
                ],
                'title_field' => '{{{ content_type }}}',
            ]
        );

        $this->end_controls_section();
    }

    private function get_available_templates(): array {
        $options = [];

        $templates = get_posts([
            'post_type'      => 'elementor_library',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'orderby'        => 'title',
            'order'          => 'ASC',
            'meta_query'     => [
                [
                    'key' => '_elementor_template_type',
                    'value' => ['section'],
                    'compare' => 'IN',
                ]
            ]

        ]);

        foreach ($templates as $template) {
            $options[$template->ID] = $template->post_title . ' (#' . $template->ID . ')';
        }

        return $options;
    }

    protected function render(): void
    {
        $upload = wp_upload_dir();
        $settings = $this->get_settings_for_display();

        $timeLine = [];
        foreach ($settings['steps'] as $index => $step) {
            $timeLine[] = [
                'slug'         => $step['sequence'],
                'frames'       => $step['frames'],
                'extension'    => $step['extension'],
                'video'        => $step['video'],
                'image'        => $step['image'],
                'content_type' => $step['content_type'],
                'canvasId'     => 'dim-canvas-' . ($index + 1) . '-' . $this->get_id(),
                'title'        => $step['title'],
                'description'  => $step['description'],
                'template_id'  => $step['template_id'],
                'last_panel'   => $index === count($settings['steps']) - 1,
                'duration'     => $step['sequence_duration'],
                'px_per_frame'     => $step['px_per_frame'],
            ];
        }

        $id = 'dim-seq-' . $this->get_id();

        $config = [
            'id' => $id,
            'baseUrl' => trailingslashit($upload['baseurl']) . 'sequences',
            'height' => (string) $settings['height'],
            'pin' => ($settings['pin'] === 'yes'),
            'scrub' => (float) $settings['scrub'],
            'slideNext' => ($settings['slide_next'] === 'yes'),
            'timeLine' => $timeLine
        ];


        ?>

        <div class="dim-sequence-timeline-container">
            <div id="<?php echo esc_attr($id); ?>"
                 class="dim-sequence"
                 data-dim-config="<?php echo esc_attr(wp_json_encode($config)); ?>">
                <div class="pin">
                    <div class="sequence-content-wrapper">
                        <?php foreach ($timeLine as $step ): ?>
                            <div class="wrapper content-wrapper <?= $step['canvasId'] ?> <?= $step['last_panel'] ? 'final-panel' : '' ?> ">
                                <div class="content-transition-overlay"></div>
                                <?php if ($step['content_type'] === 'sequence'): ?>
                                    <div class="canvas-container">
                                        <canvas class="image-sequence-canvas"></canvas>
                                    </div>

                                    <div class="sequence-content-container dim-next-row">
                                        <?php
                                            $template_id = (int) ($step['template_id'] ?? 0);

                                            if ($template_id) {
                                                echo \Elementor\Plugin::$instance->frontend->get_builder_content_for_display($template_id, true);
                                            }
                                        ?>
                                    </div>
                                <?php elseif (!$step['last_panel'] && ($step['content_type'] === 'video' || $step['content_type'] === 'image' )):?>
                                    <div class="media-container">
                                        <?php if ($step['content_type'] === 'video'): ?>
                                            <video class="dim-video" width="100%" height="auto" autoplay muted loop>
                                                <source src="<?= $step['video'] ?>" type="video/mp4">
                                            </video>
                                            <?php
                                            $template_id = (int) ($step['template_id'] ?? 0);

                                            if ($template_id) {
                                                echo \Elementor\Plugin::$instance->frontend->get_builder_content_for_display($template_id, true);
                                            }
                                            ?>
                                        <?php endif; ?>

                                        <?php if ($step['content_type'] === 'image'): ?>
                                            <div class="dim-image-bg" <?= !empty($step['image']['url']) ? "style=\"background-image: url('" . esc_url($step['image']['url']) . "');\"" : '' ?> >
                                                <?php
                                                $template_id = (int) ($step['template_id'] ?? 0);

                                                if ($template_id) {
                                                    echo \Elementor\Plugin::$instance->frontend->get_builder_content_for_display($template_id, true);
                                                }
                                                ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php else: ?>

                                <?php if (!wp_is_mobile()): ?>
                                    <div class="dim-horizontal-track">
                                        <section class="dim-horizontal-slide">
                                            <?php if ($step['content_type'] === 'video'): ?>
                                                <video class="dim-video" width="100%" height="auto" autoplay muted loop>
                                                    <source src="<?= $step['video'] ?>" type="video/mp4">
                                                </video>
                                                <?php
                                                $template_id = (int) ($step['template_id'] ?? 0);

                                                if ($template_id) {
                                                    echo \Elementor\Plugin::$instance->frontend->get_builder_content_for_display($template_id, true);
                                                }
                                                ?>

                                            <?php else: ?>
                                                <div class="dim-image-bg" style="background-image: url(<?= $step['image']['url']; ?>)">
                                                    <?php
                                                    $template_id = (int) ($step['template_id'] ?? 0);

                                                    if ($template_id) {
                                                        echo \Elementor\Plugin::$instance->frontend->get_builder_content_for_display($template_id, true);
                                                    }
                                                    ?>
                                                </div>
                                            <?php endif; ?>
                                        </section>
                                        <section class="dim-horizontal-slide images-panel">
                                            <div class="horizontal-image-content-container">
                                                <div class="img-wrapper">
                                                    <img src="/wp-content/uploads/2026/04/over_ons_1.webp" class="horizontal-img main-line" alt="">
                                                    <img src="/wp-content/uploads/2026/04/over_ons_side_1.webp" class="horizontal-img side-line top" style="width: 200px; height: 180px" alt="">
                                                    <div class="horizontal-text side-line left-bottom">
                                                        <p>
                                                            <strong> Dimensio – de zakkenspecialist </strong>
                                                            Wat begon met één ondernemer en een duidelijke visie, is uitgegroeid tot een toonaangevende speler in de verpakkingsindustrie. Vandaag bouwen ruim 450 bevlogen medewerkers samen aan die groei. Collega’s die er vanaf het begin bij zijn. Die het bedrijf hebben zien groeien en daar zelf in zijn meegegroeid. Die kansen hebben gepakt, verantwoordelijkheid hebben genomen en vandaag nog steeds bouwen aan wat het geworden is. De sfeer is open, direct en betrokken. Mensen kennen elkaar, helpen elkaar en zeggen waar het op staat. Het is een familiebedrijf, met een duidelijke ondernemersgeest. Werken hier voelt als ondernemen binnen een organisatie. Ruimte krijgen én nemen. Eigenaarschap voelen. Het verschil willen maken.
                                                        </p>
                                                    </div>
                                                </div>

                                                <div class="img-wrapper">
                                                    <img src="/wp-content/uploads/2026/04/over_ons_2.webp" class="horizontal-img main-line portrait">
                                                    <img src="/wp-content/uploads/2026/04/over_ons_side_2.webp" class="horizontal-img side-line bottom portrait" >
                                                </div>
                                                <div class="img-wrapper">
                                                    <div class="horizontal-text side-line left-top">
                                                        <p>
                                                            <strong> Zo werken wij </strong>
                                                            Die houding zie je terug in alles wat we doen. In hoe we samenwerken en in hoe we met klanten omgaan.
                                                            We denken mee. We lossen op. We nemen werk uit handen.
                                                            Met aandacht — voor de vraag, voor het proces en voor het resultaat.
                                                            Als zakkenspecialist en totaalleverancier brengen we kennis, ervaring en specialisme samen. De mensen die hier werken weten waar ze het over hebben. Ze begrijpen de praktijk en zorgen voor oplossingen die kloppen. In kwaliteit, in toepassing en in betrouwbaarheid. Met oog voor detail en gevoel voor wat echt nodig is.
                                                            Als partner die naast je staat.
                                                        </p>
                                                    </div>
                                                    <img src="/wp-content/uploads/2026/04/over_ons_3.webp" class="horizontal-img main-line">
                                                    <img src="/wp-content/uploads/2026/04/over_ons_side_3.webp" class="horizontal-img side-line top">
                                                    <div class="horizontal-text side-line right-bottom">
                                                        <p>
                                                            <strong> In beweging, met richting </strong>
                                                            De organisatie ontwikkelt zich continu. In hoe we werken, in waar we naartoe bewegen en in de keuzes die we maken.
                                                            Meer focus. Meer innovatie. Een duidelijke stap richting duurzamer werken.
                                                            Er wordt gewerkt met gerecyclede materialen en actief meebewogen met ontwikkelingen in wet- en regelgeving. Als vanzelfsprekend onderdeel van hoe we vooruitgaan.
                                                            Een gerichte stap vooruit. Met visie en overtuiging.
                                                        </p>
                                                    </div>
                                                </div>
                                                <div class="img-wrapper">
                                                    <video class="horizontal-img main-line video-horizontal" width="100%" height="auto" autoplay muted loop>
                                                        <source src="/wp-content/uploads/2026/04/overons_8.mp4" type="video/mp4">
                                                    </video>
                                                </div>
                                            </div>
                                        </section>
                                        <section class="dim-horizontal-slide text-content">
                                            <div class="title-left-column">
                                                <h2> Voor elke <span class="green-font">bak</span> </h2>
                                                <h2> een <span class="green-font">zak</span>.</h2>
                                            </div>
                                            <div class="right-column">
                                                <div class="logo-dzs">
                                                    <svg id="dim-dzs-logo-green" data-name="Layer 2" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 250 202.83">
                                                        <defs>
                                                            <style>
                                                                .dim-dzs-logo-green-1 {
                                                                    fill: #00a651;
                                                                }
                                                            </style>
                                                        </defs>
                                                        <g id="Over_Ons" data-name="Over Ons">
                                                            <g id="beeldmerk_DZS">
                                                                <g id="embleem_DZS">
                                                                    <path class="dim-dzs-logo-green-1" d="M176.9,51.21s-.09,0-.13,0c-2.64-.2-16.24-1.27-21.57-1.69l-1.96-.15c-.48-.04-.87-.36-1.02-.83-1.32-4.33-4.66-8.71-8.53-13.78-6.26-8.19-13.35-17.48-14.71-30.19-.12-1.1.23-2.22.96-3.06.72-.83,1.72-1.32,2.81-1.36,24.56-.87,35.56,1.41,40.14,17.9.06.14.65,1.98.74,11.48l2.96-11.48c.24-.95,1.08-1.65,2.06-1.7,2.94-.16,5.87.13,8.7.85.92.23,1.56,1.14,1.48,2.1-1.04,12.5-4.5,22.71-10.57,31.21h0c-.32.45-.82.7-1.35.7ZM176.9,49.48h0,0ZM153.76,47.67l1.57.12c5.31.42,18.8,1.48,21.53,1.69,5.88-8.24,9.23-18.15,10.24-30.32,0-.11-.08-.25-.18-.28-2.66-.67-5.42-.93-8.19-.8-.21.01-.41.18-.47.4l-4.69,18.16c-.11.42-.49.69-.95.64-.43-.06-.76-.43-.75-.87.19-15.09-.61-17.74-.64-17.84-4.23-15.19-13.76-17.59-38.42-16.68-.61.02-1.17.29-1.57.76-.41.48-.61,1.11-.54,1.74,1.31,12.23,8.24,21.31,14.36,29.32,3.87,5.07,7.23,9.46,8.7,13.97Z"/>
                                                                    <path class="dim-dzs-logo-green-1" d="M246.68,202.72h-83.61c-.48,0-.87-.39-.87-.87s.39-.87.87-.87h83.61c.5,0,.96-.23,1.26-.64.31-.42.4-.94.25-1.43-2.82-9.16-6.41-10.83-9.88-12.45-5.39-2.51-10.48-4.88-11.28-31.12-1.4-47.59-14.39-68.12-34.89-85.84-4.68-4.04-9.2-6.76-11.14-7.52.55.78,1.81,2.27,4.72,4.99,1.8,1.7,3.71,3.35,5.56,4.95,7.17,6.22,14.58,12.64,17.72,22.89.25.83-.23,1.2-.38,1.3-.8.49-1.54,0-4.94-3.24-.31-.29-.55-.52-.68-.64-8.13-8.59-15.49-15.14-22.55-20.1-.8-.53-2.75-1.8-3.85-1.4-1.32.74-1.35,3.27-1.38,5.49,0,.64-.01,1.25-.05,1.8-.15,5.2-3.4,30.48-7.18,30.64-.02,0-.04,0-.06,0-1.35,0-1.76-1.95-2-4.06-.2-1.73-.01-5.13.22-9.44.53-9.73,1.41-26.01-2.75-28.38-.93-.52-2.22-.25-3.82.82-8.74,6.2-17.36,21.04-21.71,29.33-.61,1.02-1.34,2.24-2.33,1.82-.58-.25-1.25-.54.26-5.9,1.8-8.06,5.27-14.17,10.29-21.39.33-.66.43-1.03.46-1.23-1.82,0-9.42,5.1-10.85,6.54-2.09,1.68-4.07,3.82-5.99,5.89-1.02,1.1-2.03,2.18-3.02,3.17-.61.6-1.73,1.7-2.66,1.02-.26-.2-1.07-.81.45-3.66,3.18-7.26,10.18-13.37,16.96-19.28,1.84-1.6,3.73-3.25,5.54-4.91-10.57,5.26-19.58,15.88-26.92,24.52l-.34.4c-.31.36-.86.41-1.22.1-.36-.31-.41-.86-.1-1.22l.34-.4c8-9.42,17.95-21.14,29.92-26.07.14-.06.29-.08.45-.06.45.06.79.32.94.71.24.61-.05,1.33-.96,2.41-2.15,2.03-4.37,3.96-6.51,5.83-6.94,6.05-13.49,11.76-16.53,18.72-.04.06-.06.12-.1.18.82-.83,1.65-1.73,2.49-2.64,1.96-2.12,3.99-4.3,6.1-5.99.02-.03,10.53-8.56,13.15-6.67,1.24.9.25,2.81-.12,3.53-5.08,7.33-8.34,13.05-10.09,20.92-.14.5-.26.97-.37,1.41,5.38-10.06,13.27-22.68,21.49-28.51,2.2-1.48,4.1-1.78,5.66-.9,4.71,2.68,4.4,15.71,3.62,29.98-.22,4.06-.41,7.57-.23,9.15.13,1.15.28,1.81.4,2.18,1.79-2.95,5.13-19.29,5.39-28.66.04-.55.05-1.13.05-1.73.03-2.57.06-5.76,2.4-7.05,1.83-.67,3.72.36,5.53,1.54,7.18,5.04,14.62,11.67,22.78,20.29.08.07.34.32.67.63.35.33,1.07,1.01,1.76,1.64-3.29-8.58-9.71-14.15-16.49-20.02-1.86-1.61-3.78-3.28-5.61-5-6.21-5.82-6.09-7.01-5.38-7.79,2.12-2.36,14,7.64,14.12,7.75,20.86,18.02,34.07,38.88,35.49,87.09.77,25.17,5.18,27.22,10.28,29.6,3.64,1.7,7.76,3.61,10.8,13.51.32,1.02.13,2.11-.51,2.97-.63.86-1.6,1.35-2.66,1.35Z"/>
                                                                    <path class="dim-dzs-logo-green-1" d="M176.49,58.53c-.09,0-.18,0-.26-.01l-23.58-1.89c-1.58-.13-2.77-1.53-2.77-3.25,0-.74.31-1.43.87-1.94.63-.58,1.57-.88,2.43-.81l23.58,1.89c1.58.13,2.78,1.53,2.78,3.25,0,.74-.31,1.43-.87,1.94-.57.53-1.37.83-2.18.83ZM152.91,52.33c-.37,0-.74.14-.99.37-.2.19-.31.41-.31.67,0,.69.41,1.46,1.18,1.53l23.58,1.89c.42.03.85-.1,1.13-.36.2-.19.31-.41.31-.67,0-.69-.42-1.46-1.18-1.53l-23.58-1.89h0s-.09,0-.13,0Z"/>
                                                                </g>
                                                                <g>
                                                                    <path class="dim-dzs-logo-green-1" d="M0,125.94h19.36c20.24,0,35.42,14.59,35.42,34.05s-15.18,34.05-35.42,34.05H0v-68.1ZM18.87,181.11c12.75,0,21.7-8.85,21.7-21.11s-8.95-21.11-21.7-21.11h-5.06v42.22h5.06Z"/>
                                                                    <path class="dim-dzs-logo-green-1" d="M74.19,177.87h37.78l-2.42,16.17h-63.61l39.35-68.32h-37.42l2.53-16.17h63.01l-39.23,68.32Z"/>
                                                                    <path class="dim-dzs-logo-green-1" d="M113.42,193.89l2.66-16.29c7.84,6.4,16.41,10.14,24.38,10.14s11.83-4.47,11.83-9.9c0-4.47-2.53-8.45-10.74-11.1l-5.19-1.69c-15.21-4.95-20.76-14.48-20.76-25.46,0-14,10.98-24.63,27.15-24.63,7.36,0,15.33,2.05,23.3,6.16l-2.53,15.93c-7.85-4.71-14.85-7.24-21-7.24-6.64,0-10.38,3.98-10.38,9.05,0,4.59,2.53,8.21,11.22,10.98l5.19,1.69c14.85,4.83,20.64,14.6,20.64,25.35,0,13.64-9.78,25.95-28.48,25.95-11.1,0-20.4-4.22-27.28-8.93Z"/>
                                                                </g>
                                                            </g>
                                                        </g>
                                                    </svg>
                                                </div>
                                                <p>
                                                    <strong> Trots en vooruitkijken </strong>
                                                    Wat hier staat, is gebouwd door mensen die ergens voor gaan. Die samen iets neerzetten en daar trots op zijn.
                                                    De volgende stap is helder.
                                                    Verder bouwen. Verder ontwikkelen. Met dezelfde energie en aandacht die ons hier hebben gebracht.
                                                    Met aandacht. Voor het werk. Voor elkaar. Voor wat er voor ons ligt.
                                                </p>
                                            </div>
                                        </section>
                                    </div>

                                    <div class="final-panel-zoom-in wrapper">
                                        <div class="content-container">
                                            <video class="last-video" width="100%" height="auto" autoplay muted loop>
                                                <source src="/wp-content/uploads/2026/04/over_ons_zwaai.mp4" type="video/mp4">
                                            </video>
                                            <div class="bottom-content dim-column-fade-in-last">
                                                <div class="bottom-container">
                                                    <div class="flex-row">
                                                        <a href="https://www.dimensio.nl/over-ons/">
                                                            <h2> Over Ons </h2>
                                                        </a>
                                                        <a href="https://www.dimensio.nl/over-ons/" class="pulse-btn">
                                                            <svg aria-hidden="true" class="e-font-icon-svg e-fas-chevron-circle-right" viewBox="0 0 512 512" xmlns="http://www.w3.org/2000/svg"><path d="M256 8c137 0 248 111 248 248S393 504 256 504 8 393 8 256 119 8 256 8zm113.9 231L234.4 103.5c-9.4-9.4-24.6-9.4-33.9 0l-17 17c-9.4 9.4-9.4 24.6 0 33.9L285.1 256 183.5 357.6c-9.4 9.4-9.4 24.6 0 33.9l17 17c9.4 9.4 24.6 9.4 33.9 0L369.9 273c9.4-9.4 9.4-24.6 0-34z"></path></svg>
                                                        </a>
                                                    </div>
                                                    <span>
                                                        De vertrouwde kwaliteitszak voor dagelijks restafval.
                                                        Gecertificeerde treksterkte en perforatieweerstand voor veilig gebruik.
                                                    </span>
                                                </div>
                                            </div>
                                        </div>

                                    </div>
                                <?php endif; ?>

                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
}

