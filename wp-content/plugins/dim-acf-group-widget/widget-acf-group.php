<?php
namespace Dim;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use Elementor\Repeater;
use Elementor\Group_Control_Typography;

if ( ! defined('ABSPATH') ) exit;

class Widget_ACF_Group extends Widget_Base {

    public function get_name(): string { return 'dim_acf_group'; }

    public function get_title(): string { return 'ACF Group (Dimensio)'; }

    public function get_icon(): string { return 'eicon-post-content'; }

    public function get_categories(): array { return ['general']; } // change to your custom category if you have one

    public function get_style_depends(): array { return ['dim-acf-group-widget']; }

    protected function register_controls(): void
    {
        $this->start_controls_section('section_source', [
            'label' => __('Source', 'dim'),
            'tab'   => Controls_Manager::TAB_CONTENT,
        ]);

        $this->add_control('group_field', [
            'label'       => __('ACF Group field name', 'dim'),
            'type'        => Controls_Manager::TEXT,
            'placeholder' => 'e.g. specs',
            'description' => __('ACF group field NAME (not label), e.g. "specs".', 'dim'),
            'label_block' => true,
        ]);

        $this->add_control('group_field_layout', [
            'label'       => __('HTML Layout', 'dim'),
            'type'        => Controls_Manager::SELECT,
            'placeholder' => 'e.g. specs',
            'description' => __('How to render the elements', 'dim'),
            'default' => 'div',
            'options' => [
                'div'       => __('Div', 'dim'),
                'table'     => __('Table', 'dim'),
                'list'      => __('List', 'dim'),
            ],
        ]);

        $rep = new Repeater();
        $rep->add_control('subfield', [
            'label'       => __('Subfield name', 'dim'),
            'type'        => Controls_Manager::TEXT,
            'placeholder' => 'e.g. material',
        ]);

        $rep->add_control('show_label', [
            'label'        => __('Show field Label', 'dim'),
            'type'         => Controls_Manager::SWITCHER,
            'label_on'     => __('Yes', 'dim'),
            'label_off'    => __('No', 'dim'),
            'return_value' => 'yes',
            'default'      => 'no',
        ]);

        $rep->add_control('label', [
            'label'       => __('Label (optional)', 'dim'),
            'type'        => Controls_Manager::TEXT,
            'placeholder' => 'Materiaal',
            'condition' => [              // shown only when show_extra is ON
                'show_label' => 'yes',
            ],
        ]);
        $rep->add_control('format', [
            'label'   => __('Format', 'dim'),
            'type'    => Controls_Manager::SELECT,
            'default' => 'text',
            'options' => [
                'text'       => __('Text', 'dim'),
                'boolean_ja' => __('Boolean (Ja/Nee)', 'dim'),
                'list'       => __('List (array → comma)', 'dim'),
            ],
        ]);

        $this->add_control('rows', [
            'label'       => __('Subfields', 'dim'),
            'type'        => Controls_Manager::REPEATER,
            'fields'      => $rep->get_controls(),
            'title_field' => '{{{ subfield }}}',
        ]);

        $this->end_controls_section();

        // Labels typography/colors
        $this->start_controls_section('section_style_label', [
            'label' => __('Label', 'dim'),
            'tab'   => Controls_Manager::TAB_STYLE,
        ]);

        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name'     => 'label_typo',
            'selector' => '{{WRAPPER}} .dim-acf-group .dim-col--label, {{WRAPPER}} .dim-acf-group .dim-list-label',
        ]);

        $this->add_control('label_color', [
            'label'     => __('Color', 'dim'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .dim-acf-group .dim-col--label' => 'color: {{VALUE}};',
                '{{WRAPPER}} .dim-acf-group .dim-list-label' => 'color: {{VALUE}};',
            ],
        ]);

        $this->end_controls_section();

        // Values typography/colors
        $this->start_controls_section('section_style_value', [
            'label' => __('Value', 'dim'),
            'tab'   => Controls_Manager::TAB_STYLE,
        ]);

        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name'     => 'value_typo',
            'selector' => '{{WRAPPER}} .dim-acf-group .dim-col--value, {{WRAPPER}} .dim-acf-group .dim-list-value',
        ]);

        $this->add_control('value_color', [
            'label'     => __('Color', 'dim'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .dim-acf-group .dim-col--value' => 'color: {{VALUE}};',
                '{{WRAPPER}} .dim-acf-group .dim-list-value' => 'color: {{VALUE}};',
            ],
        ]);

        $this->end_controls_section();

        $this->start_controls_section('section_layout', [
            'label' => __('Layout', 'dim'),
            'tab'   => Controls_Manager::TAB_STYLE,
        ]);

        $this->add_control('layout', [
            'label'   => __('Layout', 'dim'),
            'type'    => Controls_Manager::SELECT,
            'default' => 'table',
            'options' => [
                'table' => __('Table (2 cols)', 'dim'),
                'list'  => __('List', 'dim'),
            ],
        ]);

        $this->add_control('show_empty', [
            'label'        => __('Show empty fields', 'dim'),
            'type'         => Controls_Manager::SWITCHER,
            'label_on'     => __('Yes', 'dim'),
            'label_off'    => __('No', 'dim'),
            'return_value' => 'yes',
            'default'      => 'no',
        ]);

        $this->end_controls_section();
    }

    public function render(): void
    {
        $s = $this->get_settings_for_display();
        $post_id = get_the_ID();

        if (empty($s['group_field']) || ! $post_id) return;

        // Fetch the entire group array (ACF Group returns assoc array of subfields)
        $group = get_field($s['group_field'], $post_id);
        if (!$group || !is_array($group)) return;

        $items = [];
        foreach (($s['rows'] ?? []) as $row) {
            $key = $row['subfield'] ?? '';
            if (!$key) continue;

            $raw  = $group[$key] ?? null;
            $val  = $this->format_value($raw, $row['format'] ?? 'text');
            $label= $row['label'] ?: ucfirst(str_replace('_',' ',$key));

            if ($val === '' && $s['show_empty'] !== 'yes') {
                continue;
            }

            $returnItem = ['show_label' => $row['show_label']];

            $returnItem = array_merge($returnItem, ['label' => $label, 'value' => $val !== '' ? $val : '']);

            $items[] = $returnItem;
        }

        if (!$items) return;

        // Output
        echo '<div class="dim-acf-group dim-acf-group--' . esc_attr($s['group_field_layout']) . '">';
        if ($s['group_field_layout'] === 'list') {
            echo '<ul class="dim-acf-list">';
            foreach ($items as $it) {
                if ($it['show_label'] === 'yes') {
                    echo '<li><strong>' . esc_html($it['label']) . ':</strong> ' . esc_html($it['value']) . '</li>';
                } else {
                    echo '<li>'. esc_html($it['value']) . '</li>';
                }
            }
            echo '</ul>';
        } else if ($s['group_field_layout'] === 'table') {
            echo '<table class="dim-acf-table">';
            foreach ($items as $it) {
                if ($it['show_label'] === 'yes') {
                    echo '<tr><td class="dim-acf-label">' . esc_html($it['label']) . '</td> <td>' . esc_html($it['value']) . '</td></tr>';
                } else {
                    echo '<tr><td>'. esc_html($it['value']) . '</tr>';
                }
            }

            echo '</table>';
        } else {
            echo '<div class="dim-acf-container">';
            foreach ($items as $it) {
                if ($it['show_label'] === 'yes') {
                    echo '<div class="dim-row"><div class="dim-col dim-col--label">' . esc_html($it['label']) . '</div><div class="dim-col dim-col--value">' . esc_html($it['value']) . '</div></div>';
                } else {
                    echo '<div class="dim-row"><div class="dim-col dim-col--value">' . esc_html($it['value']) . '</div></div>';
                }
            }
            echo '</div>';
        }
        echo '</div>';
    }

    private function format_value($raw, string $format): string {
        if ($raw === null || $raw === '') return '';
        switch ($format) {
            case 'boolean_ja':
                return ($raw === true || $raw === '1' || $raw === 1) ? 'Ja' : 'Nee';
            case 'list':
            case 'text':
            default:
                if (is_array($raw)) return implode(', ', array_map('strval', $raw));
                return (string) $raw;
        }
    }

}
