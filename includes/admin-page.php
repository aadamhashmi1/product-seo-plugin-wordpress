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
                    <th>CSV File (must include "name" column)</th>
                    <td><input type="file" name="csv_file" accept=".csv" required /></td>
                </tr>
                <tr>
                    <th>Groq API Key</th>
                    <td><input type="text" name="groq_api_key" style="width:400px;" required /></td>
                </tr>
                <tr>
                    <th>Select Regions</th>
                    <td>
                        <fieldset>
                            <?php
                            $available_regions = [
                                'Asia', 'Europe', 'North America', 'South America',
                                'Africa', 'Australia', 'Antarctica', 'GCC' // ✅ Added GCC
                            ];
                            foreach ($available_regions as $region) {
                                echo "<label><input type='checkbox' name='target_region[]' value='$region' /> $region</label><br>";
                            }
                            ?>
                        </fieldset>
                        <p><small>Select one or more regions for localized SEO content</small></p>
                    </td>
                </tr>
            </table>
            <p><input type="submit" name="submit_csv" value="Upload & Generate" class="button button-primary" /></p>
        </form>
    </div>
    <?php

    if (isset($_POST['submit_csv'])) {
        $api_key = sanitize_text_field($_POST['groq_api_key']);
        $regions = array_map('sanitize_text_field', $_POST['target_region'] ?? []);
        $file = $_FILES['csv_file']['tmp_name'];

        if (empty($regions)) {
            echo "<div class='notice notice-error'><p>Error: Please select at least one region.</p></div>";
            return;
        }

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
            echo "<div class='notice notice-error'><p>Error: 'name' column missing in CSV.</p></div>";
            return;
        }

        if ($desc_index === false) {
            $header[] = 'description';
            $desc_index = count($header) - 1;
        }

        $updated_rows = [];

        foreach ($rows as $row) {
            $product_name = $row[$name_index];
            $combined_description = '';

            foreach ($regions as $region) {
                $section = ai_generate_description($product_name, $api_key, $region);
                $combined_description .= "<h2>SEO for $region</h2>\n" . $section . "\n\n";
            }

            $row[$desc_index] = $combined_description;
            $updated_rows[] = $row;

            $product_id = wp_insert_post([
                'post_title'   => $product_name,
                'post_content' => $combined_description,
                'post_status'  => 'publish',
                'post_type'    => 'product'
            ]);

            update_post_meta($product_id, '_regular_price', '49.99');
            update_post_meta($product_id, '_price', '49.99');
            update_post_meta($product_id, '_stock_status', 'instock');
            update_post_meta($product_id, '_manage_stock', 'yes');
            update_post_meta($product_id, '_stock', '100');
            update_post_meta($product_id, '_product_type', 'simple');

            update_post_meta($product_id, 'rank_math_focus_keyword', $product_name);
            update_post_meta($product_id, 'rank_math_title', "$product_name | #1 Best $product_name");
            update_post_meta($product_id, 'rank_math_description', "$product_name | Buy $product_name Online");

            // 🚀 Trigger Rank Math scoring
            if (function_exists('rank_math')) {
                rank_math()->meta->update_post_meta($product_id);
            }
        }

        // 📁 Save updated CSV
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
        echo "<div class='notice notice-success'><p><strong>Success!</strong> Region-based SEO content created. <a href='$download_url' target='_blank'>Download updated CSV</a></p></div>";
    }
}
