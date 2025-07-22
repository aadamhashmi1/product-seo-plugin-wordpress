<?php
add_filter('woocommerce_structured_data_product', 'add_custom_faq_howto_schema', 10, 2);

function add_custom_faq_howto_schema($markup, $product) {
    if (!$product || get_post_type($product->get_id()) !== 'product') return $markup;

    $content = $product->get_description(); // Optional: use get_short_description() if needed

    // 🧠 Extract FAQs
    preg_match_all('/<h3>(.*?)<\/h3>\s*<p>(.*?)<\/p>/is', $content, $faq_matches);
    $faq_entities = [];
    for ($i = 0; $i < count($faq_matches[1]); $i++) {
        $question = strip_tags(trim($faq_matches[1][$i]));
        $answer = strip_tags(trim($faq_matches[2][$i]));
        if ($question && $answer) {
            $faq_entities[] = [
                '@type' => 'Question',
                'name' => $question,
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => $answer]
            ];
        }
    }

    // 🛠️ Extract HowTo steps
    $howto_steps = [];
    if (preg_match('/<h2>.*?how to.*?<\/h2>(.*?)<h2>/is', $content . '<h2>', $howto_block)) {
        preg_match_all('/<li>(.*?)<\/li>/is', $howto_block[1], $steps);
        foreach ($steps[1] as $i => $step_text) {
            $howto_steps[] = [
                '@type' => 'HowToStep',
                'position' => $i + 1,
                'name' => strip_tags($step_text),
                'text' => strip_tags($step_text)
            ];
        }
    }

    // 🧩 Inject into WooCommerce's @graph structure
    if (!isset($markup['@graph'])) {
        $markup['@graph'] = [];
    }

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

    return $markup;
}
