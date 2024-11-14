/**
 * Canonicals Batch Processor
 * 
 * Handles bulk operations for canonical URLs:
 * - Auto-filling empty canonical fields
 * - Deleting default canonical values
 * 
 * Features:
 * - Batch processing with progress indication
 * - User confirmation before operations
 * - Error handling and user feedback
 * - Supports both post types and taxonomies
 */

jQuery(document).ready(function($) {
    console.log('jQuery loaded');
    $('#auto_fill_canonicals, #delete_default_canonicals').click(function(e) {
        e.preventDefault();
        
        var $button = $(this);
        var isAutoFill = $button.attr('id') === 'auto_fill_canonicals';
        var $container = $button.closest('.canonical-action-container');
        var $progress = $container.find('.progress-container');
        var $progressFill = $progress.find('.progress-fill');
        var $progressText = $progress.find('.progress-text');
        var $progressCount = $progress.find('.progress-count');
        var $result = $container.find('span');
        
        // איסוף post types
        var selectedPostTypes = {};
        $('input[name^="advanced_seo_canonicals[post_types]"]:checked').each(function() {
            var name = $(this).attr('name');
            var matches = name.match(/\[post_types\]\[(.*?)\]/);
            if (matches) {
                selectedPostTypes[matches[1]] = '1';
            }
        });

        // איסוף taxonomies
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

        // שליחת בקשת ספירה
        $.post(ajaxurl, {
            action: 'auto_fill_canonicals',
            security: canonicalsSettings.n
        }, function(response) {
            console.log('Count response:', response);
            if (response.success) {
                $progressFill.css('width', '100%');
                $progressText.text('Completed');
                $progressCount.text(response.data.total);
                $result.text('Completed');
            } else {
                $progressFill.css('width', '0%');
                $progressText.text('Error');
                $progressCount.text(response.data.error);
                $result.text('Error');
            }
        });
    });
}); 