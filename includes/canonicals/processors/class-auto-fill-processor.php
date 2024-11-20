<?php

/**
 * מעבד למילוי אוטומטי של קנוניקלים
 * 
 * אחראי על מילוי אוטומטי של כתובות קנוניקליות עבור פוסטים וטקסונומיות
 */
class Auto_Fill_Processor {
    use Meta_Handler;

    private $batch_size;
    private $logger;

    /**
     * אתחול המעבד עם לוגר וגודל אצווה
     */
    public function __construct($logger, $batch_size) {
        $this->logger = $logger;
        $this->batch_size = $batch_size;
    }

    /**
     * מתודה ראשית לעיבוד ומילוי קנוניקלים
     */
    public function process() {
        try {
            // בדיקת אבטחה
            check_ajax_referer('canonicals_nonce', 'security');
            if (!current_user_can('manage_options')) {
                throw new Exception('משתמש לא מורשה');
            }

            // קבלת הנתונים מהבקשה
            $post_types = isset($_POST['post_types']) ? $_POST['post_types'] : [];
            $taxonomies = isset($_POST['taxonomies']) ? $_POST['taxonomies'] : [];

            $this->logger->log("סוגי פוסטים שהתקבלו: " . print_r($post_types, true));
            $this->logger->log("טקסונומיות שהתקבלו: " . print_r($taxonomies, true));

            if (empty($post_types) && empty($taxonomies)) {
                throw new Exception('לא נבחרו סוגי תוכן או טקסונומיות.');
            }

            // עיבוד הנתונים
            $total_updated = 0;
            $is_complete = false;

            // טיפול בטקסונומיות
            $updated_taxonomies = $this->process_taxonomies($taxonomies);
            $total_updated += $updated_taxonomies['count'];

            // טיפול בפוסטים אם נשאר מקום באצווה
            if ($updated_taxonomies['count'] < $this->batch_size) {
                $remaining = $this->batch_size - $updated_taxonomies['count'];
                $updated_posts = $this->process_post_types($post_types, $remaining);
                $total_updated += $updated_posts['count'];

                if ($updated_posts['count'] < $remaining) {
                    $is_complete = true;
                }
            }

            // החזרת תשובה
            wp_send_json_success([
                'message' => sprintf('עודכנו %d שדות קנוניים.', $total_updated),
                'updated_count' => $total_updated,
                'is_complete' => $is_complete
            ]);

        } catch (Exception $e) {
            $this->logger->log("שגיאה במילוי אוטומטי: " . $e->getMessage());
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * עיבוד טקסונומיות ועדכון כתובות קנוניקליות
     */
    private function process_taxonomies($taxonomies) {
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
     * עיבוד סוגי פוסטים ועדכון כתובות קנוניקליות
     */
    private function process_post_types($post_types, $limit) {
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
     * עדכון כתובת קנונית עבור פוסט או טקסונומיה ספציפיים
     */
    private function update_canonical_url($id, $canonical_url, $is_term = false, $taxonomy = '') {
        // טיפול מיוחד במונחים של Yoast
        if ($is_term && defined('WPSEO_VERSION') && class_exists('WPSEO_Taxonomy_Meta')) {
            $current_canonical = WPSEO_Taxonomy_Meta::get_term_meta($id, $taxonomy, 'canonical');
            if (empty($current_canonical)) {
                WPSEO_Taxonomy_Meta::set_value($id, $taxonomy, 'canonical', $canonical_url);
                return 1;
            }
            return 0;
        }

        // טיפול רגיל בפוסטים או מונחים
        $meta_key = $this->get_meta_key($is_term);
        $current_canonical = $is_term ? get_term_meta($id, $meta_key, true) : get_post_meta($id, $meta_key, true);

        if (empty($current_canonical)) {
            $update_function = $is_term ? 'update_term_meta' : 'update_post_meta';
            return $update_function($id, $meta_key, $canonical_url) ? 1 : 0;
        }

        return 0;
    }
}