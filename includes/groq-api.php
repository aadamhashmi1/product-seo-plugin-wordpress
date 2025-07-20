<?php

function ai_generate_description($product_name, $api_key)
{
    $prompt = <<<PROMPT
Write a unique, high-quality, SEO-optimized product description of approximately 2000 words for a product called "$product_name".

Use HTML formatting:
- Start with <h1>$product_name</h1> as the main heading.
- Use <h2><span data-preserver-spaces="true"> and <h3> where needed for subheadings.
- Use <p> for content paragraphs.
- Add strong marketing language, product benefits, emotional appeals, use-cases, and buyer intent keywords.
- Include 8 to 10 relevant FAQs about the product at the end with each question in <h3> and its answer in <p>.
- Ensure each description is entirely unique and free of repeated filler or nonsense.
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
