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
                    <th>CSV File (Product Names)</th>
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
        $header[] = 'Description';

        $updated_rows = [];

        foreach ($rows as $row) {
            $product_name = $row[0];
            $description = ai_generate_description($product_name, $api_key);
            $row[] = $description;
            $updated_rows[] = $row;

            // Create WordPress page
            wp_insert_post([
                'post_title'   => $product_name,
                'post_content' => $description,
                'post_status'  => 'publish',
                'post_type'    => 'page'
            ]);
        }

        // Save updated CSV
        $upload_dir = plugin_dir_path(dirname(__FILE__)) . 'uploads/';
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
        echo "<div class='notice notice-success'><p><strong>Success!</strong> Pages created. <a href='$download_url' target='_blank'>Download updated CSV</a></p></div>";
    }
}
