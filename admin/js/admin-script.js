/**
 * Dynamics Sync Lite - Admin JavaScript
 */

(function($) {
    'use strict';

    const DynamicsAdmin = {
        
        init: function() {
            this.bindEvents();
        },
        
        bindEvents: function() {
            // Additional admin functionality can be added here
            // For now, test connection is handled inline in settings page
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        DynamicsAdmin.init();
    });

})(jQuery);