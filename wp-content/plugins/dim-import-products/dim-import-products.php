<?php
/**
 * Plugin Name: Dimensio - Import products ACF
 * Description: Admin page to upload CSV/JSON/XML and import CPT "product" with ACF fields (supports ACF Group via dot-notation).
 * Author: Dimensio
 * Version: 1.0.0
 */

if (!defined('ABSPATH')) exit;

/* ------------------------------------------------------------------------
 * Admin menu + page
 * --------------------------------------------------------------------- */
add_action('admin_menu', function () {
    add_menu_page(
        __('Product Import', 'dim'),
        __('Product Import', 'dim'),
        'manage_options',
        'dg-product-import',
        'dg_product_import_page',
        'dashicons-upload',
        25
    );
});

function dg_product_import_page() {
    $max = size_format(wp_max_upload_size());
    ?>
    <div class="wrap">
        <h1><?php _e('Import Products', 'dim'); ?></h1>
        <p>Upload een <strong>CSV</strong>, JSON of XML bestand met producten.</p>
        <p><em>Max upload size: <?php echo esc_html($max); ?></em></p>

        <form method="post" enctype="multipart/form-data">
            <?php wp_nonce_field('dg_product_import', 'dg_import_nonce'); ?>

            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><label for="import_file"> Bestand </label></th>
                    <td><input type="file" id="import_file" name="import_file" accept=".csv,.json,.xml" required></td>
                </tr>
                <tr>
                    <th scope="row"><label for="post_type"> Post type </label></th>
                    <td><input type="text" id="post_type" name="post_type" required></td>
                </tr>
                <tr>
                    <th scope="row"><label for="category_slug"> Categorie slug </label></th>
                    <td><input type="text" id="category_slug" name="category_slug" required></td>
                </tr>
                <tr>
                    <th scope="row"><label for="format">Formaat</label></th>
                    <td>
                        <select id="format" name="format">
                            <option value="csv">CSV</option>
                            <option value="json">JSON</option>
                            <option value="xml">XML</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Opties</th>
                    <td>
                        <label><input type="checkbox" name="dry_run" value="1"> Dry run (niets opslaan, alleen tellen)</label>
                    </td>
                </tr>
            </table>

            <?php submit_button(__('Upload & Import', 'dim')); ?>
        </form>
    </div>
    <?php

    // After the form
    $upload_dir = wp_upload_dir();
    $log_file   = trailingslashit($upload_dir['basedir']) . 'import-log.txt';
    if (file_exists($log_file)) {
        echo '<h2>Laatste import log</h2><pre style="background:#f9f9f9;padding:10px;max-height:300px;overflow:auto;">';
        // Show last ~100 lines
        $lines = file($log_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $last  = array_slice($lines, -100);
        echo esc_html(implode("\n", $last));
        echo '</pre>';
    }
}

/* ------------------------------------------------------------------------
 * Handle form submit (admin trigger)
 * --------------------------------------------------------------------- */
add_action('admin_init', function () {
    if (!isset($_POST['dg_import_nonce'])) return;
    if (!wp_verify_nonce($_POST['dg_import_nonce'], 'dg_product_import')) return;
    if (!current_user_can('manage_options')) return;

    if (empty($_FILES['import_file']['tmp_name'])) return;

    $format = sanitize_text_field($_POST['format'] ?? 'csv');
    $post_type = sanitize_text_field($_POST['post_type'] ?? 'csv');
    $category_slug = sanitize_text_field($_POST['category_slug'] ?? 'csv');

    $dry    = !empty($_POST['dry_run']);

    // Store upload into wp-content/uploads
    $uploaded = wp_handle_upload($_FILES['import_file'], ['test_form' => false]);
    if (isset($uploaded['error'])) {
        add_action('admin_notices', function () use ($uploaded) {
            echo '<div class="notice notice-error"><p>Error: ' . esc_html($uploaded['error']) . '</p></div>';
        });
        return;
    }

    $file = $uploaded['file'];

    // Run import
    $summary = dg_run_product_import($file, $category_slug, $post_type, $format, $dry);

    // Write log
    dg_write_import_log($file, $format, $dry, $summary);

    add_action('admin_notices', function () use ($summary, $dry) {
        $cls = empty($summary['errors']) ? 'notice-success' : 'notice-warning';
        echo '<div class="notice ' . esc_attr($cls) . '"><p>'
            . ($dry ? '<strong>Dry run:</strong> ' : '')
            . 'Created: ' . intval($summary['created'])
            . ', Updated: ' . intval($summary['updated'])
            . ', Skipped: ' . intval($summary['skipped']);
        if (!empty($summary['errors'])) {
            $first = array_slice($summary['errors'], 0, 5);
            echo '<br><small>Notes: ' . esc_html(implode(' | ', $first)) . (count($summary['errors']) > 5 ? ' …' : '') . '</small>';
        }
        echo '</p></div>';
    });
});

/* ------------------------------------------------------------------------
 * Core importer (CSV/JSON/XML) — supports ACF groups via dot-notation
 * - CPT: product
 * - Unique key: algemene_informatie.artikelcode  (adjust if needed)
 * - Taxonomy: categorie_zakken (creates terms if missing)
 * - Optional "image_url" sideloads featured image
 * --------------------------------------------------------------------- */
function dg_run_product_import(string $file, string $category_name, string $post_type, string $format='csv', bool $dry=false): array {
    $format  = strtolower($format);
    $summary = ['created'=>0, 'updated'=>0, 'skipped'=>0, 'errors'=>[]];

    if (!file_exists($file)) {
        $summary['errors'][] = "File not found: $file";
        return $summary;
    }

    // Parse to array of rows (assoc arrays)
    try {
        if ($format === 'csv')  $rows = dg_parse_csv($file);
        elseif ($format === 'json') $rows = dg_parse_json($file);
        elseif ($format === 'xml')  $rows = dg_parse_xml($file);
        else throw new \RuntimeException("Unsupported format: $format");
    } catch (\Throwable $e) {
        $summary['errors'][] = 'Parse error: ' . $e->getMessage();
        return $summary;
    }

    if (empty($rows)) {
        $summary['errors'][] = 'No rows found';
        return $summary;
    }

    // Performance guards
    wp_defer_term_counting(true);
    wp_defer_comment_counting(true);
    wp_suspend_cache_invalidation(true);

    foreach ($rows as $i => $row) {
        // Basic fields
        $title = dg_sval($row['title'] ?? '');
        // Unique key from ACF group: "artikelcode"
        $sku = dg_sval($row['artikelcode'] ?? $row['artikelnummer'] ?? '');

        if ($title === '' || $sku === '') {
            $summary['skipped']++;
            $summary['errors'][] = "Row $i: missing title or artikelcode";
            continue;
        }

        // Find existing product by unique artikelcode
        $existing_id = dg_find_post_by_meta($post_type, 'artikelcode', $sku);
        // Note: if you ALSO save artikelcode as its own ACF field (outside the group),
        // change the meta key above to that exact meta key.

        if ($dry) {
            $existing_id ? $summary['updated']++ : $summary['created']++;
            continue;
        }

        // Create/Update post
        $postarr = [
            'post_type'   => $post_type,
            'post_status' => 'publish',
            'post_title'  => $title,
        ];

        if ($existing_id) {
            $postarr['ID'] = $existing_id;
            $post_id = wp_update_post($postarr, true);
        } else {
            $post_id = wp_insert_post($postarr, true);
        }

        if (is_wp_error($post_id)) {
            $summary['skipped']++;
            $summary['errors'][] = "Row $i: " . $post_id->get_error_message();
            continue;
        }

        // Build ACF payloads from dot-notation: any key starting with "acf."
        // Example CSV header: acf.algemene_informatie.artikelcode
        $acfPayloads = []; // ['algemene_informatie' => [ 'artikelcode' => '...' ], 'merk_naam' => '...']
        foreach ($row as $key => $val) {
            if (!strpos($key, '.')) {
                $acfPayloads[$key] = $val;
                continue;
            };
            $path = explode('.', $key); // ['acf','group','subfield',...]

            //@todo removing first element of array is not neccessary, remove line
            // array_shift($path); // drop 'acf'
            dg_set_deep($acfPayloads, $path, $val);
        }

        // Save ACF fields: if value is array → assume ACF group; else top-level field
        foreach ($acfPayloads as $acfFieldKey => $acfValue) {
            if (in_array($acfFieldKey, ['product_image', '360_video_view'], true)) {
                continue;
            }

            if (is_array($acfValue)) {

                update_field($acfFieldKey, $acfValue, $post_id); // group
                // if group contains artikelcode, also save a flat meta for easier querying:
                foreach ($acfValue as $fieldKey => $fieldValue) {
                    update_post_meta($post_id, $acfFieldKey . "_" .$fieldKey, $fieldValue);
                }
            } else {
                update_field($acfFieldKey, $acfValue, $post_id); // non-group ACF field
            }
        }

        // Taxonomy (comma separated names/slugs)
        $tax_slug = $category_name;
        if (!empty($row['categories'])) {
            $term_ids = dg_ensure_terms($tax_slug, $row['categories']);
            if (!empty($term_ids)) wp_set_object_terms($post_id, $term_ids, $tax_slug, false);
        }

        if (!empty($row['product_image'])) {
            $img_id = dg_get_attachment_id_from_url($row['product_image']);

            if (!$img_id) {
                $img_id = dg_sideload_file_to_post($row['product_image'], $post_id);
            }

            if (!is_wp_error($img_id) && $img_id) {
                update_field('product_image', $img_id, $post_id);
                set_post_thumbnail($post_id, $img_id);
            }
        }

        if (!empty($row['360_video_view'])) {
            $video_id = dg_get_attachment_id_from_url($row['360_video_view']);

            if (!$video_id) {
                $video_id = dg_sideload_file_to_post($row['360_video_view'], $post_id);
            }

            if (!is_wp_error($video_id) && $video_id) {

                update_field('360_video_view', $video_id, $post_id);
            }
        }

        #build custom url for front-end
        $custom_url = $post_type === 'product' ?
                home_url() . '/onze-producten/#product=' . $post_id :
                home_url() . '/just-gloves/#product=' . $post_id;

        update_field('custom_product_url', $custom_url, $post_id);

        $existing_id ? $summary['updated']++ : $summary['created']++;
    }

    // Restore
    wp_defer_term_counting(false);
    wp_defer_comment_counting(false);
    wp_suspend_cache_invalidation(false);
    if (function_exists('wp_cache_flush')) wp_cache_flush();

    return $summary;
}

function dg_get_attachment_id_from_url(string $url): int {
    $attachment_id = attachment_url_to_postid($url);
    return $attachment_id ? (int) $attachment_id : 0;
}

/* ------------------------------------------------------------------------
 * Parsers (CSV auto-detects ; , or \t), JSON, XML (<products><product>…)
 * --------------------------------------------------------------------- */
function dg_parse_csv(string $file): array {
    $rows = [];
    $fh = fopen($file, 'r');
    if (!$fh) throw new \RuntimeException('Cannot open CSV');

    // detect delimiter ; , or \t
    $sample = fgets($fh);
    $delim  = (strpos($sample, ';') !== false) ? ';' : ((strpos($sample, "\t") !== false) ? "\t" : ',');
    rewind($fh);

    $header = fgetcsv($fh, 0, $delim);
    if (!$header) throw new \RuntimeException('Invalid/empty CSV header');


    $header = array_map(function ($h) {
        $h = (string) $h;

        // Remove UTF-8 BOM from first header if present
        $h = preg_replace('/^\xEF\xBB\xBF/', '', $h);

        // Trim spaces
        return trim($h);
    }, $header);

    while (($data = fgetcsv($fh, 0, $delim)) !== false) {
        if (count($data) < count($header)) {
            $data = array_pad($data, count($header), '');
        }

        if (count($data) > count($header)) {
            $data = array_slice($data, 0, count($header));
        }

        $row = array_combine($header, $data);

        $row = array_map(function ($v) {
            return is_string($v) ? trim($v) : $v;
        }, $row);

        $rows[] = $row;
    }

    fclose($fh);
    return $rows;
}
function dg_parse_json(string $file): array {
    $data = json_decode(file_get_contents($file), true);
    if (!is_array($data)) throw new \RuntimeException('Invalid JSON');
    return $data;
}
function dg_parse_xml(string $file): array {
    libxml_use_internal_errors(true);
    $xml = simplexml_load_file($file);
    if (!$xml) throw new \RuntimeException('Invalid XML');
    $rows = [];
    foreach ($xml->product as $p) {
        // Flatten simple elements; allow nested <acf> if you want (optional)
        $row = [];
        foreach ($p->children() as $child) {
            $row[$child->getName()] = trim((string)$child);
        }
        // If you structure as <acf><algemene_informatie>…</algemene_informatie></acf>, you can
        // walk nested nodes and build dot-notation keys here. For simplicity we keep it flat.
        $rows[] = $row;
    }
    return $rows;
}

/* ------------------------------------------------------------------------
 * Helpers
 * --------------------------------------------------------------------- */
function dg_find_post_by_meta(string $post_type, string $key, string $value): int {
    $q = new \WP_Query([
        'post_type'      => $post_type,
        'post_status'    => 'any',
        'fields'         => 'ids',
        'meta_query'     => [[ 'key' => $key, 'value' => $value, 'compare' => '=' ]],
        'posts_per_page' => 1,
        'no_found_rows'  => true,
    ]);
    return (int)($q->posts[0] ?? 0);
}
function dg_ensure_terms(string $taxonomy, string $input): array {
    $parts = array_filter(array_map('trim', explode(',', (string)$input)));
    $ids = [];
    foreach ($parts as $name_or_slug) {
        $found = term_exists($name_or_slug, $taxonomy);
        if (!$found) $found = term_exists(sanitize_title($name_or_slug), $taxonomy);
        if (!$found) {
            $created = wp_insert_term($name_or_slug, $taxonomy);
            if (!is_wp_error($created)) $ids[] = (int)$created['term_id'];
        } else {
            $ids[] = (int)(is_array($found) ? $found['term_id'] : $found);
        }
    }
    return array_values(array_unique($ids));
}
function dg_sideload_file_to_post(string $url, int $post_id): WP_Error|bool|int|string
{
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        return 0;
    }

    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/media.php';
    require_once ABSPATH . 'wp-admin/includes/image.php';

    $tmp = download_url($url);

    if (is_wp_error($tmp)) {
        return $tmp;
    }

    $file_array = [
            'name'     => basename(parse_url($url, PHP_URL_PATH)),
            'tmp_name' => $tmp,
    ];

    $attachment_id = media_handle_sideload($file_array, $post_id);

    if (is_wp_error($attachment_id)) {
        @unlink($tmp);
        return $attachment_id;
    }

    return $attachment_id;
}
function dg_sval($v): string { return trim((string)$v); }

