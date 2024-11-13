<?php
// tabs/meta_description.php

function advanced_seo_meta_description_init() {
    register_setting('advanced_seo_meta_description_group', 'advanced_seo_meta_description');
    add_settings_section('advanced_seo_meta_description_section', 'Meta Description Settings', 'advanced_seo_meta_description_section_callback', 'advanced_seo_meta_description');
    add_settings_field('default_meta_description', 'Default Meta Description', 'advanced_seo_default_meta_description_render', 'advanced_seo_meta_description', 'advanced_seo_meta_description_section');
}

function advanced_seo_meta_description_section_callback() {
    echo 'Configure your meta description settings:';
}

function advanced_seo_default_meta_description_render() {
    $options = get_option('advanced_seo_meta_description');
    ?>
    <textarea name='advanced_seo_meta_description[default_meta_description]' rows='5' cols='50'><?php echo $options['default_meta_description'] ?? ''; ?></textarea>
    <?php
}

function advanced_seo_meta_description_settings() {
    settings_fields('advanced_seo_meta_description_group');
    do_settings_sections('advanced_seo_meta_description');
}