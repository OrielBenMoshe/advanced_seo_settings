<?php
require_once plugin_dir_path(__FILE__) . '../includes/canonicals/class-canonicals-settings.php';
require_once plugin_dir_path(__FILE__) . '../includes/canonicals/class-canonicals-processor.php';
require_once plugin_dir_path(__FILE__) . '../includes/canonicals/class-canonicals-admin.php';

// Ensure the logger is available
global $advanced_seo_logger;

// Initialize the classes with the logger
$canonicals_settings = new Canonicals_Settings($advanced_seo_logger);
$canonicals_processor = new Canonicals_Processor($advanced_seo_logger);
$canonicals_admin = new Canonicals_Admin($advanced_seo_logger);

function advanced_seo_canonicals_init() {
    global $canonicals_settings, $advanced_seo_logger;
    // $advanced_seo_logger->log("Initializing Advanced SEO Canonicals");
    $canonicals_settings->init();
}

add_action('admin_init', 'advanced_seo_canonicals_init');
add_action('wp_ajax_auto_fill_canonicals', array($canonicals_processor, 'auto_fill_canonicals'));
add_action('wp_ajax_delete_default_canonicals', array($canonicals_processor, 'delete_default_canonicals'));
add_action('admin_init', array($canonicals_admin, 'init'));

function advanced_seo_canonicals_settings() {
    global $canonicals_settings, $advanced_seo_logger;
    $advanced_seo_logger->log("Rendering Advanced SEO Canonicals settings page");
    $canonicals_settings->render_settings_page();
}