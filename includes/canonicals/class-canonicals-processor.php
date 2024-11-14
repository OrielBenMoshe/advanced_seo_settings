<?php
use WP_Query;
/**
 * Class Canonicals_Processor
 *
 * Handles the processing of canonical URLs for both posts and taxonomies.
 * This includes auto-filling, updating, and deleting canonical URLs.
 */
class Canonicals_Processor
{
    private $batch_size = 200;
    private $logger;

    public function __construct()
    {
        global $advanced_seo_logger;
        $this->logger = $advanced_seo_logger;
    }

    private function count_empty_canonicals($post_types, $taxonomies) {
        $total_empty_canonicals = 0;  // שינוי שם המשתנה
    
        // ספירת פוסטים עם קנוניקל ריק
        foreach ($post_types as $post_type => $enabled) {
            if ($enabled == '1') {
                $args = array(
                    'post_type' => $post_type,
                    'posts_per_page' => -1,
                    'fields' => 'ids',
                );
                $posts = get_posts($args);
                
                foreach ($posts as $post_id) {
                    $meta_key = $this->get_meta_key(false);
                    $current_canonical = get_post_meta($post_id, $meta_key, true);
                    if (empty($current_canonical)) {
                        $total_empty_canonicals++;
                    }
                }
            }
        }
    
        // ספירת טקסונומיות עם קנוניקל ריק
        foreach ($taxonomies as $taxonomy => $enabled) {
            if ($enabled == '1') {
                $terms = get_terms([
                    'taxonomy' => $taxonomy,
                    'hide_empty' => false,
                    'fields' => 'ids'
                ]);
    
                foreach ($terms as $term_id) {
                    if (defined('WPSEO_VERSION') && class_exists('WPSEO_Taxonomy_Meta')) {
                        // בדיקה ליוסט
                        $current_canonical = WPSEO_Taxonomy_Meta::get_term_meta($term_id, $taxonomy, 'canonical');
                    } else {
                        // בדיקה רגילה
                        $meta_key = $this->get_meta_key(true);
                        $current_canonical = get_term_meta($term_id, $meta_key, true);
                    }
                    
                    if (empty($current_canonical)) {
                        $total_empty_canonicals++;
                    }
                }
            }
        }
    
        return $total_empty_canonicals;
    }




    
    /**
     * Auto-fill canonical URLs for selected post types and taxonomies.
     * This method is typically called via AJAX.
     */
  

