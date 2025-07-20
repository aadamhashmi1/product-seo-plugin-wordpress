<?php

function ai_generate_description($product_name, $api_key)
{
    $prompt = "Write a 2000-word, SEO-optimized, highly unique product description for a product named \"$product_name\". Include features, emotional storytelling, buyer benefits, and strong marketing tone.";

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
