<?php
class Canonicals_Settings
{
    private $logger;

    public function __construct()
    {
        global $advanced_seo_logger;
        $this->logger = $advanced_seo_logger;
    }

    public function init()
    {
        // $this->logger->log("Initializing Canonicals Settings");
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
        
        <script type="text/javascript">
            jQuery(document).ready(function($) {
                $('#auto_fill_canonicals, #delete_default_canonicals').click(function() {
                    var action_name = $(this).attr('id') === 'auto_fill_canonicals' ? 'למלא' : 'למחוק';
                    console.log('החלה הפעולה');
                    
                    var $button = $(this);
                    var $result = $button.next('span');
                    var action = $button.attr('id') === 'auto_fill_canonicals' ? 'auto_fill_canonicals' : 'delete_default_canonicals';
                    
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
                    
                    console.log('Selected post types:', selectedPostTypes);
                    console.log('Selected taxonomies:', selectedTaxonomies);
                    
                    
                        // שליחת קריאה לשרת
    jQuery.ajax({
        url: ajaxurl,
        method: 'POST',
        dataType: 'json',
        data: {
            action: 'get_update_count',
            // security: canonicals_nonce,
            post_types: selectedPostTypes,
            taxonomies: selectedTaxonomies,
        },
        success: function (response) {
            if (response.success) {
                const { posts_count, taxonomies_count } = response.data;

                // הודעת Confirm
                const message = `מספר הפוסטים שיתעדכנו: ${posts_count}\nמספר הטקסונומיות שיתעדכנו: ${taxonomies_count}\nהאם להמשיך?`;
                if (!confirm(message)) {
                    return;
                }
            } else {
                alert('שגיאה בקבלת הנתונים: ' + response.data);
            }
        },
        error: function (xhr, status, error) {
            alert('שגיאה בתקשורת עם השרת: ' + error);
        },
    });


                          
                    // if (!confirm(`האם אתה בטוח שברצונך ${action_name} קנוניקלס?`)) {
                    //     return;
                    // }

                    function processBatch() {
                        var data = {
                            'action': action,
                            'security': '<?php echo wp_create_nonce("canonicals_nonce"); ?>',
                            'post_types': selectedPostTypes,
                            'taxonomies': selectedTaxonomies
                        };
                        
                        $.post(ajaxurl, data, function(response) {
                            console.log('Server response:', response);
                            if (response.success) {
                                $result.text(response.data.message);
                                if (!response.data.is_complete) {
                                    setTimeout(processBatch, 1000); // Wait 1 second before next batch
                                } else {
                                    $button.prop('disabled', false);
                                    $result.text(response.data.message + ' הפעולה הושלמה.');
                                }
                            } else {
                                $result.text('אירעה שגיאה: ' + (response.data || 'Unknown error'));
                                $button.prop('disabled', false);
                            }
                        }).fail(function(jqXHR, textStatus, errorThrown) {
                            console.error('AJAX error:', textStatus, errorThrown);
                            $result.text('אירעה שגיאה בתקשורת עם השרת: ' + textStatus);
                            $button.prop('disabled', false);
                        });
                    }
                    
                    processBatch();
                });
            });


                </script>
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
            'ניהול אוטומטי של שדות קנוניקל',
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

        echo "<div style='margin-bottom: 20px;'>";
        echo "<label><input type='checkbox' id='select_all_content_types'> בחר הכל</label>";
        echo "</div>";

        foreach ($post_types as $post_type) {
            $checked = !empty($options['post_types'][$post_type->name]) ? 'checked' : '';
            echo "<div style='margin-bottom: 10px;'>";
            echo "<label><input type='checkbox' name='advanced_seo_canonicals[post_types][{$post_type->name}]' value='1' class='content_type_checkbox' {$checked}> {$post_type->label}</label>";

            // Get taxonomies for this post type
            $taxonomies = get_object_taxonomies($post_type->name, 'objects');
            if (!empty($taxonomies)) {
                echo "<div style='margin-inline-start: 24px;'>";
                echo "<strong style='line-height: 2;'>טקסונומיות:</strong><br>";
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
                    var allChecked = $('.content_type_checkbox:checked, .taxonomy_checkbox:checked').length === 
                                     $('.content_type_checkbox, .taxonomy_checkbox').length;
                    $('#select_all_content_types').prop('checked', allChecked);
                }
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
}