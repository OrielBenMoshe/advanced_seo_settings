<?php

require_once dirname(__FILE__) . '/../traits/trait-meta-handler.php';
require_once dirname(__FILE__) . '/class-auto-fill-processor.php';  
require_once dirname(__FILE__) . '/class-delete-processor.php';   
/**
 * מעבד לספירת שדות קנוניים
 * 
 * אחראי על ספירת שדות שצריכים מילוי או מחיקה לפני ביצוע הפעולה
 */
class Count_Processor {
    use Meta_Handler;

    private $logger;
    private $auto_fill_processor;
    private $delete_processor;

    /**
     * אתחול המעבד
     */
    public function __construct($logger, $auto_fill_processor, $delete_processor) {
        $this->logger = $logger;
        $this->auto_fill_processor = $auto_fill_processor;
        $this->delete_processor = $delete_processor;
    }

    /**
     * ספירת שדות שצריכים מילוי אוטומטי
     */
    public function count_fillable() {
        error_log('=== Starting count_fillable ===');
        try {
            error_log('POST data: ' . print_r($_POST, true));
            check_ajax_referer('canonicals_nonce', 'security');
            if (!current_user_can('manage_options')) {
                throw new Exception('משתמש לא מורשה');
            }

            $post_types = isset($_POST['post_types']) ? $_POST['post_types'] : [];
            $taxonomies = isset($_POST['taxonomies']) ? $_POST['taxonomies'] : [];

            if (empty($post_types) && empty($taxonomies)) {
                throw new Exception('לא נבחרו סוגי תוכן או טקסונומיות.');
            }

            $this->logger->log("סופר שדות ריקים עבור: " . print_r(['post_types' => $post_types, 'taxonomies' => $taxonomies], true));
            
            $count = $this->auto_fill_processor->count_empty_fields($post_types, $taxonomies);

            wp_send_json_success([
                'count' => $count,
                'message' => sprintf('נמצאו %d שדות קנוניים ריקים למילוי אוטומטי.', $count)
            ]);

        } catch (Exception $e) {
            $this->logger->log("שגיאה בספירת שדות למילוי: " . $e->getMessage());
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * ספירת שדות שצריכים מחיקה
     */
    public function count_deletable() {
        try {
            check_ajax_referer('canonicals_nonce', 'security');
            if (!current_user_can('manage_options')) {
                throw new Exception('משתמש לא מורשה');
            }

            $post_types = isset($_POST['post_types']) ? $_POST['post_types'] : [];
            $taxonomies = isset($_POST['taxonomies']) ? $_POST['taxonomies'] : [];

            if (empty($post_types) && empty($taxonomies)) {
                throw new Exception('לא נבחרו סוגי תוכן או טקסונומיות.');
            }

            $this->logger->log("סופר שדות ברירת מחדל עבור: " . print_r(['post_types' => $post_types, 'taxonomies' => $taxonomies], true));
            
            $count = $this->delete_processor->count_default_fields($post_types, $taxonomies);

            wp_send_json_success([
                'count' => $count,
                'message' => sprintf('נמצאו %d שדות קנוניים המוגדרים כברירת מחדל למחיקה.', $count)
            ]);

        } catch (Exception $e) {
            $this->logger->log("שגיאה בספירת שדות למחיקה: " . $e->getMessage());
            wp_send_json_error($e->getMessage());
        }
    }
}