<?php
// 🧭 Admin Menu & CSV Upload UI (Batch Processing)

add_action('admin_menu', function () {
    add_menu_page(
        'AI Product Generator',
        'AI Product Generator',
        'manage_options',
        'ai-generator',
        'ai_generator_page',
        'dashicons-products',
        30
    );
});

function ai_generator_page() {
    ?>
    <div class="wrap">
        <h1>AI Product Generator</h1>
        <form id="ai-upload-form" method="POST" enctype="multipart/form-data">
            <table class="form-table">
                <tr>
                    <th>CSV File (must include "name" column)</th>
                    <td><input type="file" name="csv_file" accept=".csv" required /></td>
                </tr>
                <tr>
                    <th>Groq API Key</th>
                    <td><input type="text" name="groq_api_key" style="width:400px;" required /></td>
                </tr>
                <tr>
                    <th>Target Region</th>
                    <td>
                        <select name="target_region" required>
                            <option value="Global">Global</option>
                            <option value="Asia">Asia</option>
                            <option value="Europe">Europe</option>
                            <option value="North America">North America</option>
                            <option value="South America">South America</option>
                            <option value="Africa">Africa</option>
                            <option value="Australia">Australia</option>
                            <option value="Antarctica">Antarctica</option>
                        </select>
                    </td>
                </tr>
            </table>
            <p><input type="submit" value="Upload & Generate" class="button button-primary" /></p>
        </form>

        <div id="ai-progress-section" style="display:none;">
            <h2>Generating Products...</h2>
            <div id="ai-progress-bar" style="width:100%;background:#eee;">
                <div id="ai-progress-fill" style="width:0;height:20px;background:#4caf50;"></div>
            </div>
            <p id="ai-progress-text">0%</p>
        </div>
    </div>
    <?php
}

// Step 1: Upload CSV via AJAX
add_action('wp_ajax_ai_gen_upload_csv', function () {
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }

    if (empty($_FILES['csv_file']['tmp_name']) || empty($_POST['groq_api_key'])) {
        wp_send_json_error(['message' => 'Missing required fields']);
    }

    if (!file_exists(AI_GEN_UPLOAD_DIR)) {
        mkdir(AI_GEN_UPLOAD_DIR, 0755, true);
    }

    $csv_path = AI_GEN_UPLOAD_DIR . 'pending_products.csv';
    move_uploaded_file($_FILES['csv_file']['tmp_name'], $csv_path);

    update_option('ai_gen_csv_path', $csv_path);
    update_option('ai_gen_api_key', sanitize_text_field($_POST['groq_api_key']));
    update_option('ai_gen_region', sanitize_text_field($_POST['target_region']));

    wp_send_json_success(['message' => 'CSV uploaded successfully']);
});

