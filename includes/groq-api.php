<?php

function ai_generate_description($product_name, $api_key, $region = 'Global')
{
    $image_tag = "<img src='https://via.placeholder.com/800x600?text=" . urlencode($product_name) . "' alt='" . esc_attr($product_name) . "' style='width:100%;height:auto;margin-bottom:20px;' />";
    
    $prompt = <<<PROMPT
Create a detailed, SEO-optimized HTML article (~2000 words) for "$product_name", written for customers in $region.

Include:
- No more than 17 mentions of "$product_name"
- No repeated phrases or keyword stuffing
- Embed this image at the top: $image_tag

Adapt content structure, vocabulary, emotional tone, benefits, and buying language to suit cultural expectations of $region consumers.

Structure:
- <h1> Product title
- <p> Regional intro
- <h2> What is "$product_name"?</h2>
- <h2> Benefits</h2> (5–7 points)
- <h2> How to Use</h2>
- <h2> Side Effects / Precautions</h2>
- <h2> Frequently Asked Questions</h2> (8–10 entries in <h3> + <p> format)

Exclude commentary like “Here is your article…” or “Let me know if you need changes.”
PROMPT;

    $request_data = json_encode([
        'model' => 'llama3-70b-8192',
        'messages' => [['role' => 'user', 'content' => $prompt]]
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

    // Clean AI lead-in and outro
    $clean_content = preg_replace('/^Here.*?:\s*/i', '', $raw_content);
    $clean_content = preg_replace('/I hope.*?modifications\./i', '', $clean_content);

    // Enforce keyword usage limit
    $max_occurrences = 17;
    $keyword_count = substr_count(strtolower($clean_content), strtolower($product_name));
    if ($keyword_count > $max_occurrences) {
        $pattern = '/' . preg_quote($product_name, '/') . '/i';
        $clean_content = preg_replace_callback($pattern, function ($match) use (&$max_occurrences) {
            return --$max_occurrences >= 0 ? $match[0] : 'the product';
        }, $clean_content);
    }

    return $clean_content;
}
