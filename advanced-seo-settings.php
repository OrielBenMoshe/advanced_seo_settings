<?php
/**
 * Plugin Name: Advansed SEO Settings
 * Description: A plugin to add SEO settings to WordPress with tabs
 * Version: 1.0.0
 * Author: Oriel Ben-Moshe
 * Author URI: http://yourwebsite.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

// Prevent direct access to this file
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('ADVANCED_SEO_VERSION', '1.0.2');
define('ADVANCED_SEO_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('ADVANCED_SEO_PLUGIN_URL', plugin_dir_url(__FILE__));

// Include necessary files
require_once ADVANCED_SEO_PLUGIN_DIR . 'includes/class-advanced-seo-logger.php';

// Initialize Logger
global $advanced_seo_logger;
$advanced_seo_logger = new Advanced_SEO_Logger();

// Include tab files
require_once ADVANCED_SEO_PLUGIN_DIR . 'tabs/canonicals.php';
require_once ADVANCED_SEO_PLUGIN_DIR . 'tabs/alt_text.php';
require_once ADVANCED_SEO_PLUGIN_DIR . 'tabs/meta_description.php';

// Add menu item
function advanced_seo_add_admin_menu() {
    add_menu_page(
        'Advanced SEO Settings',
        'Advanced SEO Settings',
        'manage_options',
        'advanced_seo_settings',
        'advanced_seo_settings_page',
        'dashicons-admin-generic',
        3
    );
}
add_action('admin_menu', 'advanced_seo_add_admin_menu');

// Create settings page
function advanced_seo_settings_page() {
    global $advanced_seo_logger;
    $active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'canonicals';
    $advanced_seo_logger->log("Accessed settings page. Active tab: " . $active_tab);

    ?>
    <div class="wrap">
        <h1>Advanced SEO Settings</h1>
        <h2 class="nav-tab-wrapper">
            <a href="?page=advanced_seo_settings&tab=canonicals" class="nav-tab <?php echo $active_tab == 'canonicals' ? 'nav-tab-active' : ''; ?>">Canonicals</a>
            <a href="?page=advanced_seo_settings&tab=alt_text" class="nav-tab <?php echo $active_tab == 'alt_text' ? 'nav-tab-active' : ''; ?>">Alt Text</a>
            <a href="?page=advanced_seo_settings&tab=meta_description" class="nav-tab <?php echo $active_tab == 'meta_description' ? 'nav-tab-active' : ''; ?>">Meta Description</a>
        </h2>
        <form method="post" action="options.php">
            <?php
            if ($active_tab == 'canonicals') {
                advanced_seo_canonicals_settings();
            } elseif ($active_tab == 'alt_text') {
                advanced_seo_alt_text_settings();
            } elseif ($active_tab == 'meta_description') {
                advanced_seo_meta_description_settings();
            }
            ?>
        </form>
    </div>
    <?php
}

// Register settings
function advanced_seo_settings_init() {
    advanced_seo_canonicals_init();
    advanced_seo_alt_text_init();
    advanced_seo_meta_description_init();
}
add_action('admin_init', 'advanced_seo_settings_init');

// Enqueue admin scripts and styles
function advanced_seo_admin_scripts() {
    wp_enqueue_style('advanced-seo-admin-style', plugins_url('admin-style.css', __FILE__));
}
add_action('admin_enqueue_scripts', 'advanced_seo_admin_scripts');


// This function should be added to ensure the options are saved correctly
function advanced_seo_canonicals_save_settings($options) {
    error_log('Debug - Saving options: ' . print_r($options, true));

    // וודא שpost_types ו-all_taxonomies הם תמיד מערכים
    if (!isset($options['post_types']) || !is_array($options['post_types'])) {
        $options['post_types'] = array();
    }
    if (!isset($options['all_taxonomies']) || !is_array($options['all_taxonomies'])) {
        $options['all_taxonomies'] = array();
    }

    // הסר פריטים לא מסומנים
    $options['post_types'] = array_filter($options['post_types']);
    $options['all_taxonomies'] = array_filter($options['all_taxonomies']);

    error_log('Debug - Filtered options: ' . print_r($options, true));

    return $options;
}
add_filter('pre_update_option_advanced_seo_canonicals', 'advanced_seo_canonicals_save_settings');