     public function auto_fill_canonicals()
     {
        try {
            // Start session if not started
            if (!session_id()) {
                session_start();
            }
     
            check_ajax_referer('canonicals_nonce', 'security');
            if (!current_user_can('manage_options')) {
                throw new Exception('Unauthorized user');
            }
     
            $post_types = isset($_POST['post_types']) ? $_POST['post_types'] : [];
            $taxonomies = isset($_POST['taxonomies']) ? $_POST['taxonomies'] : [];
     
            // Initialize session variables
            if (isset($_POST['count_only']) && $_POST['count_only'] === 'true') {
                // Reset session variables
                $_SESSION['processed_taxonomies'] = [];
                $_SESSION['last_taxonomy_id'] = 0;
                $_SESSION['processed_post_types'] = [];
                $_SESSION['last_post_id'] = 0;
                $_SESSION['total_processed'] = 0;
     
                $total_empty_canonicals = $this->count_empty_canonicals($post_types, $taxonomies);
                $_SESSION['total_items'] = $total_empty_canonicals;
                
                wp_send_json_success([
                    'total_empty_canonicals' => $total_empty_canonicals,
                    'message' => sprintf('נמצאו %d פריטים ללא קנוניקל שיושפעו מהפעולה.', $total_empty_canonicals)
                ]);
                return;
            }
     
            // Get session variables
            $processed_taxonomies = &$_SESSION['processed_taxonomies'];
            $last_taxonomy_id = &$_SESSION['last_taxonomy_id'];
            $processed_post_types = &$_SESSION['processed_post_types'];
            $last_post_id = &$_SESSION['last_post_id'];
            $total_processed = &$_SESSION['total_processed'];
     
            $batch_updated = 0;
            $is_complete = false;
     
            // Process taxonomies first
            foreach ($taxonomies as $taxonomy => $enabled) {
                if ($enabled != '1' || in_array($taxonomy, $processed_taxonomies)) {
                    continue;
                }
     
                $args = [
                    'taxonomy' => $taxonomy,
                    'hide_empty' => false,
                    'fields' => 'ids',
                    'number' => $this->batch_size,
                    'offset' => $last_taxonomy_id,
                    'orderby' => 'id',
                    'order' => 'ASC'
                ];
     
                $terms = get_terms($args);
     
                if (empty($terms)) {
                    $processed_taxonomies[] = $taxonomy;
                    $last_taxonomy_id = 0;
                    continue;
                }
     
                foreach ($terms as $term_id) {
                    $canonical_url = get_term_link($term_id, $taxonomy);
                    if (!is_wp_error($canonical_url)) {
                        $batch_updated += $this->update_canonical_url($term_id, $canonical_url, true, $taxonomy);
                    }
                }
     
                $last_taxonomy_id = end($terms);
                break;
            }
     
            // Process post types after taxonomies
            if (count($processed_taxonomies) === count(array_filter($taxonomies))) {
                foreach ($post_types as $post_type => $enabled) {
                    if ($enabled != '1' || in_array($post_type, $processed_post_types)) {
                        continue;
                    }
     
                    $args = [
                        'post_type' => $post_type,
                        'posts_per_page' => $this->batch_size,
                        'offset' => $last_post_id,
                        'fields' => 'ids',
                        'orderby' => 'ID',
                        'order' => 'ASC'
                    ];
     
                    $query = new WP_Query($args);
     
                    if (empty($query->posts)) {
                        $processed_post_types[] = $post_type;
                        $last_post_id = 0;
                        continue;
                    }
     
                    foreach ($query->posts as $post_id) {
                        $batch_updated += $this->update_canonical_url($post_id, get_permalink($post_id), false);
                    }
     
                    $last_post_id = end($query->posts);
                    break;
                }
            }
     
            $total_processed += $batch_updated;
            $is_complete = (
                count($processed_taxonomies) === count(array_filter($taxonomies)) && 
                count($processed_post_types) === count(array_filter($post_types))
            );
     
            if ($is_complete) {
                // Clear session variables
                $_SESSION['processed_taxonomies'] = [];
                $_SESSION['last_taxonomy_id'] = 0;
                $_SESSION['processed_post_types'] = [];
                $_SESSION['last_post_id'] = 0;
                $_SESSION['total_processed'] = 0;
                $_SESSION['total_items'] = 0;
            }
     
            wp_send_json_success([
                'message' => sprintf('עודכנו %d שדות קנוניים מתוך %d.', $total_processed, $_SESSION['total_items']),
                'updated_count' => $batch_updated,
                'total_processed' => $total_processed,
                'total_items' => $_SESSION['total_items'],
                'is_complete' => $is_complete
            ]);
     
        } catch (Exception $e) {
            $this->logger->log("Error: " . $e->getMessage());
            wp_send_json_error($e->getMessage());
        }
     }



    /**
     * Process taxonomies for canonical URL updates.
     *
     * @param array $taxonomies Array of taxonomies to process
     * @return array Count of updated items
     */
    private function process_taxonomies($taxonomies)
    {
        $updated_count = 0;
        foreach ($taxonomies as $taxonomy => $enabled) {
            if ($enabled != '1' || $updated_count >= $this->batch_size) {
                continue;
            }

            $terms = get_terms([
                'taxonomy' => $taxonomy,
                'hide_empty' => false,
                'fields' => 'ids',
                'number' => $this->batch_size - $updated_count
            ]);

            foreach ($terms as $term_id) {
                $canonical_url = get_term_link($term_id, $taxonomy);
                if (!is_wp_error($canonical_url)) {
                    $updated_count += $this->update_canonical_url($term_id, $canonical_url, true, $taxonomy);
                }
            }
        }
        return ['count' => $updated_count];
    }

