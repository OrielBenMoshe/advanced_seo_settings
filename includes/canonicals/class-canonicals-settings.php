<?php
class Canonicals_Settings
{
    private $logger;

    public function __construct()
    {
        global $advanced_seo_logger;
        $this->logger = $advanced_seo_logger;
        
        // לוג בזמן יצירת המחלקה
        $this->logger->log("Canonicals_Settings constructor called");
        
        // הוספת האזנה לפעולת AJAX
        add_action('wp_ajax_count_canonical_fields', array($this, 'count_canonical_fields'));
        
        // לוג לאחר הוספת ה-action
        $this->logger->log("Added AJAX action: count_canonical_fields");
    }

    public function init()
    {
        $this->logger->log("Initializing Canonicals Settings");
        $this->register_settings();
        $this->add_settings_fields();
    }

    public function render_settings_page()
    {
        $this->logger->log("Rendering Canonicals Settings page");
        ?>
        <div class="wrap">
            <h1>הגדרות קנוניקל</h1>
            <form method="post" action="options.php">
                <?php
                    settings_fields('advanced_seo_canonicals_group');
                    do_settings_sections('advanced_seo_canonicals');
                    submit_button();
                ?>
            </form>
        </div>
        
        <div id="canonicals-modal" class="modal" style="display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.4);">
            <div class="modal-content" style="background-color: #fefefe; margin: 15% auto; padding: 20px; border: 1px solid #888; width: 80%; max-width: 500px; border-radius: 5px;">
                <h3 id="modal-title"></h3>
                <div id="count-results">בודק נתונים...</div>
                <p id="modal-message" style="margin-top: 15px;"></p>
                <div style="text-align: left;">
                    <button type="button" id="modal-confirm" class="button button-primary">אישור</button>
                    <button type="button" id="modal-cancel" class="button button-secondary">ביטול</button>
                </div>
            </div>
        </div>
        
        <script type="text/javascript">
            jQuery(document).ready(function($) {
                var currentAction = '';
                var $modal = $('#canonicals-modal');
                var canonicalsNonce = '<?php echo wp_create_nonce("canonicals_nonce"); ?>';
                
                // פונקציות עזר משופרות
                function getSelectedPostTypes() {
                    const selectedPostTypes = {};
                    $('input[name^="advanced_seo_canonicals[post_types]"]:checked').each(function() {
                        const matches = $(this).attr('name').match(/\[post_types\]\[(.*?)\]/);
                        if (matches) {
                            selectedPostTypes[matches[1]] = '1';
                        }
                    });
                    return selectedPostTypes;
                }

                function getSelectedTaxonomies() {
                    const selectedTaxonomies = {};
                    $('input[name^="advanced_seo_canonicals[taxonomies]"]:checked').each(function() {
                        const matches = $(this).attr('name').match(/\[taxonomies\]\[(.*?)\]/);
                        if (matches) {
                            selectedTaxonomies[matches[1]] = '1';
                        }
                    });
                    return selectedTaxonomies;
                }

                // עונקציה מעודכנת להצגת המודאל
                async function showModal(title, message, action) {
                    $('#modal-title').text(title);
                    $('#count-results').html('בודק נתונים...');
                    currentAction = action;
                    $modal.show();

                    try {
                        const postTypes = getSelectedPostTypes();
                        const taxonomies = getSelectedTaxonomies();
                        
                        const data = {
                            'action': 'count_canonical_fields',
                            'security': canonicalsNonce,
                            'post_types': postTypes,
                            'taxonomies': taxonomies,
                            'action_type': action === 'auto_fill_canonicals' ? 'auto_fill' : 'delete'
                        };

                        // הדפסת הנתונים לקונסול לצורך דיבוג
                        console.log('Sending data:', data);

                        const response = await $.ajax({
                            url: ajaxurl,
                            type: 'POST',
                            data: data,
                            dataType: 'json'
                        });

                        console.log('Server response:', response);

                        if (response && response.success) {
                            let detailsHtml = '<div class="count-details">';
                            let modalMessage = '';
                            
                            if (response.data.total > 0) {
                                detailsHtml += '<p>נמצאו ' + response.data.total + ' שדות לטיפול:</p><ul>';
                                for (const [key, value] of Object.entries(response.data.details)) {
                                    detailsHtml += '<li>' + value.label + ': ' + value.count + ' שדות</li>';
                                }
                                detailsHtml += '</ul>';
                                modalMessage = message;
                            } else {
                                detailsHtml += '<p style="color: warning;">לא נמצאו שדות לטיפול</p>';
                                modalMessage = 'לא נדרשת פעולה - כל השדות שנבחרו כבר מטופלים.';
                            }

                            if (Object.keys(response.data.skipped_details).length > 0) {
                                detailsHtml += '<p>שדות שנבחרו אך לא נכללו בפעולה' + 
                                               (action === 'auto_fill_canonicals' 
                                                ? ' (כבר קיים ערך בשדה הקנוניקל)' 
                                                : ' (ערך הקנוניקל שהה לכתובת ה-URL המקורית)') +
                                               '</p><ul>';
                                
                                for (const [key, value] of Object.entries(response.data.skipped_details)) {
                                    detailsHtml += '<li>' + value.label + ': ' + value.count + ' שדות</li>';
                                }
                                detailsHtml += '</ul>';
                            }
                            
                            detailsHtml += '</div>';
                            $('#count-results').html(detailsHtml);
                            $('#modal-message').html('<p>' + modalMessage + '</p>');

                            if (response.data.total === 0) {
                                $('#modal-cancel').hide();
                                $('#modal-confirm').text('סגור');
                            } else {
                                $('#modal-cancel').show();
                                $('#modal-confirm').text('אישור');
                            }
                        } else {
                            $('#count-results').html('אירעה שגיאה בספירת השדות');
                        }
                    } catch (error) {
                        console.error('AJAX Error:', error);
                        $('#count-results').html('אירעה שגיאה בתקשורת עם השרת');
                    }
                }

                // עונקציה חדשה לבדיקת בחירות
                function hasSelectedItems() {
                    const hasPostTypes = Object.keys(getSelectedPostTypes()).length > 0;
                    const hasTaxonomies = Object.keys(getSelectedTaxonomies()).length > 0;
                    
                    if (!hasPostTypes && !hasTaxonomies) {
                        clearAllResults(); // ניקוי הודעות קודמות
                        $('#' + currentAction).next('span').text('נא לבחור לפחות סוג תוכן אחד או טקסונומיה אחת');
                        return false;
                    }
                    return true;
                }

                // עדכון אירועי הכפתורים
                $('#auto_fill_canonicals').click(function() {
                    currentAction = 'auto_fill_canonicals';
                    if (!hasSelectedItems()) return;
                    
                    showModal(
                        'אישור מילוי קנוניקלים',
                        'האם אתה בטוח שברצונך למלא באופן אוטומטי את שדות הקנוניקל הריקים?',
                        'auto_fill_canonicals'
                    );
                });

                $('#delete_default_canonicals').click(function() {
                    currentAction = 'delete_default_canonicals';
                    if (!hasSelectedItems()) return;
                    
                    showModal(
                        'אישור מחיקת קנוניקלים',
                        'האם אתה בטוח שברצונך למחוק את כל שדות הקנוניקל שזהים ל-URL המקורי?',
                        'delete_default_canonicals'
                    );
                });

                $('#modal-cancel').click(function() {
                    $modal.hide();
                });

                // פונקציה חדשה לניקוי כל ההודעות
                function clearAllResults() {
                    $('#auto_fill_result, #delete_default_result').text('');
                    $('#count-results').empty();
                }

                $('#modal-confirm').click(function() {
                    clearAllResults(); // ניקוי הודעות קודמות
                    $modal.hide();
                    
                    // אם זה כפתור "סגור", לא ממשיכים לפעולת העדכון
                    if ($('#modal-confirm').text() === 'סגור') {
                        return;
                    }
                    
                    var $button = $('#' + currentAction);
                    var $result = $button.next('span');
                    
                    $button.prop('disabled', true);
                    $result.text('מבצע פעולה...');

                    var selectedPostTypes = {};
                    $('input[name^="advanced_seo_canonicals[post_types]"]:checked').each(function() {
                        var name = $(this).attr('name');
                        var matches = name.match(/\[post_types\]\[(.*?)\]/);
                        if (matches) {
                            selectedPostTypes[matches[1]] = '1';
                        }
                    });

                    var selectedTaxonomies = {};
                    $('input[name^="advanced_seo_canonicals[taxonomies]"]:checked').each(function() {
                        var name = $(this).attr('name');
                        var matches = name.match(/\[taxonomies\]\[(.*?)\]/);
                        if (matches) {
                            selectedTaxonomies[matches[1]] = '1';
                        }
                    });

                    function processBatch() {
                        var data = {
                            'action': currentAction,
                            'security': '<?php echo wp_create_nonce("canonicals_nonce"); ?>',
                            'post_types': selectedPostTypes,
                            'taxonomies': selectedTaxonomies
                        };
                        
                        $.post(ajaxurl, data, function(response) {
                            console.log('Server response:', response);
                            if (response.success) {
                                clearAllResults(); // ניקוי לפני הצגת ההודעה החדשה
                                $result.text(response.data.message);
                                if (!response.data.is_complete) {
                                    setTimeout(processBatch, 1000);
                                } else {
                                    $button.prop('disabled', false);
                                    $result.text(response.data.message + ' הפעולה הושלמה.');
                                }
                            } else {
                                clearAllResults(); // ניקוי לפני הצגת הודעת השגיאה
                                $result.text('אירעה שגיאה: ' + (response.data || 'Unknown error'));
                                $button.prop('disabled', false);
                            }
                        }).fail(function(jqXHR, textStatus, errorThrown) {
                            clearAllResults(); // ניקוי לפני הצגת הודעת השגיאה
                            console.error('AJAX error:', textStatus, errorThrown);
                            $result.text('אירעה שגיאה בתקשורת עם השרת: ' + textStatus);
                            $button.prop('disabled', false);
                        });
                    }
                    
                    processBatch();
                });
            });
        </script>
        <style>
        .count-details {
            margin: 15px 0;
            padding: 10px;
            background: #f8f8f8;
            border-radius: 4px;
        }

        .count-details ul {
            margin: 10px 20px;
            list-style-type: disc;
        }

        .count-details p {
            margin: 0 0 10px 0;
            font-weight: bold;
        }
        </style>
        <?php
    }

