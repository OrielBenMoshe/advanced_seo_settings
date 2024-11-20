<?php

/**
 * מעבד לטיפול בכתובות URL
 * 
 * אחראי על נירמול והשוואת כתובות URL קנוניות
 */
class URL_Processor {
    private $logger;

    /**
     * אתחול המעבד עם לוגר
     */
    public function __construct($logger) {
        $this->logger = $logger;
    }

    /**
     * נירמול כתובת URL להשוואה
     * 
     * מנקה ומסדר את הכתובת כך שתהיה אחידה להשוואה:
     * - מסיר פרוטוקול (http/https)
     * - מסיר www
     * - מסיר / בסוף
     * - הופך לאותיות קטנות
     * - מפענח תווים מקודדים
     */
    public function normalize($url) {
        $this->logger->log("מנרמל URL: $url");
        
        if (empty($url)) {
            $this->logger->log("URL ריק, מחזיר מחרוזת ריקה");
            return '';
        }

        $normalized = $url;
        
        // הסרת פרוטוקול
        $normalized = preg_replace('(^https?://)', '', $normalized);
        
        // הסרת www
        $normalized = preg_replace('/^www\./', '', $normalized);
        
        // הסרת / בסוף
        $normalized = rtrim($normalized, '/');
        
        // המרה לאותיות קטנות
        $normalized = strtolower($normalized);
        
        // פענוח URL
        $normalized = urldecode($normalized);
        
        // המרת תווים מקודדים
        $normalized = preg_replace_callback('/%[0-9A-F]{2}/i', function ($match) {
            return chr(hexdec($match[0]));
        }, $normalized);

        $this->logger->log("URL מנורמל: $normalized");
        return $normalized;
    }

    /**
     * השוואת שתי כתובות URL
     */
    public function compare($url1, $url2) {
        $normalized1 = $this->normalize($url1);
        $normalized2 = $this->normalize($url2);
        
        return $normalized1 === $normalized2;
    }
}