<?php
if (!defined('ABSPATH')) exit;

class Dim_Sequences_Admin {
    const CAPABILITY = 'manage_options';
    const MENU_SLUG  = 'dim-sequences';

    public static function init(): void {
        add_action('admin_menu', [__CLASS__, 'admin_menu']);
        add_action('admin_post_dim_sequences_upload', [__CLASS__, 'handle_upload']);
    }

    public static function admin_menu(): void {
        add_menu_page(
            __('DIM Sequences', 'dim'),
            __('DIM Sequences', 'dim'),
            self::CAPABILITY,
            self::MENU_SLUG,
            [__CLASS__, 'render_page'],
            'dashicons-images-alt2',
            58
        );
    }

    private static function sequences_base_dir(): string {
        $upload = wp_upload_dir();
        return trailingslashit($upload['basedir']) . 'sequences';
    }

    private static function sequences_base_url(): string {
        $upload = wp_upload_dir();
        return trailingslashit($upload['baseurl']) . 'sequences';
    }

    private static function ensure_base_dir(): void {
        $base = self::sequences_base_dir();
        if (!file_exists($base)) {
            wp_mkdir_p($base);
        }
    }

    public static function render_page(): void {
        if (!current_user_can(self::CAPABILITY)) {
            wp_die(__('You do not have permission.', 'dim'));
        }

        self::ensure_base_dir();
        $base_dir = self::sequences_base_dir();
        $base_url = self::sequences_base_url();

        // read sequences list
        $folders = [];
        if (is_dir($base_dir)) {
            foreach (scandir($base_dir) as $name) {
                if ($name === '.' || $name === '..') continue;
                $path = $base_dir . DIRECTORY_SEPARATOR . $name;
                if (is_dir($path)) $folders[] = $name;
            }
        }
        sort($folders);

        $notice = '';
        if (!empty($_GET['dim_notice'])) {
            $notice = sanitize_text_field(wp_unslash($_GET['dim_notice']));
        }
        ?>
        <div class="wrap">
            <h1>DIM Sequences</h1>

            <?php if ($notice): ?>
                <div class="notice notice-success is-dismissible"><p><?php echo esc_html($notice); ?></p></div>
            <?php endif; ?>

            <p><strong>Base dir:</strong> <code><?php echo esc_html($base_dir); ?></code></p>
            <p><strong>Base url:</strong> <code><?php echo esc_html($base_url); ?></code></p>

            <hr />

            <h2>Upload a ZIP</h2>
            <p>
                Upload a ZIP containing frames (webp/png/jpg). Files can be in the root of the ZIP or inside a single folder.
                The ZIP will be extracted to <code>/uploads/sequences/&lt;slug&gt;/</code>.
            </p>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data">
                <?php wp_nonce_field('dim_sequences_upload'); ?>
                <input type="hidden" name="action" value="dim_sequences_upload" />

                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="dim_slug">Sequence slug</label></th>
                        <td>
                            <input name="dim_slug" id="dim_slug" type="text" class="regular-text" placeholder="hero-truck-01" required />
                            <p class="description">Lowercase, numbers, dashes. Example: <code>hero-truck-01</code>.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="dim_zip">ZIP file</label></th>
                        <td>
                            <input name="dim_zip" id="dim_zip" type="file" accept=".zip" required />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">If folder exists</th>
                        <td>
                            <label><input type="radio" name="dim_mode" value="replace" checked> Replace</label><br>
                            <label><input type="radio" name="dim_mode" value="merge"> Merge</label>
                            <p class="description">Replace will delete existing frames in the slug folder first.</p>
                        </td>
                    </tr>
                </table>

                <?php submit_button('Upload & Extract'); ?>
            </form>

            <hr />

            <h2>Existing sequences</h2>
            <?php if (empty($folders)): ?>
                <p>No sequences found yet.</p>
            <?php else: ?>
                <ul>
                    <?php foreach ($folders as $f): ?>
                        <li><code><?php echo esc_html($f); ?></code></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
        <?php
    }

    public static function handle_upload(): void {
        if (!current_user_can(self::CAPABILITY)) {
            wp_die(__('You do not have permission.', 'dim'));
        }
        check_admin_referer('dim_sequences_upload');

        self::ensure_base_dir();

        $slug = isset($_POST['dim_slug']) ? sanitize_title(wp_unslash($_POST['dim_slug'])) : '';
        if (!$slug) {
            self::redirect_notice('Missing slug.');
        }

        $mode = isset($_POST['dim_mode']) ? sanitize_text_field(wp_unslash($_POST['dim_mode'])) : 'replace';
        if (!in_array($mode, ['replace', 'merge'], true)) $mode = 'replace';

        if (empty($_FILES['dim_zip']) || !isset($_FILES['dim_zip']['tmp_name'])) {
            self::redirect_notice('No ZIP uploaded.');
        }

        $file = $_FILES['dim_zip'];

        if (!empty($file['error'])) {
            self::redirect_notice('Upload error: ' . (string)$file['error']);
        }

        $tmp = $file['tmp_name'];
        $name = (string)$file['name'];

        if (strtolower(pathinfo($name, PATHINFO_EXTENSION)) !== 'zip') {
            self::redirect_notice('Only ZIP files are allowed.');
        }

        $target_dir = trailingslashit(self::sequences_base_dir()) . $slug;

        // Replace mode: clear folder
        if ($mode === 'replace' && is_dir($target_dir)) {
            self::rrmdir($target_dir);
        }
        if (!file_exists($target_dir)) {
            wp_mkdir_p($target_dir);
        }

        if (!class_exists('ZipArchive')) {
            self::redirect_notice('ZipArchive not available on server.');
        }

        $zip = new ZipArchive();
        if ($zip->open($tmp) !== true) {
            self::redirect_notice('Could not open ZIP.');
        }

        $allowed_ext = ['webp', 'png', 'jpg', 'jpeg'];

        // Extract safely: no traversal, only allowed extensions
        $extracted = 0;

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            if (!$stat || empty($stat['name'])) continue;

            $entry = (string)$stat['name'];

            // Skip directories
            if (str_ends_with($entry, '/')) continue;

            // Prevent traversal
            if (str_contains($entry, '..') || str_starts_with($entry, '/') || str_starts_with($entry, '\\')) continue;

            $ext = strtolower(pathinfo($entry, PATHINFO_EXTENSION));
            if (!in_array($ext, $allowed_ext, true)) continue;

            // Write file to target_dir, flatten nested paths by taking basename
            $basename = basename($entry);

            // optional: keep subfolders? for now flatten
            $dest = trailingslashit($target_dir) . $basename;

            $stream = $zip->getStream($entry);
            if (!$stream) continue;

            $out = fopen($dest, 'wb');
            if (!$out) {
                fclose($stream);
                continue;
            }

            while (!feof($stream)) {
                fwrite($out, fread($stream, 8192));
            }
            fclose($out);
            fclose($stream);

            $extracted++;
        }

        $zip->close();

        self::redirect_notice("Extracted {$extracted} frame(s) into sequences/{$slug}.");
    }

    private static function rrmdir(string $dir): void {
        if (!is_dir($dir)) return;
        $items = scandir($dir);
        if (!$items) return;
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') continue;
            $path = $dir . DIRECTORY_SEPARATOR . $item;
            if (is_dir($path)) self::rrmdir($path);
            else @unlink($path);
        }
        @rmdir($dir);
    }

    private static function redirect_notice(string $msg): void {
        wp_safe_redirect(add_query_arg(
            ['page' => self::MENU_SLUG, 'dim_notice' => rawurlencode($msg)],
            admin_url('admin.php')
        ));
        exit;
    }
}