    private function register_settings()
    {
        // $this->logger->log("Registering Canonicals Settings");
        register_setting('advanced_seo_canonicals_group', 'advanced_seo_canonicals', array($this, 'sanitize_settings'));
    }

    private function add_settings_fields()
    {
        add_settings_section(
            'advanced_seo_canonicals_auto_section',
            'ניהול אוטומטי של שדת קנוניקל',
            array($this, 'auto_section_callback'),
            'advanced_seo_canonicals'
        );
    
        add_settings_field(
            'content_types',
            'סוגי תוכן וטקסונומיות<br><span class="description" style="font-weight: 300;">בחר את סוגי התוכן והטקסונומיות שברצונך לנהל את שדות הקנוניקל שלהם.</span>',
            array($this, 'render_content_types'),
            'advanced_seo_canonicals',
            'advanced_seo_canonicals_auto_section'
        );
    
        add_settings_field(
            'auto_fill_button',
            'מילוי אוטומטי של שדות קנוניקל<br><span class="description" style="font-weight: 300;">ממלא באופן אוטומטי את שדות הקנוניקל הריקים עם כתובת ה-URL המקורית של התוכן, ומדלג על שדות עם תוכן קיים ושונה מהכתובת המקורית </span>',
            array($this, 'auto_fill_button_render'),
            'advanced_seo_canonicals',
            'advanced_seo_canonicals_auto_section'
        );
    
        add_settings_field(
            'delete_default_button',
            'מחיקת קנוניקלים ברירת מחדל<br><span class="description" style="font-weight: 300;">מוחק את שדות הקנוניקל שערכם זהה לכתובת ה-URL המקורית של התוכן.</span>',
            array($this, 'delete_default_button_render'),
            'advanced_seo_canonicals',
            'advanced_seo_canonicals_auto_section'
        );
    }

