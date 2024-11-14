<?php
class Canonicals_Settings
{
    private $logger;

    public function __construct()
    {
        global $advanced_seo_logger;
        $this->logger = $advanced_seo_logger;
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
    }

    public function enqueue_scripts($hook)
    {
        // Load only on canonicals settings page
        if ('seo_page_advanced-seo-canonicals' !== $hook) {
            return;
        }

        wp_enqueue_script(
            'canonicals-batch-processor',
            plugins_url('/assets/js/canonicals-batch-processor.js', dirname(__FILE__, 2)),
            array('jquery'),
            '1.0.0',
            true
        );

        wp_localize_script('canonicals-batch-processor', 'canonicalsSettings', array(
            'ajaxurl' => admin_url('ajax.php'),
            'nonce' => wp_create_nonce("canonicals_nonce")
        ));
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
            <script>
                console.log('Settings page loaded');
            </script>
            <form method="post" action="options.php">
                <?php
                    settings_fields('advanced_seo_canonicals_group');
                    do_settings_sections('advanced_seo_canonicals');
                    submit_button();
                ?>
            </form>
        </div>
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
    }
  
    public function auto_fill_button_render($args)
    {
        $this->logger->log("Rendering auto fill button");
        echo "<div class='canonical-action-container'>";
        echo "<button type='button' id='auto_fill_canonicals' class='button button-primary'>מלא קנוניקלים אוטומטית</button>";
        echo "<div class='progress-container' style='display:none; margin-top: 10px;'>";
        echo "<div class='progress-bar'>";
        echo "<div class='progress-fill'></div>";
        echo "</div>";
        echo "<div class='progress-text'>0%</div>";
        echo "<div class='progress-count'>0 מתוך 0 פריטים עודכנו</div>";
        echo "</div>";
        echo "<span id='auto_fill_result' style='margin-inline-start: 10px;'></span>";
        echo "</div>";
    }
    
    public function delete_default_button_render($args)
    {
        $this->logger->log("Rendering delete default button");
        echo "<div class='canonical-action-container'>";
        echo "<button type='button' id='delete_default_canonicals' class='button button-secondary'>מחק קנוניקלים ברירת מחדל</button>";
        echo "<div class='progress-container' style='display:none; margin-top: 10px;'>";
        echo "<div class='progress-bar'>";
        echo "<div class='progress-fill'></div>";
        echo "</div>";
        echo "<div class='progress-text'>0%</div>";
        echo "<div class='progress-count'>0 מתוך 0 פריטים נמחקו</div>";
        echo "</div>";
        echo "<span id='delete_default_result' style='margin-inline-start: 10px;'></span>";
        echo "</div>";
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
