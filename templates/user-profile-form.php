<?php
/**
 * User Profile Form Template
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="dsl-profile-container">
    <div class="dsl-profile-header">
        <h2><?php echo esc_html($atts['title']); ?></h2>
        <p class="dsl-subtitle"><?php _e('Manage your contact information synced with Dynamics 365', 'dynamics-sync-lite'); ?></p>
    </div>
    
    <div id="dsl-message-container"></div>
    
    <div id="dsl-loading" class="dsl-loading" style="display: none;">
        <div class="dsl-spinner"></div>
        <p><?php _e('Loading your profile...', 'dynamics-sync-lite'); ?></p>
    </div>
    
    <form id="dsl-profile-form" class="dsl-form" style="display: none;">
        <div class="dsl-form-row">
            <div class="dsl-form-group">
                <label for="dsl-firstname">
                    <?php _e('First Name', 'dynamics-sync-lite'); ?> 
                    <span class="required">*</span>
                </label>
                <input type="text" 
                       id="dsl-firstname" 
                       name="firstname" 
                       class="dsl-input" 
                       required 
                       autocomplete="given-name" />
            </div>
            
            <div class="dsl-form-group">
                <label for="dsl-lastname">
                    <?php _e('Last Name', 'dynamics-sync-lite'); ?> 
                    <span class="required">*</span>
                </label>
                <input type="text" 
                       id="dsl-lastname" 
                       name="lastname" 
                       class="dsl-input" 
                       required 
                       autocomplete="family-name" />
            </div>
        </div>
        
        <div class="dsl-form-row">
            <div class="dsl-form-group">
                <label for="dsl-email">
                    <?php _e('Email Address', 'dynamics-sync-lite'); ?> 
                    <span class="required">*</span>
                </label>
                <input type="email" 
                       id="dsl-email" 
                       name="email" 
                       class="dsl-input" 
                       required 
                       autocomplete="email" />
            </div>
            
            <div class="dsl-form-group">
                <label for="dsl-phone">
                    <?php _e('Phone Number', 'dynamics-sync-lite'); ?>
                </label>
                <input type="tel" 
                       id="dsl-phone" 
                       name="phone" 
                       class="dsl-input" 
                       autocomplete="tel" />
            </div>
        </div>
        
        <div class="dsl-form-section">
            <h3><?php _e('Address Information', 'dynamics-sync-lite'); ?></h3>
            
            <div class="dsl-form-group">
                <label for="dsl-address">
                    <?php _e('Street Address', 'dynamics-sync-lite'); ?>
                </label>
                <input type="text" 
                       id="dsl-address" 
                       name="address" 
                       class="dsl-input" 
                       autocomplete="address-line1" />
            </div>
            
            <div class="dsl-form-row">
                <div class="dsl-form-group">
                    <label for="dsl-city">
                        <?php _e('City', 'dynamics-sync-lite'); ?>
                    </label>
                    <input type="text" 
                           id="dsl-city" 
                           name="city" 
                           class="dsl-input" 
                           autocomplete="address-level2" />
                </div>
                
                <div class="dsl-form-group">
                    <label for="dsl-state">
                        <?php _e('State/Province', 'dynamics-sync-lite'); ?>
                    </label>
                    <input type="text" 
                           id="dsl-state" 
                           name="state" 
                           class="dsl-input" 
                           autocomplete="address-level1" />
                </div>
            </div>
            
            <div class="dsl-form-row">
                <div class="dsl-form-group">
                    <label for="dsl-postal-code">
                        <?php _e('Postal Code', 'dynamics-sync-lite'); ?>
                    </label>
                    <input type="text" 
                           id="dsl-postal-code" 
                           name="postal_code" 
                           class="dsl-input" 
                           autocomplete="postal-code" />
                </div>
                
                <div class="dsl-form-group">
                    <label for="dsl-country">
                        <?php _e('Country', 'dynamics-sync-lite'); ?>
                    </label>
                    <input type="text" 
                           id="dsl-country" 
                           name="country" 
                           class="dsl-input" 
                           autocomplete="country-name" />
                </div>
            </div>
        </div>
        
        <div class="dsl-form-actions">
            <button type="submit" class="dsl-button dsl-button-primary" id="dsl-submit-btn">
                <?php _e('Update Profile', 'dynamics-sync-lite'); ?>
            </button>
            <span class="dsl-sync-status" id="dsl-sync-status"></span>
        </div>
    </form>
</div>