    /**
     * Process post types for canonical URL updates.
     *
     * @param array $post_types Array of post types to process
     * @param int $limit Maximum number of posts to process
     * @return array Count of updated items
     */
    private function process_post_types($post_types, $limit)
    {
        $updated_count = 0;
        foreach ($post_types as $post_type => $enabled) {
            if ($enabled != '1' || $updated_count >= $limit) {
                continue;
            }

            $query = new WP_Query([
                'post_type' => $post_type,
                'posts_per_page' => $limit - $updated_count,
                'fields' => 'ids',
            ]);

            foreach ($query->posts as $post_id) {
                $updated_count += $this->update_canonical_url($post_id, get_permalink($post_id), false);
            }
        }
        return ['count' => $updated_count];
    }

    /**
     * Delete canonical URLs for specified post types.
     *
     * @param array $post_types Array of post types to process
     * @param int $limit Maximum number of posts to process
     * @return array Count of deleted and checked items
     */
    public function delete_default_canonicals()
    {
        check_ajax_referer('canonicals_nonce', 'security');
        if (!current_user_can('manage_options')) {
            $this->logger->log("Unauthorized user attempted to delete default canonicals");
            wp_send_json_error('Unauthorized user');
        }

        $post_types = isset($_POST['post_types']) ? $_POST['post_types'] : [];
        $taxonomies = isset($_POST['taxonomies']) ? $_POST['taxonomies'] : [];

        $this->logger->log("Received post types for deletion: " . print_r($post_types, true));
        $this->logger->log("Received taxonomies for deletion: " . print_r($taxonomies, true));

        if (empty($post_types) && empty($taxonomies)) {
            $this->logger->log("No content types or taxonomies selected.");
            wp_send_json_error('No content types or taxonomies selected.');
        }

        $total_deleted = 0;
        $total_checked = 0;
        $is_complete = false;

        $deleted_taxonomies = $this->delete_taxonomy_canonicals($taxonomies);
        $total_deleted += $deleted_taxonomies['count'];
        $total_checked += $deleted_taxonomies['checked'];

        $this->logger->log("Deleted taxonomy canonicals: {$deleted_taxonomies['count']} out of {$deleted_taxonomies['checked']} checked");

        if ($deleted_taxonomies['checked'] < $this->batch_size) {
            $remaining = $this->batch_size - $deleted_taxonomies['checked'];
            $deleted_posts = $this->delete_post_type_canonicals($post_types, $remaining);
            $total_deleted += $deleted_posts['count'];
            $total_checked += $deleted_posts['checked'];

            $this->logger->log("Deleted post canonicals: {$deleted_posts['count']} out of {$deleted_posts['checked']} checked");

            if ($deleted_posts['checked'] < $remaining) {
                $is_complete = true;
            }
        }

        $this->logger->log("Total deleted: $total_deleted, Total checked: $total_checked, Is complete: " . ($is_complete ? "Yes" : "No"));

        wp_send_json_success([
            'message' => sprintf('נמחקו %d שדות קנוניים המוגדרים כברירת מחדל. %d שדות כבר היו ריקים או שלא היה צורך במחיקה.', $total_deleted, $total_checked - $total_deleted),
            'deleted_count' => $total_deleted,
            'checked_count' => $total_checked,
            'is_complete' => $is_complete
        ]);
    }

