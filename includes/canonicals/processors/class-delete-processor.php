<?php

/**
 * מעבד למחיקת קנוניקלים
 * 
 * אחראי על מחיקת כתובות קנוניקליות המוגדרות כברירת מחדל עבור פוסטים וטקסונומיות
 */
class Delete_Processor {
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
     * מתודה ראשית למחיקת קנוניקלים
     */
    public function process() {
        try {
            // בדיקת אבטחה
            check_ajax_referer('canonicals_nonce', 'security');
            if (!current_user_can('manage_options')) {
                $this->logger->log("ניסיון לא מורשה למחיקת קנוניקלים");
                throw new Exception('משתמש לא מורשה');
            }

            // קבלת הנתונים מהבקשה
            $post_types = isset($_POST['post_types']) ? $_POST['post_types'] : [];
            $taxonomies = isset($_POST['taxonomies']) ? $_POST['taxonomies'] : [];

            $this->logger->log("סוגי פוסטים למחיקה: " . print_r($post_types, true));
            $this->logger->log("טקסונומיות למחיקה: " . print_r($taxonomies, true));

            if (empty($post_types) && empty($taxonomies)) {
                throw new Exception('לא נבחרו סוגי תוכן או טקסונומיות.');
            }

            // מחיקת הנתונים
            $total_deleted = 0;
            $total_checked = 0;
            $is_complete = false;

            // טיפול בטקסונומיות
            $deleted_taxonomies = $this->delete_taxonomy_canonicals($taxonomies);
            $total_deleted += $deleted_taxonomies['count'];
            $total_checked += $deleted_taxonomies['checked'];

            // טיפול בפוסטים אם נשאר מקום באצווה
            if ($deleted_taxonomies['checked'] < $this->batch_size) {
                $remaining = $this->batch_size - $deleted_taxonomies['checked'];
                $deleted_posts = $this->delete_post_type_canonicals($post_types, $remaining);
                $total_deleted += $deleted_posts['count'];
                $total_checked += $deleted_posts['checked'];

                if ($deleted_posts['checked'] < $remaining) {
                    $is_complete = true;
                }
            }

            // החזרת תשובה
            wp_send_json_success([
                'message' => sprintf('נמחקו %d שדות קנוניים המוגדרים כברירת מחדל. %d שדות נבדקו.', 
                    $total_deleted, $total_checked),
                'deleted_count' => $total_deleted,
                'checked_count' => $total_checked,
                'is_complete' => $is_complete
            ]);

        } catch (Exception $e) {
            $this->logger->log("שגיאה במחיקת קנוניקלים: " . $e->getMessage());
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * מחיקת קנוניקלים מטקסונומיות
     */
    private function delete_taxonomy_canonicals($taxonomies) {
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
                    $result = $this->delete_canonical($term_id, $permalink, true, $taxonomy);
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
     * מחיקת קנוניקלים מפוסטים
     */
    private function delete_post_type_canonicals($post_types, $limit) {
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
                    $result = $this->delete_canonical($post_id, $permalink, false);
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
     * מחיקת קנוניקל ספציפי
     */
    private function delete_canonical($id, $permalink, $is_term = false, $taxonomy = '') {
        if ($is_term && defined('WPSEO_VERSION') && class_exists('WPSEO_Taxonomy_Meta')) {
            $current_canonical = WPSEO_Taxonomy_Meta::get_term_meta($id, $taxonomy, 'canonical');
            if (!empty($current_canonical) && $this->is_default_canonical($current_canonical, $permalink)) {
                WPSEO_Taxonomy_Meta::set_value($id, $taxonomy, 'canonical', '');
                return 1;
            }
            return 0;
        }

        $meta_key = $this->get_meta_key($is_term);
        $current_canonical = $is_term ? get_term_meta($id, $meta_key, true) : get_post_meta($id, $meta_key, true);

        if (!empty($current_canonical) && $this->is_default_canonical($current_canonical, $permalink)) {
            $delete_function = $is_term ? 'delete_term_meta' : 'delete_post_meta';
            return $delete_function($id, $meta_key) ? 1 : 0;
        }

        return 0;
    }

    /**
     * בדיקה האם הקנוניקל הוא ברירת מחדל
     */
    private function is_default_canonical($canonical, $permalink) {
        return trim($canonical, '/') === trim($permalink, '/');
    }
}