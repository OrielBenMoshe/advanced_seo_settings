<?php
class Canonicals_Admin {
    private $plugin_path;
    private $plugin_url;
    private $logger;

    public function __construct() {
        global $advanced_seo_logger;
        $this->logger = $advanced_seo_logger;
        
        // Get the path to the includes/canonicals directory
        $this->plugin_path = plugin_dir_path(__FILE__);
        $this->plugin_url = plugin_dir_url(__FILE__);
        
        $this->logger->log("Plugin path: " . $this->plugin_path);
        $this->logger->log("Plugin URL: " . $this->plugin_url);
    }

    public function init() {
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
    }

    public function enqueue_admin_scripts($hook) {
        $this->logger->log("Enqueuing admin scripts for hook: " . $hook);

        // Only load on specific admin pages
        if (!in_array($hook, array('post.php', 'post-new.php', 'term.php'))) {
            return;
        }

        // Main canonical status script
        $canonical_status_path = 'js/canonical-status.js';
        $this->logger->log("Looking for canonical status script at: " . $this->plugin_path . $canonical_status_path);
        
        wp_enqueue_script(
            'advanced-seo-canonical-status',
            $this->plugin_url . $canonical_status_path,
            array('jquery', 'wp-plugins', 'wp-edit-post', 'wp-element', 'wp-components', 'wp-data', 'wp-hooks'),
            $this->get_file_version($this->plugin_path . $canonical_status_path),
            true
        );

        // Normalized URL display script
        $normalized_url_path = 'js/normalized-url-display.js';
        $this->logger->log("Looking for normalized URL script at: " . $this->plugin_path . $normalized_url_path);
        
        wp_enqueue_script(
            'advanced-seo-normalized-url',
            $this->plugin_url . $normalized_url_path,
            array('jquery'),
            $this->get_file_version($this->plugin_path . $normalized_url_path),
            true
        );

        // Add data for term pages
        if ($hook === 'term.php' && isset($_GET['taxonomy']) && isset($_GET['tag_ID'])) {
            $term_id = intval($_GET['tag_ID']);
            $taxonomy = sanitize_text_field($_GET['taxonomy']);
            $term_link = get_term_link($term_id, $taxonomy);

            if (!is_wp_error($term_link)) {
                wp_localize_script('advanced-seo-canonical-status', 'canonicalStatusData', array(
                    'termPermalinkUrl' => $term_link,
                    'isTermEdit' => true,
                    'taxonomyType' => $taxonomy,
                    'termId' => $term_id
                ));
            }
        }

        // Add post-specific data
        if (in_array($hook, array('post.php', 'post-new.php'))) {
            $post_id = isset($_GET['post']) ? intval($_GET['post']) : 0;
            if ($post_id) {
                wp_localize_script('advanced-seo-canonical-status', 'canonicalStatusData', array(
                    'postId' => $post_id,
                    'postType' => get_post_type($post_id),
                    'isPostEdit' => true
                ));
            }
        }
    }

    private function get_file_version($file_path) {
        if (file_exists($file_path)) {
            $version = filemtime($file_path);
            $this->logger->log("File version for {$file_path}: {$version}");
            return $version;
        }
        return '1.0.0';
    }
}