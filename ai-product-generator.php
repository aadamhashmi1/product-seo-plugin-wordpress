<?php
/**
 * Plugin Name: AI Product Page Generator
 * Description: Upload a CSV of product names, generate unique SEO descriptions using Groq API, and publish WooCommerce product pages.
 * Version: 1.0
 * Author: Aadam
 */

defined('ABSPATH') || exit;

// 🔧 Plugin Constants
define('AI_GEN_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('AI_GEN_PLUGIN_URL', plugin_dir_url(__FILE__));
define('AI_GEN_UPLOAD_DIR', AI_GEN_PLUGIN_DIR . 'uploads/');
define('AI_GEN_LOG_FILE', AI_GEN_UPLOAD_DIR . 'error-log.txt');

// 🚀 Activation Hook — Create Uploads Folder
register_activation_hook(__FILE__, 'ai_gen_activate');
function ai_gen_activate() {
    if (!file_exists(AI_GEN_UPLOAD_DIR)) {
        mkdir(AI_GEN_UPLOAD_DIR, 0755, true);
    }
}

// 📦 Load Core Components
foreach (['admin-page.php', 'groq-api.php', 'schema-hooks.php'] as $file) {
    $path = AI_GEN_PLUGIN_DIR . 'includes/' . $file;
    if (file_exists($path)) {
        require_once $path;
    }
}