/**
 * Deep set helper for dot-notation: dg_set_deep($arr, ['group','key'], 'value')
 * → $arr['group']['key'] = 'value'
 */
function dg_set_deep(array &$arr, array $path, $value): void {
    $ref =& $arr;
    foreach ($path as $i => $key) {
        $is_last = $i === array_key_last($path);
        if ($is_last) {
            $ref[$key] = $value;
        } else {
            if (!isset($ref[$key]) || !is_array($ref[$key])) $ref[$key] = [];
            $ref =& $ref[$key];
        }
    }
}

function dg_write_import_log(string $file, string $format, bool $dry, array $summary): void {
    $upload_dir = wp_upload_dir();
    $log_file   = trailingslashit($upload_dir['basedir']) . 'import-log.txt';

    $timestamp = date('Y-m-d H:i:s');
    $line  = "[$timestamp] Import ($format) " . ($dry ? "[DRY RUN]" : "") . PHP_EOL;
    $line .= "Source file: $file" . PHP_EOL;
    $line .= "Created: {$summary['created']} | Updated: {$summary['updated']} | Skipped: {$summary['skipped']}" . PHP_EOL;
    if (!empty($summary['errors'])) {
        $line .= "Errors: " . implode(' | ', $summary['errors']) . PHP_EOL;
    }
    $line .= str_repeat('-', 60) . PHP_EOL;

    file_put_contents($log_file, $line, FILE_APPEND);
}