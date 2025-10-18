/**
 * OAuth Independent Profile Script
 */

(function($) {
    'use strict';

    const OAuthProfile = {
        
        init: function() {
            this.form = $('#dsl-oauth-profile-form');
            this.loading = $('#dsl-oauth-loading');
            this.messageContainer = $('#dsl-oauth-message-container');
            this.profileSection = $('#dsl-oauth-profile-section');
            this.loginSection = $('#dsl-oauth-login-section');
            this.submitBtn = $('#dsl-oauth-submit-btn');
            
            if (this.profileSection.length) {
                this.checkAuthentication();
                this.bindEvents();
            }
        },
        
        bindEvents: function() {
            this.form.on('submit', this.handleSubmit.bind(this));
        },
        
        checkAuthentication: function() {
            // Check if user is authenticated via AJAX
            $.ajax({
                url: dslAjax.ajaxurl,
                type: 'POST',
                data: {
                    action: 'dsl_check_oauth_session',
                    nonce: dslAjax.nonce
                },
                success: this.handleAuthCheck.bind(this)
            });
        },
        
        handleAuthCheck: function(response) {
            if (response.success && response.data.authenticated) {
                // User is authenticated - load profile
                this.loginSection.hide();
                this.profileSection.show();
                this.loadProfile();
            } else {
                // User not authenticated - show login
                this.profileSection.hide();
                this.loginSection.show();
            }
        },
        
        loadProfile: function() {
            this.showLoading();
            
            $.ajax({
                url: dslAjax.ajaxurl,
                type: 'POST',
                data: {
                    action: 'dsl_oauth_get_profile',
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
                
                // Populate form
                $('#dsl-oauth-firstname').val(contact.firstname || '');
                $('#dsl-oauth-lastname').val(contact.lastname || '');
                $('#dsl-oauth-email').val(contact.emailaddress1 || '');
                $('#dsl-oauth-phone').val(contact.telephone1 || '');
                $('#dsl-oauth-address').val(contact.address1_line1 || '');
                $('#dsl-oauth-city').val(contact.address1_city || '');
                $('#dsl-oauth-state').val(contact.address1_stateorprovince || '');
                $('#dsl-oauth-postal-code').val(contact.address1_postalcode || '');
                $('#dsl-oauth-country').val(contact.address1_country || '');
                
                this.form.fadeIn(300);
                
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
                action: 'dsl_oauth_update_profile',
                nonce: dslAjax.nonce,
                firstname: $('#dsl-oauth-firstname').val().trim(),
                lastname: $('#dsl-oauth-lastname').val().trim(),
                email: $('#dsl-oauth-email').val().trim(),
                phone: $('#dsl-oauth-phone').val().trim(),
                address: $('#dsl-oauth-address').val().trim(),
                city: $('#dsl-oauth-city').val().trim(),
                state: $('#dsl-oauth-state').val().trim(),
                postal_code: $('#dsl-oauth-postal-code').val().trim(),
                country: $('#dsl-oauth-country').val().trim()
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
            const requiredFields = ['oauth-firstname', 'oauth-lastname'];
            
            requiredFields.forEach(function(fieldId) {
                const field = $('#dsl-' + fieldId);
                const value = field.val().trim();
                
                if (!value) {
                    field.addClass('dsl-error');
                    isValid = false;
                } else {
                    field.removeClass('dsl-error');
                }
            });
            
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
                this.submitBtn.prop('disabled', true).text(dslAjax.strings.updating);
                this.form.addClass('dsl-loading-overlay');
            } else {
                this.submitBtn.prop('disabled', false).text('Save Changes');
                this.form.removeClass('dsl-loading-overlay');
            }
        },
        
        showMessage: function(message, type) {
            const messageHtml = '<div class="dsl-oauth-notice dsl-oauth-notice-' + type + '">' + 
                message + '</div>';
            this.messageContainer.html(messageHtml);
            
            if (type === 'success') {
                setTimeout(() => this.clearMessages(), 5000);
            }
        },
        
        clearMessages: function() {
            this.messageContainer.fadeOut(200, function() {
                $(this).empty().show();
            });
        }
    };

    $(document).ready(function() {
        OAuthProfile.init();
    });

})(jQuery);