    public function auto_section_callback()
    {
        $this->logger->log("Rendering auto section callback");
        echo 'פיצ\'ר זה מאפשר ניהול אוטומטי של שדות קנוניקל בעמודים ובטקסונומיות. ניתן למלא אוטומטית שדות ריקים או למחוק קנוניקלים שזהים לURL המקור.';
    }

    public function render_content_types($args)
    {
        $this->logger->log("Rendering content types");
        $options = get_option('advanced_seo_canonicals', array());
        if (!is_array($options)) {
            $options = array();
        }
        if (!isset($options['post_types']) || !is_array($options['post_types'])) {
            $options['post_types'] = array();
        }
        if (!isset($options['taxonomies']) || !is_array($options['taxonomies'])) {
            $options['taxonomies'] = array();
        }

        $post_types = get_post_types(['public' => true], 'objects');

        // echo "<div style='margin-bottom: 20px;'>";
        // echo "<label><input type='checkbox' id='select_all_content_types'> בחר הכל</label>";
        // echo "</div>";

        foreach ($post_types as $post_type) {
            $checked = !empty($options['post_types'][$post_type->name]) ? 'checked' : '';
            echo "<div style='margin-bottom: 10px;'>";
            echo "<label><input type='checkbox' name='advanced_seo_canonicals[post_types][{$post_type->name}]' value='1' class='content_type_checkbox' {$checked}> {$post_type->label}</label>";

            // Get taxonomies for this post type
            $taxonomies = get_object_taxonomies($post_type->name, 'objects');
            if (!empty($taxonomies)) {
                echo "<div style='margin-inline-start: 24px;'>";
                echo "<strong style='line-height: 2;'>טקסונומית:</strong><br>";
                foreach ($taxonomies as $taxonomy) {
                    $tax_checked = !empty($options['taxonomies'][$taxonomy->name]) ? 'checked' : '';
                    echo "<label style='margin-inline-start: 24px;'><input type='checkbox' name='advanced_seo_canonicals[taxonomies][{$taxonomy->name}]' value='1' class='taxonomy_checkbox' {$tax_checked}> {$taxonomy->label}</label><br>";
                }
                echo "</div>";
            }

            echo "</div>";
        }
        ?>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            $('#select_all_content_types').change(function() {
                $('.content_type_checkbox, .taxonomy_checkbox').prop('checked', this.checked);
            });
    
            $('.content_type_checkbox, .taxonomy_checkbox').change(function() {
                if (!this.checked) {
                    $('#select_all_content_types').prop('checked', false);
                } else {
                    var totalCheckboxes = $('.content_type_checkbox, .taxonomy_checkbox').length;
                    var checkedCheckboxes = $('.content_type_checkbox:checked, .taxonomy_checkbox:checked').length;
                    $('#select_all_content_types').prop('checked', totalCheckboxes === checkedCheckboxes);
                }
                
                clearAllResults();
            });
        });
        </script>
        <?php
    }

    public function auto_fill_button_render($args)
    {
        $this->logger->log("Rendering auto fill button");
        echo "<button type='button' id='auto_fill_canonicals' class='button button-primary'>מלא קנוניקלים אוטומטית</button>";
        echo "<span id='auto_fill_result' style='margin-inline-start: 10px;'></span>";
    }

    public function delete_default_button_render($args)
    {
        $this->logger->log("Rendering delete default button");
        echo "<button type='button' id='delete_default_canonicals' class='button button-secondary'>מחק קנוניקלים ברירת מחדל</button>";
        echo "<span id='delete_default_result' style='margin-inline-start: 10px;'></span>";
    }

    public function sanitize_settings($input)
    {
        $this->logger->log("Sanitizing settings input");
        $sanitized_input = array();

        if (isset($input['post_types']) && is_array($input['post_types'])) {
            foreach ($input['post_types'] as $post_type => $value) {
                $sanitized_input['post_types'][$post_type] = !empty($value) ? 1 : 0;
            }
        }

        if (isset($input['taxonomies']) && is_array($input['taxonomies'])) {
            foreach ($input['taxonomies'] as $taxonomy => $value) {
                $sanitized_input['taxonomies'][$taxonomy] = !empty($value) ? 1 : 0;
            }
        }

        $this->logger->log("Sanitized settings: " . print_r($sanitized_input, true));
        return $sanitized_input;
    }

    public function count_canonical_fields() {
        // לוג של הבקשה הנכנסת
        $this->logger->log("Count Canonical Fields - Request Data: " . print_r($_POST, true));

        // בדיקת nonce
        if (!check_ajax_referer('canonicals_nonce', 'security', false)) {
            $this->logger->log("Nonce verification failed");
            wp_send_json_error('Invalid security token');
            return;
        }

        if (!current_user_can('manage_options')) {
            $this->logger->log("User doesn't have required permissions");
            wp_send_json_error('Insufficient permissions');
            return;
        }

        // קבלת והמרת הנתונים
        $post_types = isset($_POST['post_types']) ? (array)$_POST['post_types'] : array();
        $taxonomies = isset($_POST['taxonomies']) ? (array)$_POST['taxonomies'] : array();
        $action_type = isset($_POST['action_type']) ? sanitize_text_field($_POST['action_type']) : '';

        $this->logger->log("Processing request with post_types: " . print_r($post_types, true));
        $this->logger->log("Processing request with taxonomies: " . print_r($taxonomies, true));
        $this->logger->log("Action type: " . $action_type);

        $total_count = 0;
        $details = array();
        $skipped_details = array();

        // ספירת פוסטים
        foreach ($post_types as $post_type => $enabled) {
            if ($enabled !== '1') continue;

            $args = array(
                'post_type' => $post_type,
                'posts_per_page' => -1,
                'fields' => 'ids',
                'post_status' => 'publish'
            );

            $query = new WP_Query($args);
            $count = 0;
            $skipped = 0;

            foreach ($query->posts as $post_id) {
                $permalink = get_permalink($post_id);
                $meta_key = '_yoast_wpseo_canonical';
                $canonical = get_post_meta($post_id, $meta_key, true);

                if ($action_type === 'auto_fill') {
                    if (empty($canonical)) {
                        $count++;
                    } else {
                        $skipped++;
                    }
                } else if ($action_type === 'delete') {
                    if (!empty($canonical) && trailingslashit($canonical) === trailingslashit($permalink)) {
                        $count++;
                    } else {
                        $skipped++;
                    }
                }
            }

            $post_type_obj = get_post_type_object($post_type);
            if ($count > 0) {
                $details[$post_type] = array(
                    'count' => $count,
                    'label' => $post_type_obj->labels->name
                );
                $total_count += $count;
            }
            if ($skipped > 0) {
                $skipped_details[$post_type] = array(
                    'count' => $skipped,
                    'label' => $post_type_obj->labels->name
                );
            }
        }

        // ספירת טקסונומיות
        foreach ($taxonomies as $taxonomy => $enabled) {
            if ($enabled !== '1') continue;

            $terms = get_terms(array(
                'taxonomy' => $taxonomy,
                'hide_empty' => false,
                'fields' => 'ids'
            ));

            if (is_wp_error($terms)) continue;

            $count = 0;
            $skipped = 0;

            foreach ($terms as $term_id) {
                $term_link = get_term_link($term_id, $taxonomy);
                if (is_wp_error($term_link)) continue;

                if (defined('WPSEO_VERSION') && class_exists('WPSEO_Taxonomy_Meta')) {
                    $canonical = WPSEO_Taxonomy_Meta::get_term_meta($term_id, $taxonomy, 'canonical');
                } else {
                    $canonical = get_term_meta($term_id, 'wpseo_canonical', true);
                }

                if ($action_type === 'auto_fill') {
                    if (empty($canonical)) {
                        $count++;
                    } else {
                        $skipped++;
                    }
                } else if ($action_type === 'delete') {
                    if (!empty($canonical) && trailingslashit($canonical) === trailingslashit($term_link)) {
                        $count++;
                    } else {
                        $skipped++;
                    }
                }
            }

            $tax_obj = get_taxonomy($taxonomy);
            if ($count > 0) {
                $details[$taxonomy] = array(
                    'count' => $count,
                    'label' => $tax_obj->labels->name
                );
                $total_count += $count;
            }
            if ($skipped > 0) {
                $skipped_details[$taxonomy] = array(
                    'count' => $skipped,
                    'label' => $tax_obj->labels->name
                );
            }
        }

        $result = array(
            'total' => $total_count,
            'details' => $details,
            'skipped_details' => $skipped_details
        );

        $this->logger->log("Sending response: " . print_r($result, true));
        wp_send_json_success($result);
    }

    private function normalize_url($url) {
        if (empty($url)) {
            return '';
        }
        $normalized = $url;
        $normalized = preg_replace('(^https?://)', '', $normalized);
        $normalized = preg_replace('/^www\./', '', $normalized);
        $normalized = rtrim($normalized, '/');
        $normalized = strtolower($normalized);
        return $normalized;
    }
}