<?php

function ai_generate_description($product_name, $api_key)
{
    $site_locale = get_locale(); // e.g. 'en_US', 'fr_FR', 'ja_JP'

    $image_tag = "<img src='https://via.placeholder.com/800x600?text=" . urlencode($product_name) . "' alt='" . esc_attr($product_name) . "' style='width:100%;height:auto;margin-bottom:20px;' />";

    $prompt = <<<PROMPT
Write a clean, persuasive, SEO-optimized HTML article about "$product_name" for a $site_locale audience, approximately 2000 words long.

Requirements:
- Mention "$product_name" no more than 17 times
- Avoid repeated phrases and keyword stuffing
- Every paragraph must be culturally adapted and non-repetitive
- Embed this image at the top: $image_tag

Structure:
- <h1> SEO title including "$product_name"
- <p> Introduction tailored to regional buying behavior and emotional tone
- <h2> What Is "$product_name"?</h2> — regionally relevant description
- <h2> Benefits of "$product_name"</h2> — 5–7 distinct advantages, matching local values
- <h2> How to Use "$product_name"</h2> — usage patterns aligned with cultural norms
- <h2> Side Effects or Precautions</h2> — if applicable
- <h2> Frequently Asked Questions</h2> — 8–10 location-aware FAQs using <h3> and <p>

Ensure tone, examples, and wording reflect the interests, lifestyle, and language expectations common for $site_locale users. All writing must be valid HTML, SEO-friendly, and original.
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

    // Enforce keyword usage limit (max 17 mentions)
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
