<?php
add_filter('woocommerce_structured_data_product', 'add_custom_faq_howto_schema', 10, 2);
add_filter('rank_math/snippet/rich_snippet_product', function ($data, $post_id) {
    // Inject custom FAQ or HowTo schema if needed
    return $data; // or merge your custom schema here
}, 99, 2);

function add_custom_faq_howto_schema($markup, $product) {
    if (!$product || get_post_type($product->get_id()) !== 'product') return $markup;

    // Get full product content (use description or AI-generated field)
    $content = $product->get_description(); 

    // 🔎 Extract FAQ entries
    preg_match_all('/<h3>(.*?)<\/h3>\s*<p>(.*?)<\/p>/is', $content, $faq_matches);
    $faq_entities = [];
    for ($i = 0; $i < count($faq_matches[1]); $i++) {
        $q = strip_tags(trim($faq_matches[1][$i]));
        $a = strip_tags(trim($faq_matches[2][$i]));
        if ($q && $a) {
            $faq_entities[] = [
                '@type' => 'Question',
                'name' => $q,
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => $a]
            ];
        }
    }

    // 🔧 Extract HowTo steps
    $howto_steps = [];
    if (preg_match('/<h2>.*?how to.*?<\/h2>(.*?)<h2>/is', $content . '<h2>', $howto_block)) {
        preg_match_all('/<li>(.*?)<\/li>/is', $howto_block[1], $step_matches);
        foreach ($step_matches[1] as $i => $step_text) {
            $howto_steps[] = [
                '@type' => 'HowToStep',
                'position' => $i + 1,
                'name' => strip_tags($step_text),
                'text' => strip_tags($step_text)
            ];
        }
    }

    // ✅ Merge into WooCommerce's @graph structure
    if (!isset($markup['@context'])) {
        $markup['@context'] = 'https://schema.org';
    }

    if (!isset($markup['@graph'])) {
        $markup['@graph'] = [];
    }

    // Only inject if base Product schema already exists
    if (is_array($markup['@graph']) && count($markup['@graph']) > 0) {
        if (count($faq_entities) >= 3) {
            $markup['@graph'][] = [
                '@type' => 'FAQPage',
                'mainEntity' => $faq_entities
            ];
        }

        if (count($howto_steps) >= 2) {
            $markup['@graph'][] = [
                '@type' => 'HowTo',
                'name' => $product->get_title() . ' Usage Guide',
                'step' => $howto_steps
            ];
        }
    }

    return $markup;
}
