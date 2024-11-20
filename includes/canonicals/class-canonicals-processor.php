<?php

require_once 'traits/trait-meta-handler.php';
require_once 'processors/class-auto-fill-processor.php';
require_once 'processors/class-delete-processor.php';
require_once 'processors/class-url-processor.php';

/**
 * Class Canonicals_Processor
 *
 * Main orchestrator for handling canonical URLs processing.
 */
class Canonicals_Processor {
    use Meta_Handler;

    private $batch_size = 100;
    private $logger;
    private $auto_fill_processor;
    private $delete_processor;
    private $url_processor;

    public function __construct() {
        global $advanced_seo_logger;
        $this->logger = $advanced_seo_logger;
        
        // Initialize processors
        $this->auto_fill_processor = new Auto_Fill_Processor($this->logger, $this->batch_size);
        $this->delete_processor = new Delete_Processor($this->logger, $this->batch_size);
        $this->url_processor = new URL_Processor($this->logger);
    }

    /**
     * Auto-fill canonical URLs for selected post types and taxonomies.
     * This method is typically called via AJAX.
     */
    public function auto_fill_canonicals() {
        return $this->auto_fill_processor->process();
    }

    /**
     * Delete canonical URLs for specified post types and taxonomies.
     */
    public function delete_default_canonicals() {
        return $this->delete_processor->process();
    }

    /**
     * Normalize a URL for comparison.
     *
     * @param string $url The URL to normalize
     * @return string The normalized URL
     */
    public function normalize_url($url) {
        return $this->url_processor->normalize($url);
    }
}