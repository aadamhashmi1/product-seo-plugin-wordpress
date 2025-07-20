<?php

function ai_generate_description($product_name, $api_key)
{
    $image_tag = "<img src='https://via.placeholder.com/800x600?text=" . urlencode($product_name) . "' alt='" . esc_attr($product_name) . "' style='width:100%;height:auto;margin-bottom:20px;' />";

    $prompt = <<<PROMPT
Write a clean, readable, SEO-optimized HTML article about "$product_name" that is approximately 2000 words long.

Requirements:
- Mention "$product_name" no more than 17 times
- Avoid keyword stuffing and repeated phrases
- Every paragraph should be unique and helpful
- Embed the following image at the top: $image_tag

Use the following structure:
- <h1> title with "$product_name"
- Introduction (<p>) explaining the product
- <h2> What Is "$product_name"?</h2>
- <h2> Benefits of "$product_name"</h2> — list 5–7 unique benefits
- <h2> How to Use "$product_name"</h2> — step-by-step guide
- <h2> Side Effects or Precautions</h2> — concise warnings
- <h2> Frequently Asked Questions</h2> — 8–10 tailored FAQs in <h3> + <p> format

Use diverse vocabulary. All writing must be clear, original, and formatted as valid HTML.
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
    $raw_content = $result['choices'][0]['message']['content'] ?? 'No content generated.';

    // Enforce keyword usage limit
    $max_occurrences = 17;
    $keyword_count = substr_count(strtolower($raw_content), strtolower($product_name));

    if ($keyword_count > $max_occurrences) {
        $pattern = '/' . preg_quote($product_name, '/') . '/i';
        $raw_content = preg_replace_callback($pattern, function ($match) use (&$max_occurrences) {
            return --$max_occurrences >= 0 ? $match[0] : 'the product';
        }, $raw_content);
    }

    return $raw_content;
}