    /**
     * Delete canonical URLs for specified taxonomies.
     *
     * @param array $taxonomies Array of taxonomies to process
     * @return array Count of deleted and checked items
     */
    private function delete_taxonomy_canonicals($taxonomies)
    {
        $deleted_count = 0;
        $checked_count = 0;
        foreach ($taxonomies as $taxonomy => $enabled) {
            if ($enabled != '1' || $checked_count >= $this->batch_size) {
                continue;
            }

            $terms = get_terms([
                'taxonomy' => $taxonomy,
                'hide_empty' => false,
                'fields' => 'id=>slug',
                'number' => $this->batch_size - $checked_count
            ]);

            foreach ($terms as $term_id => $term_slug) {
                $permalink = get_term_link($term_id, $taxonomy);
                if (!is_wp_error($permalink)) {
                    // העברת ה-taxonomy כפרמטר
                    $result = $this->delete_default_canonical($term_id, $permalink, true, $taxonomy);
                    $deleted_count += $result;
                    $checked_count++;
                }
            }
        }
        return ['count' => $deleted_count, 'checked' => $checked_count];
    }

    /**
 * Delete canonical URLs for specified post types.
 *
 * @param array $post_types Array of post types to process
 * @param int $limit Maximum number of posts to process
 * @return array Count of deleted and checked items
 */
    private function delete_post_type_canonicals($post_types, $limit)
    {
        $deleted_count = 0;
        $checked_count = 0;

        foreach ($post_types as $post_type => $enabled) {
            if ($enabled != '1' || $checked_count >= $limit) {
                continue;
            }

            $query = new WP_Query([
                'post_type' => $post_type,
                'posts_per_page' => $limit - $checked_count,
                'fields' => 'ids',
            ]);

            foreach ($query->posts as $post_id) {
                $permalink = get_permalink($post_id);
                if ($permalink) {
                    $result = $this->delete_default_canonical($post_id, $permalink, false);
                    $deleted_count += $result;
                    $checked_count++;
                }
            }
        }

        return [
            'count' => $deleted_count,
            'checked' => $checked_count
        ];
    }

    /**
     * Update canonical URL for a specific post or term.
     *
     * @param int $id Post ID or Term ID
     * @param string $canonical_url The canonical URL to set
     * @param bool $is_term Whether this is a term or a post
     * @param string $taxonomy The taxonomy (only for terms)
     * @return int 1 if updated, 0 if not
     */
    private function update_canonical_url($id, $canonical_url, $is_term = false, $taxonomy = '')
    {
        // טיפול מיוחד במונחים של Yoast
        if ($is_term && defined('WPSEO_VERSION') && class_exists('WPSEO_Taxonomy_Meta')) {
            $current_canonical = WPSEO_Taxonomy_Meta::get_term_meta($id, $taxonomy, 'canonical');
            // שינוי כאן: בודקים רק אם השדה ריק
            if (empty($current_canonical)) {
                WPSEO_Taxonomy_Meta::set_value($id, $taxonomy, 'canonical', $canonical_url);
                return 1;
            }
            return 0;
        }

        // טיפול רגיל בפוסטים או מונחים אחרים
        $meta_key = $this->get_meta_key($is_term);
        $current_canonical = $is_term ? get_term_meta($id, $meta_key, true) : get_post_meta($id, $meta_key, true);

        // שינוי כאן: בודקים רק אם השדה ריק
        if (empty($current_canonical)) {
            $update_function = $is_term ? 'update_term_meta' : 'update_post_meta';
            return $update_function($id, $meta_key, $canonical_url) ? 1 : 0;
        }

        return 0;
    }

