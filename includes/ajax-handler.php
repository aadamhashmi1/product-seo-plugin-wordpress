<?php
add_action('wp_ajax_generate_ai_product', function () {
    $product_id = intval($_POST['product_id']);
    if (!$product_id) wp_send_json_error(['message' => 'Missing product ID']);

    $product = wc_get_product($product_id);
    if (!$product) wp_send_json_error(['message' => 'Invalid product']);

    require_once plugin_dir_path(__FILE__) . '/groq-api.php';
    $name = $product->get_name();
    $region = 'Global';
    $api_key = 'YOUR_API_KEY'; // Replace this securely

    $start = microtime(true);
    $html = ai_generate_description($name, $api_key, $region);

    wp_update_post(['ID' => $product_id, 'post_content' => $html]);

    $duration = round(microtime(true) - $start, 2);
    wp_send_json_success(['duration' => $duration]);
});
