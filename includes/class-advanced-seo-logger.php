<?php
class Advanced_SEO_Logger {
    private $log_file;

    public function __construct($file_name = 'debug.log') {
        // Get the plugin's root directory
        $plugin_dir = plugin_dir_path(dirname(__FILE__));
        $this->log_file = $plugin_dir . $file_name;

        if (!is_writable(dirname($this->log_file))) {
            add_action('admin_notices', function() {
                echo '<div class="error"><p>Advanced SEO Settings: Unable to write to log file. Please check permissions.</p></div>';
            });
        }
    }

    public function log($message) {
        $timestamp = current_time('mysql');
        $formatted_message = sprintf("[%s] %s\n", $timestamp, $message);
        
        if (file_put_contents($this->log_file, $formatted_message, FILE_APPEND) === false) {
            error_log("Failed to write to log file: " . $this->log_file);
        }
    }

    public function clear_log() {
        if (file_exists($this->log_file)) {
            unlink($this->log_file);
        }
    }

    public function get_log_content() {
        if (file_exists($this->log_file)) {
            return file_get_contents($this->log_file);
        }
        return '';
    }
}