    /**
     * Delete default canonical URL for a specific post or term.
     *
     * @param int $id Post ID or Term ID
     * @param string $permalink The current permalink of the post or term
     * @param bool $is_term Whether this is a term or a post
     * @return int 1 if deleted, 0 if not
     */
    private function delete_default_canonical($id, $permalink, $is_term = false, $taxonomy = '')
    {
        $this->logger->log("Attempting to delete canonical for " . ($is_term ? "term" : "post") . " $id");

        // בדיקה אם זו טקסונומיה ויש Yoast SEO
        if ($is_term && defined('WPSEO_VERSION') && class_exists('WPSEO_Taxonomy_Meta')) {
            $this->logger->log("Checking Yoast term canonical");

            // השגת ערך הקנוניקל באמצעות WPSEO_Taxonomy_Meta
            $current_canonical = WPSEO_Taxonomy_Meta::get_term_meta($id, $taxonomy, 'canonical');
            $this->logger->log("Current Yoast canonical value: " . print_r($current_canonical, true));

            if (empty($current_canonical)) {
                $this->logger->log("Current canonical is empty. No deletion needed.");
                return 0;
            }

            $normalized_current = $this->normalize_url($current_canonical);
            $normalized_permalink = $this->normalize_url($permalink);
            $this->logger->log("Normalized current: $normalized_current");
            $this->logger->log("Normalized permalink: $normalized_permalink");

            if ($normalized_current === $normalized_permalink) {
                // מחיקת הקנוניקל באמצעות WPSEO_Taxonomy_Meta
                WPSEO_Taxonomy_Meta::set_value($id, $taxonomy, 'canonical', '');
                $this->logger->log("Yoast term canonical deleted successfully");
                return 1;
            }

            $this->logger->log("Canonical doesn't match permalink. No deletion needed.");
            return 0;
        }

        // טיפול בפוסטים או פלאגינים אחרים
        $meta_key = $this->get_meta_key($is_term);
        $this->logger->log("Using meta key: $meta_key");

        if (empty($meta_key)) {
            $this->logger->log("Meta key is empty. Skipping.");
            return 0;
        }

        $current_canonical = $is_term ? get_term_meta($id, $meta_key, true) : get_post_meta($id, $meta_key, true);
        $this->logger->log("Current canonical: " . print_r($current_canonical, true));

        if (empty($current_canonical)) {
            $this->logger->log("Current canonical is empty. No deletion needed.");
            return 0;
        }

        $normalized_current = $this->normalize_url($current_canonical);
        $normalized_permalink = $this->normalize_url($permalink);

        if ($normalized_current === $normalized_permalink) {
            $delete_function = $is_term ? 'delete_term_meta' : 'delete_post_meta';
            $result = $delete_function($id, $meta_key);
            $this->logger->log("Deletion " . ($result ? "successful" : "failed"));
            return $result ? 1 : 0;
        }

        $this->logger->log("Canonical doesn't match permalink. No deletion needed.");
        return 0;
    }

    /**
     * Normalize a URL for comparison.
     *
     * @param string $url The URL to normalize
     * @return string The normalized URL
     */
    private function normalize_url($url)
    {
        $this->logger->log("Normalizing URL: $url");
        if (empty($url)) {
            $this->logger->log("URL is empty, returning empty string");
            return '';
        }
        $normalized = $url;
        $normalized = preg_replace('(^https?://)', '', $normalized);
        $normalized = preg_replace('/^www\./', '', $normalized);
        $normalized = rtrim($normalized, '/');
        $normalized = strtolower($normalized);
        $normalized = urldecode($normalized);
        $normalized = preg_replace_callback('/%[0-9A-F]{2}/i', function ($match) {
            return chr(hexdec($match[0]));
        }, $normalized);
        $this->logger->log("Normalized URL: $normalized");
        return $normalized;
    }

    /**
     * Get the appropriate meta key for canonical URLs based on the SEO plugin in use.
     *
     * @param bool $is_term Whether this is for a term or a post
     * @return string The meta key to use
     */
    private function get_meta_key($is_term)
    {
        $meta_key = '';
        if (defined('WPSEO_VERSION')) {
            $meta_key = $is_term ? 'wpseo_canonical' : '_yoast_wpseo_canonical';
        } elseif (class_exists('RankMath')) {
            $meta_key = 'rank_math_canonical_url';
        } elseif ($is_term) {
            $meta_key = 'custom_canonical_url';
        } else {
            $meta_key = 'canonical_url';
        }
        $this->logger->log("Meta key determined: $meta_key for " . ($is_term ? "term" : "post"));
        return $meta_key;
    }
}
