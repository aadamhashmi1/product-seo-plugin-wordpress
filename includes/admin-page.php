<?php
// 🧭 Admin Menu & CSV Upload UI

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
        <form method="POST" enctype="multipart/form-data">
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
            <p><input type="submit" name="submit_csv" value="Upload & Generate" class="button button-primary" /></p>
        </form>
    </div>
    <?php

    if (isset($_POST['submit_csv'])) {
    $api_key = sanitize_text_field($_POST['groq_api_key']);
    $region  = sanitize_text_field($_POST['target_region']);
    $file    = $_FILES['csv_file']['tmp_name'];

    if (!file_exists($file)) {
        echo "<div class='notice notice-error'><p>Error: File not found.</p></div>";
        return;
    }

    $rows = array_map('str_getcsv', file($file));
    $header = array_map('strtolower', array_map('trim', array_shift($rows)));

    if (!in_array('name', $header)) {
        echo "<div class='notice notice-error'><p>Error: 'name' column missing in CSV.</p></div>";
        return;
    }

    $payload = [
        'rows' => $rows,
        'header' => $header,
        'api_key' => $api_key,
        'region' => $region
    ];

    set_transient('ai_batch_payload', $payload, 60 * 60);

    echo "<div class='notice notice-success'><p>✅ CSV uploaded. Ready to start import.</p></div>";
    echo "<button id='start-import' class='button button-primary'>Start Import</button>";
    echo "<progress id='progress-bar' value='0' max='" . count($rows) . "'></progress>";
    echo "<p id='status'></p>";
}

}
add_action('wp_ajax_ai_generate_batch', 'ai_generate_batch');
function ai_generate_batch() {
    $batch_index = intval($_POST['batch_index']);
    $batch_size = 5;

    $payload = get_transient('ai_batch_payload');
    if (!$payload) wp_send_json_error(['message' => 'No data found.']);

    $rows = $payload['rows'];
    $header = $payload['header'];
    $api_key = $payload['api_key'];
    $region = $payload['region'];

    $start = $batch_index * $batch_size;
    $batch = array_slice($rows, $start, $batch_size);

    foreach ($batch as $row) {
        $name_index = array_search('name', $header);
        $desc_index = array_search('description', $header);

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

        // You can reuse your column_map logic here later
    }

    wp_send_json_success(['processed' => count($batch)]);
}
add_action('admin_enqueue_scripts', function () {
    wp_enqueue_script('ai-import-js', plugin_dir_url(__FILE__) . 'js/ai-import.js', ['jquery'], null, true);
    wp_localize_script('ai-import-js', 'ai_ajax', [
        'ajaxurl' => admin_url('admin-ajax.php'),
        'totalRows' => get_transient('ai_batch_payload') ? count(get_transient('ai_batch_payload')['rows']) : 0
    ]);
});

function ai_generator_process_csv() {
    require_once ABSPATH . 'wp-admin/includes/image.php';
    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/media.php';

    $api_key = sanitize_text_field($_POST['groq_api_key']);
    $region  = sanitize_text_field($_POST['target_region']);
    $file    = $_FILES['csv_file']['tmp_name'];

    if (!file_exists($file)) {
        echo "<div class='notice notice-error'><p>Error: File not found.</p></div>";
        return;
    }

    $rows = array_map('str_getcsv', file($file));
    $header = array_map('strtolower', array_map('trim', array_shift($rows)));

    $name_index = array_search('name', $header);
    $desc_index = array_search('description', $header);

    if ($name_index === false) {
        echo "<div class='notice notice-error'><p>Error: 'name' column missing in CSV.</p></div>";
        return;
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

    $updated_rows = [];

    foreach ($rows as $row) {
        $product_name = isset($row[$name_index]) ? trim($row[$name_index]) : '';
        if (empty($product_name)) continue;

        $description = isset($row[$desc_index]) && !empty($row[$desc_index])
            ? $row[$desc_index]
            : ai_generate_description($product_name, $api_key, $region);

        $row[$desc_index] = $description;
        $updated_rows[] = $row;

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

        $post_data = get_post($product_id);
        $post_data->post_title .= ' ';
        wp_update_post($post_data);
        do_action('rank_math/recalculate_score', $product_id);
    }

    // 📤 Export updated CSV
    if (!file_exists(AI_GEN_UPLOAD_DIR)) {
        mkdir(AI_GEN_UPLOAD_DIR, 0755, true);
    }

    $output_path = AI_GEN_UPLOAD_DIR . 'updated_products.csv';
    $fp = fopen($output_path, 'w');
    fputcsv($fp, $header);
    foreach ($updated_rows as $row) {
        fputcsv($fp, $row);
    }
    fclose($fp);

    $download_url = plugins_url('uploads/updated_products.csv', dirname(__FILE__));
    echo "<div class='notice notice-success'><p><strong>Success!</strong> Products created. <a href='$download_url' target='_blank'>Download updated CSV</a></p></div>";
}
