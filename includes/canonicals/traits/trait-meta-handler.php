<?php

trait Meta_Handler {
    /**
     * Get the appropriate meta key for canonical URLs based on the SEO plugin in use.
     *
     * @param bool $is_term Whether this is for a term or a post
     * @return string The meta key to use
     */
    private function get_meta_key($is_term) {
        $meta_key = '';
        if (defined('WPSEO_VERSION')) {
            $meta_key = $is_term ? 'wpseo_canonical' : '_yoast_wpseo_canonical';
        } elseif (class_exists('RankMath')) {
            $meta_key = 'rank_math_canonical_url';
        } elseif ($is_term) {
            $meta_key = 'custom_canonical_url';
        } else {
            $meta_key = 'canonical_url';
        }
        $this->logger->log("Meta key determined: $meta_key for " . ($is_term ? "term" : "post"));
        return $meta_key;
    }
}