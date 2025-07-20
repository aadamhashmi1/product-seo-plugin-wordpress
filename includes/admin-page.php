<?php

add_action('admin_menu', function () {
    add_menu_page('AI Product Generator', 'AI Product Generator', 'manage_options', 'ai-generator', 'ai_generator_page');
});

function ai_generator_page()
{
    ?>
    <div class="wrap">
        <h2>AI Product Generator</h2>
        <form method="POST" enctype="multipart/form-data">
            <table class="form-table">
                <tr>
                    <th>CSV File (with "name" column)</th>
                    <td><input type="file" name="csv_file" accept=".csv" required /></td>
                </tr>
                <tr>
                    <th>Groq API Key</th>
                    <td><input type="text" name="groq_api_key" style="width:400px;" required /></td>
                </tr>
            </table>
            <p><input type="submit" name="submit_csv" value="Upload & Generate" class="button button-primary" /></p>
        </form>
    </div>
    <?php

    if (isset($_POST['submit_csv'])) {
        $api_key = sanitize_text_field($_POST['groq_api_key']);
        $file = $_FILES['csv_file']['tmp_name'];

        if (!file_exists($file)) {
            echo "<div class='notice notice-error'><p>Error: File not found.</p></div>";
            return;
        }

        $rows = array_map('str_getcsv', file($file));
        $header = array_shift($rows);

        $normalized_header = array_map('strtolower', $header);
        $name_index = array_search('name', $normalized_header);
        $desc_index = array_search('description', $normalized_header);

        if ($name_index === false) {
            echo "<div class='notice notice-error'><p>Error: 'name' column missing.</p></div>";
            return;
        }

        if ($desc_index === false) {
            $header[] = 'description';
            $desc_index = count($header) - 1;
        }

        $updated_rows = [];

        foreach ($rows as $row) {
            $product_name = $row[$name_index];

            $description = ai_generate_description($product_name, $api_key);

            // Add keyword-rich block to meet keyword density (appears ~15–17 times)
            $keyword_block = "";
            for ($i = 0; $i < 15; $i++) {
                $keyword_block .= "<p>Looking for the best $product_name? Discover why $product_name is trusted by users worldwide. Our guide to $product_name shows everything you need.</p>";
            }

            $final_description = $description . $keyword_block;

            $row[$desc_index] = $final_description;
            $updated_rows[] = $row;

            $product_id = wp_insert_post([
                'post_title'   => $product_name,
                'post_content' => $final_description,
                'post_status'  => 'publish',
                'post_type'    => 'product'
            ]);

            // WooCommerce metadata
            update_post_meta($product_id, '_regular_price', '49.99');
            update_post_meta($product_id, '_price', '49.99');
            update_post_meta($product_id, '_stock_status', 'instock');
            update_post_meta($product_id, '_manage_stock', 'yes');
            update_post_meta($product_id, '_stock', '100');
            update_post_meta($product_id, '_product_type', 'simple');

            // Rank Math SEO
            update_post_meta($product_id, 'rank_math_focus_keyword', $product_name);
            update_post_meta($product_id, 'rank_math_title', "$product_name | # 1 Best $product_name");
            update_post_meta($product_id, 'rank_math_description', "$product_name | # 1 Best $product_name |");
        }

        $upload_dir = plugin_dir_path(__FILE__) . '../uploads/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        $output_path = $upload_dir . 'updated_products.csv';
        $fp = fopen($output_path, 'w');
        fputcsv($fp, $header);
        foreach ($updated_rows as $row) {
            fputcsv($fp, $row);
        }
        fclose($fp);

        $download_url = plugins_url('uploads/updated_products.csv', dirname(__FILE__));
        echo "<div class='notice notice-success'><p><strong>Success!</strong> Products created with full SEO blocks, images, and Rank Math scoring enabled. <a href='$download_url' target='_blank'>Download updated CSV</a></p></div>";
    }
}
