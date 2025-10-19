/**
 * Frontend JavaScript for Dynamics Sync Lite
 */

(function($) {
    'use strict';
    
    let contactId = null;
    
    /**
     * Initialize
     */
    function init() {
        loadContactData();
        bindEvents();
    }
    
    /**
     * Bind events
     */
    function bindEvents() {
        $('#dsl-contact-form').on('submit', handleFormSubmit);
        $('#dsl-refresh-data').on('click', function(e) {
            e.preventDefault();
            loadContactData();
        });
    }
    
    /**
     * Load contact data
     */
    function loadContactData() {
        showLoading();
        hideMessage();
        
        $.ajax({
            url: dslData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'dsl_get_contact',
                nonce: dslData.nonce
            },
            success: function(response) {
                if (response.success) {
                    populateForm(response.data);
                    showForm();
                } else {
                    showError(response.data || dslData.strings.error);
                    hideLoading();
                }
            },
            error: function() {
                showError(dslData.strings.error);
                hideLoading();
            }
        });
    }
    
    /**
     * Populate form with data
     */
    function populateForm(data) {
        contactId = data.contactid;
        $('#dsl_contact_id').val(data.contactid || '');
        $('#dsl_firstname').val(data.firstname || '');
        $('#dsl_lastname').val(data.lastname || '');
        $('#dsl_email').val(data.emailaddress1 || '');
        $('#dsl_phone').val(data.telephone1 || '');
        $('#dsl_address').val(data.address1_line1 || '');
        $('#dsl_city').val(data.address1_city || '');
        $('#dsl_state').val(data.address1_stateorprovince || '');
        $('#dsl_postal').val(data.address1_postalcode || '');
        $('#dsl_country').val(data.address1_country || '');
    }
    
    /**
     * Handle form submit
     */
    function handleFormSubmit(e) {
        e.preventDefault();
        
        if (!contactId) {
            showError('Contact ID is missing. Please refresh the page.');
            return;
        }
        
        const formData = {
            action: 'dsl_update_contact',
            nonce: dslData.nonce,
            contact_id: contactId,
            firstname: $('#dsl_firstname').val(),
            lastname: $('#dsl_lastname').val(),
            telephone1: $('#dsl_phone').val(),
            address1_line1: $('#dsl_address').val(),
            address1_city: $('#dsl_city').val(),
            address1_stateorprovince: $('#dsl_state').val(),
            address1_postalcode: $('#dsl_postal').val(),
            address1_country: $('#dsl_country').val()
        };
        
        const $submitBtn = $('#dsl-contact-form button[type="submit"]');
        const originalText = $submitBtn.text();
        
        $submitBtn.prop('disabled', true).text(dslData.strings.saving);
        hideMessage();
        
        $.ajax({
            url: dslData.ajaxUrl,
            type: 'POST',
            data: formData,
            success: function(response) {
                if (response.success) {
                    showSuccess(response.data.message || dslData.strings.success);
                    if (response.data.contact) {
                        populateForm(response.data.contact);
                    }
                } else {
                    showError(response.data || dslData.strings.error);
                }
            },
            error: function() {
                showError(dslData.strings.error);
            },
            complete: function() {
                $submitBtn.prop('disabled', false).text(originalText);
            }
        });
    }
    
    /**
     * Show loading state
     */
    function showLoading() {
        $('.dsl-loading').show();
        $('#dsl-form-fields').hide();
    }
    
    /**
     * Hide loading state
     */
    function hideLoading() {
        $('.dsl-loading').hide();
    }
    
    /**
     * Show form
     */
    function showForm() {
        hideLoading();
        $('#dsl-form-fields').slideDown();
    }
    
    /**
     * Show success message
     */
    function showSuccess(message) {
        $('#dsl-message')
            .removeClass('dsl-notice-error')
            .addClass('dsl-notice dsl-notice-success')
            .html('<p>' + escapeHtml(message) + '</p>')
            .slideDown();
        
        setTimeout(function() {
            $('#dsl-message').slideUp();
        }, 5000);
    }
    
    /**
     * Show error message
     */
    function showError(message) {
        $('#dsl-message')
            .removeClass('dsl-notice-success')
            .addClass('dsl-notice dsl-notice-error')
            .html('<p>' + escapeHtml(message) + '</p>')
            .slideDown();
    }
    
    /**
     * Hide message
     */
    function hideMessage() {
        $('#dsl-message').slideUp();
    }
    
    /**
     * Escape HTML
     */
    function escapeHtml(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, function(m) { return map[m]; });
    }
    
    // Initialize on document ready
    $(document).ready(init);
    
})(jQuery);