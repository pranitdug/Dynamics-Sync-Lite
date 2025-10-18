/**
 * Dynamics Sync Lite - Public JavaScript
 */

(function($) {
    'use strict';

    const DynamicsProfile = {
        
        init: function() {
            this.form = $('#dsl-profile-form');
            this.loading = $('#dsl-loading');
            this.messageContainer = $('#dsl-message-container');
            this.submitBtn = $('#dsl-submit-btn');
            
            if (this.form.length) {
                this.loadProfile();
                this.bindEvents();
            }
        },
        
        bindEvents: function() {
            this.form.on('submit', this.handleSubmit.bind(this));
        },
        
        loadProfile: function() {
            this.showLoading();
            
            $.ajax({
                url: dslAjax.ajaxurl,
                type: 'POST',
                data: {
                    action: 'dsl_get_profile',
                    nonce: dslAjax.nonce
                },
                success: this.handleLoadSuccess.bind(this),
                error: this.handleLoadError.bind(this)
            });
        },
        
        handleLoadSuccess: function(response) {
            this.hideLoading();
            
            if (response.success) {
                const contact = response.data.contact;
                
                // Populate form fields
                $('#dsl-firstname').val(contact.firstname || '');
                $('#dsl-lastname').val(contact.lastname || '');
                $('#dsl-email').val(contact.emailaddress1 || '');
                $('#dsl-phone').val(contact.telephone1 || '');
                $('#dsl-address').val(contact.address1_line1 || '');
                $('#dsl-city').val(contact.address1_city || '');
                $('#dsl-state').val(contact.address1_stateorprovince || '');
                $('#dsl-postal-code').val(contact.address1_postalcode || '');
                $('#dsl-country').val(contact.address1_country || '');
                
                // Show form
                this.form.fadeIn(300);
                
                // Show info message if not synced
                if (contact.dynamics_sync_status === 'not_synced') {
                    this.showMessage(response.data.message, 'info');
                }
            } else {
                this.showMessage(response.data.message || dslAjax.strings.error, 'error');
            }
        },
        
        handleLoadError: function() {
            this.hideLoading();
            this.showMessage(dslAjax.strings.error, 'error');
        },
        
        handleSubmit: function(e) {
            e.preventDefault();
            
            if (!this.validateForm()) {
                return;
            }
            
            const formData = {
                action: 'dsl_update_profile',
                nonce: dslAjax.nonce,
                firstname: $('#dsl-firstname').val().trim(),
                lastname: $('#dsl-lastname').val().trim(),
                email: $('#dsl-email').val().trim(),
                phone: $('#dsl-phone').val().trim(),
                address: $('#dsl-address').val().trim(),
                city: $('#dsl-city').val().trim(),
                state: $('#dsl-state').val().trim(),
                postal_code: $('#dsl-postal-code').val().trim(),
                country: $('#dsl-country').val().trim()
            };
            
            this.setFormLoading(true);
            this.clearMessages();
            
            $.ajax({
                url: dslAjax.ajaxurl,
                type: 'POST',
                data: formData,
                success: this.handleSubmitSuccess.bind(this),
                error: this.handleSubmitError.bind(this)
            });
        },
        
        handleSubmitSuccess: function(response) {
            this.setFormLoading(false);
            
            if (response.success) {
                this.showMessage(response.data.message, 'success');
                
                // Scroll to message
                $('html, body').animate({
                    scrollTop: this.messageContainer.offset().top - 100
                }, 300);
            } else {
                this.showMessage(response.data.message || dslAjax.strings.error, 'error');
            }
        },
        
        handleSubmitError: function() {
            this.setFormLoading(false);
            this.showMessage(dslAjax.strings.error, 'error');
        },
        
        validateForm: function() {
            let isValid = true;
            const requiredFields = ['firstname', 'lastname', 'email'];
            
            requiredFields.forEach(function(fieldName) {
                const field = $('#dsl-' + fieldName);
                const value = field.val().trim();
                
                if (!value) {
                    field.addClass('dsl-error');
                    isValid = false;
                } else {
                    field.removeClass('dsl-error');
                }
            });
            
            // Validate email format
            const email = $('#dsl-email').val().trim();
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            
            if (email && !emailRegex.test(email)) {
                $('#dsl-email').addClass('dsl-error');
                this.showMessage('Please enter a valid email address.', 'error');
                isValid = false;
            }
            
            if (!isValid) {
                this.showMessage('Please fill in all required fields.', 'error');
            }
            
            return isValid;
        },
        
        showLoading: function() {
            this.loading.fadeIn(200);
            this.form.hide();
        },
        
        hideLoading: function() {
            this.loading.fadeOut(200);
        },
        
        setFormLoading: function(loading) {
            if (loading) {
                this.submitBtn.prop('disabled', true)
                    .text(dslAjax.strings.updating);
                this.form.addClass('dsl-loading-overlay');
                $('#dsl-sync-status').text(dslAjax.strings.updating);
            } else {
                this.submitBtn.prop('disabled', false)
                    .text(this.submitBtn.data('original-text') || 'Update Profile');
                this.form.removeClass('dsl-loading-overlay');
                $('#dsl-sync-status').text('');
            }
        },
        
        showMessage: function(message, type) {
            const messageHtml = '<div class="dsl-notice dsl-notice-' + type + '">' + 
                message + '</div>';
            
            this.messageContainer.html(messageHtml);
            
            // Auto-hide success messages after 5 seconds
            if (type === 'success') {
                setTimeout(function() {
                    this.clearMessages();
                }.bind(this), 5000);
            }
        },
        
        clearMessages: function() {
            this.messageContainer.fadeOut(200, function() {
                $(this).empty().show();
            });
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        DynamicsProfile.init();
    });

})(jQuery);