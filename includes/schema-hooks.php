<?php
// 📦 Optional: Add custom schema or metadata for AI-generated products

add_filter('rank_math/json_ld', 'ai_gen_add_schema', 20, 2);

function ai_gen_add_schema($data, $jsonld) {
    if (is_singular('product')) {
        $product_id = get_the_ID();
        $focus_keyword = get_post_meta($product_id, 'rank_math_focus_keyword', true);

        if ($focus_keyword) {
            $data['@type'] = 'Product';
            $data['name'] = get_the_title($product_id);
            $data['description'] = get_the_excerpt($product_id);
            $data['sku'] = get_post_meta($product_id, '_sku', true);
            $data['brand'] = ['@type' => 'Brand', 'name' => 'AI Generated'];
        }
    }

    return $data;
}
