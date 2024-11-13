/**
 * Normalized URL Display
 * Adds normalized URL display next to canonical field title
 */
(function($) {
    'use strict';

    const NormalizedUrlDisplay = {
        init: function() {
            this.bindEvents();
        },

        bindEvents: function() {
            $(document).ready(() => {
                this.setupAccordionListener();
                this.setupFieldListener();
            });
        },

        normalizeUrl: function(url) {
            if (!url) return '';
            let normalized = url;
            
            try {
                normalized = decodeURIComponent(normalized);
                normalized = normalized
                    .replace(/^https?:\/\//, '')
                    .replace(/^www\./, '')
                    .replace(/\/$/, '')
                    .trim();
            } catch (e) {
                console.error('Error normalizing URL:', e);
                return url;
            }
            
            return normalized;
        },

        setupAccordionListener: function() {
            $(document).on('click', '#collapsible-advanced-settings', () => {
                setTimeout(() => this.checkAndInitialize(), 100);
            });

            this.checkAndInitialize();

            const observer = new MutationObserver((mutations) => {
                mutations.forEach((mutation) => {
                    if (mutation.type === 'attributes' && 
                        mutation.attributeName === 'aria-expanded' &&
                        mutation.target.getAttribute('aria-expanded') === 'true') {
                        this.checkAndInitialize();
                    }
                });
            });

            const accordionButton = document.getElementById('collapsible-advanced-settings');
            if (accordionButton) {
                observer.observe(accordionButton, {
                    attributes: true,
                    attributeFilter: ['aria-expanded']
                });
            }
        },

        checkAndInitialize: function() {
            if ($('#collapsible-advanced-settings').attr('aria-expanded') === 'true') {
                setTimeout(() => this.initializeDisplay(), 150);
            }
        },

        initializeDisplay: function() {
            const canonicalField = $('#yoast-canonical-metabox');
            if (!canonicalField.length) return;
            
            this.updateNormalizedDisplay(canonicalField);
        },

        setupFieldListener: function() {
            $(document).on('input', '#yoast-canonical-metabox', 
                (e) => this.updateNormalizedDisplay($(e.target))
            );
        },

        updateNormalizedDisplay: function(field) {
            const originalValue = field.val();
            if (!originalValue) return;

            const normalizedValue = this.normalizeUrl(originalValue);
            if (normalizedValue === originalValue) return;

            // מחפש את div של הכותרת
            const titleContainer = field.closest('.yoast-field-group')
                                     .find('.yoast-field-group__title');
            
            // מוצא או יוצר את אלמנט התצוגה המנורמלת
            let normalizedDisplay = titleContainer.find('.normalized-url-display');
            
            if (!normalizedDisplay.length) {
                normalizedDisplay = $('<span/>', {
                    'class': 'normalized-url-display',
                    'css': {
                        'fontSize': '14px',
                        'color': '#666',
                        'marginRight': '8px',
                        'fontWeight': 'normal',
                        'backgroundColor': '##f0ffe1',
                        'padding': '2px 14px',
                        'borderRadius': '5px',
                        'boxShadow': '0px 1px 3px 0px #00000030'
                    }
                });
                
                titleContainer.append(normalizedDisplay);
            }
            
            normalizedDisplay.html(`
                URL בעברית: 
                <a href="${originalValue}" 
                   target="_blank" 
                   style="color: #0073aa; text-decoration: none; direction: ltr; display: inline-block;"
                   onmouseover="this.style.textDecoration='underline'"
                   onmouseout="this.style.textDecoration='none'">
                    ${normalizedValue}
                </a>
            `);
        }
    };

    // Initialize
    $(document).ready(() => NormalizedUrlDisplay.init());

})(jQuery);