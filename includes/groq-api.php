<?php

function ai_generate_description($product_name, $api_key)
{
    $prompt = <<<PROMPT
Write a comprehensive, SEO-optimized HTML article of approximately 2000 words about the product "$product_name". 

The content must be completely unique — do not repeat structures, headings, or examples across different products. Vary the heading phrasing and ensure every product has its own distinct format, flow, and vocabulary. Do not reuse bullet point templates or FAQ questions across products.

Use HTML formatting:
- Begin with a unique <h1> title that includes "$product_name" in a creative and descriptive way.
- Structure the article with meaningful, keyword-rich headings using <h2><span data-preserver-spaces="true">, <h3>, and <p>.
- Include a strong introduction (2–3 sentences).
- Provide a detailed explanation of what "$product_name" is.
- Clearly list its benefits (5–7) using varied wording and formatting.
- Show how to use "$product_name" with step-by-step guidance (bullets or numbered list).
- Mention any precautions, warnings, or side effects in a separate section.
- Conclude with 8–10 unique FAQs, each in <h3> and <p>. Questions should differ per product and be tailored to the product’s real-world context.

Avoid repetition in language, tone, format, and structure. Use diverse keywords, emotional and functional hooks, and SEO techniques that suit "$product_name".

All content must be clean, readable, professional, and promotion-worthy.
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
