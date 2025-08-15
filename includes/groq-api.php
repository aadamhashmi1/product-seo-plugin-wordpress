<?php

function ai_generate_description($product_name, $api_key, $region = 'Global') {
    $moderated_terms = [
        'cigarette' => 'adult wellness product',
        'vape' => 'adult inhaler device',
        'hookah' => 'cultural smoking pipe',
        'nicotine' => 'substance-based inhaler',
        'alcohol' => 'adult beverage',
        'whiskey' => 'distilled spirits',
        'lingerie' => 'intimate apparel',
        'condom' => 'safety product',
        'vibrator' => 'personal wellness device',
        'sex toy' => 'private health accessory'
    ];

    $safe_name = $product_name;
    foreach ($moderated_terms as $term => $replacement) {
        if (stripos($product_name, $term) !== false) {
            $safe_name = str_ireplace($term, $replacement, $product_name);
            break;
        }
    }

    $normalized_keyword = preg_replace('/[^a-zA-Z0-9 ]/', '', $product_name);
    $image_tag = "<img src='https://via.placeholder.com/800x600?text=" . urlencode($safe_name) . "' alt='" . esc_attr($safe_name) . "' style='width:100%;height:auto;margin-bottom:20px;' />";

    $base_prompt = <<<PROMPT
You are a professional product copywriter. Create a detailed, persuasive, SEO-optimized HTML article about "$safe_name" tailored for buyers in $region. It should be exactly 2000 words long.

Requirements:
- Must be written in English
- Use correct HTML: <h1>, <h2>, <h3>, <p>, <ul>, <li>
- Embed this image at the top: $image_tag
- Mention "$safe_name" exactly 13 times — no more, no less
- Avoid keyword stuffing, repetition, or filler language
- Include exactly 10 FAQs with <h3> + <p> tags

Structure:
<h1> Product Title
<p> Introduction explaining "$safe_name" to buyers in $region region(s), highlighting cultural preferences or market trends if relevant.

<h2> What is "$safe_name"?
<h2> Benefits — 5 to 7 points
<h2> How to Use
<h2> Precautions or Side Effects
<h2> Frequently Asked Questions — 10 entries

Only generate production-ready HTML content. No preamble or closing remarks.
PROMPT;

    $max_attempts = 3;
    $attempt = 0;
    $valid = false;
    $clean_content = '';
    $error_log = [];

    while (!$valid && $attempt < $max_attempts) {
        $attempt++;
        $delay = pow(2, $attempt);

        $prompt = $base_prompt;
        if ($attempt > 1) {
            $prompt .= "\n\nFix language, HTML structure, and keyword balance. Keep it clean and focused.";
        }

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
        $raw = $result['choices'][0]['message']['content'] ?? '';

        $raw = preg_replace('/^Here.*?:\s*/i', '', $raw);
        $raw = preg_replace('/I hope.*?modifications\./i', '', $raw);

        $is_long = strlen(trim($raw)) > 600;
        $has_h1 = preg_match('/<h1>.*<\/h1>/i', $raw);
        $is_english = preg_match('/[A-Za-z]{5,}/', $raw);
        $faq_count = substr_count(strtolower($raw), '<h3>');
        $has_faqs = $faq_count >= 8;

        if ($is_long && $has_h1 && $is_english && $has_faqs) {
            $valid = true;
            $clean_content = $raw;
        } else {
            $error_log[] = "Attempt $attempt failed for '$product_name':"
                . (!$is_long ? " Too short;" : "")
                . (!$has_h1 ? " Missing <h1>;" : "")
                . (!$is_english ? " Not English;" : "")
                . (!$has_faqs ? " Only $faq_count FAQs;" : "")
                . " Waiting {$delay}s...";
            sleep($delay);
        }
    }

    if (!$valid) {
        $clean_content = "<p><strong>Error:</strong> Failed to generate content for '$product_name' after $max_attempts attempts.</p>";
        $error_log[] = "❌ Final failure for '$product_name'.";
    }

    // 🎯 Enforce keyword mention count
    $target_count = 13;
    $pattern = '/' . preg_quote($normalized_keyword, '/') . '/i';
    $current_count = substr_count(strtolower($clean_content), strtolower($normalized_keyword));

    if ($current_count > $target_count) {
        $count = 0;
        $clean_content = preg_replace_callback($pattern, function ($match) use (&$count, $target_count) {
            return (++$count <= $target_count) ? $match[0] : 'the product';
        }, $clean_content);
    } elseif ($current_count < $target_count) {
        $needed = $target_count - $current_count;
        for ($i = 0; $i < $needed; $i++) {
            $clean_content .= "<p>{$normalized_keyword} is popular among $region buyers for its style and performance.</p>";
        }
    }

    $final_count = substr_count(strtolower($clean_content), strtolower($normalized_keyword));
    if ($final_count !== 13) {
        $error_log[] = "⚠️ Final count for '$product_name' was $final_count instead of 13.";
    }

    if (!empty($error_log)) {
        if (!file_exists(AI_GEN_UPLOAD_DIR)) {
            mkdir(AI_GEN_UPLOAD_DIR, 0755, true);
        }
        file_put_contents(AI_GEN_LOG_FILE, implode("\n", $error_log) . "\n", FILE_APPEND);
    }

    return $clean_content;
}
