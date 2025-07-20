<?php
/**
 * Plugin Name: AI Product Page Generator
 * Description: Upload a CSV of product names, generate unique SEO descriptions using Groq API, and publish product pages.
 * Version: 1.0
 * Author: Aadam & Copilot
 */

defined('ABSPATH') or die('No script kiddies please!');

// Load dependencies
require_once plugin_dir_path(__FILE__) . 'includes/admin-page.php';
require_once plugin_dir_path(__FILE__) . 'includes/groq-api.php';
