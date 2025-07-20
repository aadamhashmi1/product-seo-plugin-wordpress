<?php

function ai_generate_description($product_name, $api_key)
{
    // Generate product image URL using Bing Image Creator or placeholder logic
    $image_tag = "<img src='https://via.placeholder.com/800x600?text=" . urlencode($product_name) . "' alt='" . esc_attr($product_name) . "' style='width:100%;height:auto;margin-bottom:20px;' />";

    $prompt = <<<PROMPT
Write a comprehensive, SEO-optimized HTML article of exactly 2000 words about the product "$product_name".

The content must be completely unique. Do not repeat structures, headings, or examples across products. Vary the headings and paragraph structures.

Use HTML formatting:
- After the first paragraph, insert this image tag: $image_tag
- Use <h1> for a custom, compelling title that includes "$product_name"
- Use <h2><span data-preserver-spaces="true"> and <h3> for headings
- Use <p> for readable, keyword-rich paragraphs
- Include a brief introduction, detailed benefits, how-to guide, side effects, and 8–10 custom FAQs
Dont repeat sentences and each description shuld be unique.and non repeating

All writing should be promotional, non-generic, and SEO-targeted for "$product_name".
PROMPT;

    $request_data = json_encode([
        'model' => 'llama3-70b-8192',
        'messages' => [
            ['role' => 'user', 'content' => $prompt]
        ]
    ]);

    $ch = curl_init("https://api.groq.com/openai/v1/chat/completions");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $api_key
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $request_data);

    $response = curl_exec($ch);
    curl_close($ch);

    $result = json_decode($response, true);
    return $result['choices'][0]['message']['content'] ?? 'No content generated.';
}
