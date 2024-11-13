<?php
// tabs/alt_text.php

function advanced_seo_alt_text_init() {
    register_setting('advanced_seo_alt_text_group', 'advanced_seo_alt_text');
    add_settings_section('advanced_seo_alt_text_section', 'Alt Text Settings', 'advanced_seo_alt_text_section_callback', 'advanced_seo_alt_text');
    add_settings_field('auto_alt_text', 'Auto Generate Alt Text', 'advanced_seo_auto_alt_text_render', 'advanced_seo_alt_text', 'advanced_seo_alt_text_section');
}

function advanced_seo_alt_text_section_callback() {
    echo 'Configure your alt text settings:';
}

function advanced_seo_auto_alt_text_render() {
    $options = get_option('advanced_seo_alt_text');
    ?>
    <input type='checkbox' name='advanced_seo_alt_text[auto_alt_text]' <?php checked($options['auto_alt_text'] ?? false, 1); ?> value='1'>
    <?php
}

function advanced_seo_alt_text_settings() {
    settings_fields('advanced_seo_alt_text_group');
    do_settings_sections('advanced_seo_alt_text');
}