// Step 2: Process batch via AJAX
add_action('wp_ajax_ai_gen_process_batch', function () {
    require_once ABSPATH . 'wp-admin/includes/image.php';
    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/media.php';

    $csv_path = get_option('ai_gen_csv_path');
    $api_key  = get_option('ai_gen_api_key');
    $region   = get_option('ai_gen_region');

    if (!file_exists($csv_path)) {
        wp_send_json_error(['message' => 'CSV not found']);
    }

    $rows = array_map('str_getcsv', file($csv_path));
    $header = array_map('strtolower', array_map('trim', array_shift($rows)));

    $name_index = array_search('name', $header);
    $desc_index = array_search('description', $header);

    if ($name_index === false) {
        wp_send_json_error(['message' => '"name" column missing in CSV']);
    }

    if ($desc_index === false) {
        $header[] = 'description';
        $desc_index = count($header) - 1;
    }

    $column_map = [
        'sku' => '_sku',
        'regular price' => '_regular_price',
        'sale price' => '_sale_price',
        'stock' => '_stock',
        'weight (lbs)' => '_weight',
        'length (in)' => '_length',
        'width (in)' => '_width',
        'height (in)' => '_height',
        'tax status' => '_tax_status',
        'tax class' => '_tax_class',
        'purchase note' => '_purchase_note',
        'visibility in catalog' => '_visibility',
        'allow customer reviews?' => '_reviews_allowed',
        'sold individually?' => '_sold_individually',
        'backorders allowed?' => '_backorders',
        'shipping class' => '_shipping_class',
        'images' => 'images',
        'categories' => 'categories',
        'tags' => 'tags',
        'short description' => 'short_description',
    ];

    $batch_size = 3; // Change this to adjust speed vs. stability
    $offset = isset($_POST['offset']) ? intval($_POST['offset']) : 0;
    $processed = 0;

    for ($i = $offset; $i < min($offset + $batch_size, count($rows)); $i++) {
        $row = $rows[$i];
        $product_name = isset($row[$name_index]) ? trim($row[$name_index]) : '';
        if (empty($product_name)) continue;

        $description = isset($row[$desc_index]) && !empty($row[$desc_index])
            ? $row[$desc_index]
            : ai_generate_description($product_name, $api_key, $region);

        $row[$desc_index] = $description;

        $product_id = wp_insert_post([
            'post_title'   => $product_name,
            'post_content' => $description,
            'post_status'  => 'publish',
            'post_type'    => 'product'
        ]);

        if (is_wp_error($product_id)) continue;

        $price_set = false;

        foreach ($column_map as $csv_column => $meta_key) {
            $col_index = array_search(strtolower($csv_column), $header);
            if ($col_index === false || empty($row[$col_index])) continue;

            $value = trim($row[$col_index]);

            switch ($meta_key) {
                case 'short_description':
                    wp_update_post(['ID' => $product_id, 'post_excerpt' => $value]);
                    break;

                case '_regular_price':
                    $clean_price = preg_replace('/[^0-9\.]/', '', $value);
                    if (is_numeric($clean_price)) {
                        update_post_meta($product_id, '_regular_price', $clean_price);
                        update_post_meta($product_id, '_price', $clean_price);
                        $price_set = true;
                    }
                    break;

                case 'categories':
                    wp_set_object_terms($product_id, array_map('trim', explode(',', $value)), 'product_cat');
                    break;

                case 'tags':
                    wp_set_object_terms($product_id, array_map('trim', explode(',', $value)), 'product_tag');
                    break;

                case 'images':
                    $image_urls = array_map('trim', explode(',', $value));
                    $attachment_ids = [];

                    foreach ($image_urls as $image_url) {
                        $tmp = download_url($image_url);

                        if (is_wp_error($tmp)) continue;

                        $file_array = [
                            'name'     => basename($image_url),
                            'tmp_name' => $tmp
                        ];

                        $attachment_id = media_handle_sideload($file_array, $product_id);

                        if (!is_wp_error($attachment_id)) {
                            $attachment_ids[] = $attachment_id;
                        }

                        @unlink($tmp);
                    }

                    if (!empty($attachment_ids)) {
                        set_post_thumbnail($product_id, $attachment_ids[0]);

                        if (count($attachment_ids) > 1) {
                            update_post_meta($product_id, '_product_image_gallery', implode(',', array_slice($attachment_ids, 1)));
                        }
                    }
                    break;

                default:
                    update_post_meta($product_id, $meta_key, $value);
            }
        }

        // 🛒 Default fallback values
        if (!$price_set) {
            update_post_meta($product_id, '_regular_price', '49.99');
            update_post_meta($product_id, '_price', '49.99');
        }

        if (!get_post_meta($product_id, '_stock', true)) {
            update_post_meta($product_id, '_stock_status', 'instock');
            update_post_meta($product_id, '_manage_stock', 'yes');
            update_post_meta($product_id, '_stock', '100');
        }

        update_post_meta($product_id, '_product_type', 'simple');

        // 🔍 Rank Math SEO
        update_post_meta($product_id, 'rank_math_focus_keyword', $product_name);
        update_post_meta($product_id, 'rank_math_title', "$product_name | #1 Best $product_name");
        update_post_meta($product_id, 'rank_math_description', "$product_name | Buy $product_name Online");

        wp_update_post(['ID' => $product_id]);

        add_action('shutdown', function () use ($product_id) {
            do_action('rank_math/recalculate_score', $product_id);
            do_action('rank_math/seo_score/index_post', $product_id);
        });

        $processed++;
    }

    $done = ($offset + $processed) >= count($rows);

    wp_send_json_success([
        'processed' => $processed,
        'offset'    => $offset + $processed,
        'total'     => count($rows),
        'done'      => $done
    ]);
});
// ✅ Logged-in admin requests
add_action('wp_ajax_ai_gen_process_batch', 'ai_gen_process_batch');

// ✅ Guests too (only if you want public access to this AJAX)
add_action('wp_ajax_nopriv_ai_gen_process_batch', 'ai_gen_process_batch');

function ai_gen_process_batch() {
    // For now, just test if AJAX works
    $offset = isset($_POST['offset']) ? intval($_POST['offset']) : 0;

    wp_send_json_success([
        'message' => 'AJAX works!',
        'offset'  => $offset
    ]);
}

// Load JS
add_action('admin_enqueue_scripts', function () {
    wp_enqueue_script('ai-import', AI_GEN_PLUGIN_URL . 'js/ai-import.js', ['jquery'], '1.0', true);
    wp_localize_script('ai-import', 'AIGen', [
        'ajax_url' => admin_url('admin-ajax.php')
    ]);
});

