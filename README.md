# Dynamics Sync Lite - Complete Edition

**Version:** 1.2.0  
**A WordPress Plugin for Microsoft Dynamics 365 Integration**

## 🎯 Overview

Dynamics Sync Lite is a comprehensive WordPress plugin that seamlessly integrates with Microsoft Dynamics 365, allowing users to manage their contact information through a secure, user-friendly interface. The plugin supports both OAuth-based authentication (with or without WordPress accounts) and traditional WordPress user authentication.

### Key Features

✅ **Dual Authentication Modes**
- OAuth authentication (Microsoft login) without requiring WordPress accounts
- Traditional WordPress user authentication with Dynamics sync
- Demo mode for testing without API credentials

✅ **Real-Time Bidirectional Sync**
- Instant synchronization with Dynamics 365
- View and update contact information
- Automatic conflict resolution

✅ **Admin Dashboard Widget**
- Live sync statistics
- Recent activity monitoring
- Error tracking and success rates

✅ **Comprehensive Logging**
- Detailed API call logging
- User action tracking
- Automatic log cleanup (30 days)

✅ **Security First**
- OAuth 2.0 implementation
- Nonce verification on all requests
- Input sanitization and validation
- HTTPS enforcement

✅ **Developer Friendly**
- Well-documented code
- WordPress coding standards
- Extensible architecture
- Multiple shortcodes

---

## 📋 Requirements

### Minimum Requirements
- **WordPress:** 5.8 or higher
- **PHP:** 7.4 or higher
- **SSL Certificate:** HTTPS enabled (required for OAuth)
- **Database:** MySQL 5.6+ or MariaDB 10.0+

### Microsoft Requirements
- Microsoft Dynamics 365 instance
- Azure Active Directory tenant
- Application registration in Azure AD
- API permissions configured

---

## 🚀 Installation

### Step 1: Install the Plugin

**Option A: Via GitHub**
```bash
cd wp-content/plugins/
git clone https://github.com/yourusername/dynamics-sync-lite.git
```

**Option B: Manual Upload**
1. Download the plugin ZIP file
2. Go to WordPress Admin → Plugins → Add New
3. Click "Upload Plugin"
4. Choose the ZIP file and click "Install Now"

### Step 2: Activate the Plugin

1. Go to **WordPress Admin → Plugins**
2. Find "Dynamics Sync Lite - Complete Edition"
3. Click **"Activate"**

✅ The plugin will automatically:
- Create the logs database table
- Enable demo mode for testing
- Set up default configuration
- Register OAuth endpoints

---

## ⚙️ Configuration

### Quick Start with Demo Mode

**Perfect for testing without API credentials!**

1. Go to **Settings → Dynamics Sync**
2. **Demo Mode** is enabled by default
3. Create a page and add shortcode: `[dynamics_profile_oauth]`
4. Visit the page and click "Sign In with Microsoft"
5. You'll be automatically logged in as a demo user

### Production Setup

#### Part 1: Azure AD Configuration

**1. Register Application in Azure AD**

Navigate to [Azure Portal](https://portal.azure.com):

```
Azure Active Directory → App registrations → New registration
```

- **Name:** Dynamics Sync Lite (or your choice)
- **Supported account types:** Single tenant
- **Redirect URI:** `https://yoursite.com/dynamics-oauth-callback/`

Click **"Register"**

**2. Note Your Credentials**

After registration, copy these values:
- ✅ **Application (client) ID** - Found on Overview page
- ✅ **Directory (tenant) ID** - Found on Overview page

**3. Create Client Secret**

```
Certificates & secrets → New client secret
```

- Add description: "Dynamics Sync Lite Secret"
- Choose expiration period
- Click "Add"
- **⚠️ IMPORTANT:** Copy the **Value** immediately (you won't see it again!)

**4. Configure API Permissions**

```
API permissions → Add a permission → Dynamics CRM
```

Select permissions:
- ✅ `user_impersonation` (Delegated)
- ✅ Or application-specific permissions as needed

Click **"Grant admin consent"** (requires admin rights)

**5. Configure Authentication**

```
Authentication → Add a platform → Web
```

Add redirect URI:
```
https://yoursite.com/dynamics-oauth-callback/
```

Enable:
- ✅ Access tokens
- ✅ ID tokens

#### Part 2: Dynamics 365 Configuration

**1. Create Application User in Dynamics**

```
Settings → Security → Application Users → New
```

- **User Type:** Application User
- **Application ID:** Your Client ID from Azure
- **Full Name:** Dynamics Sync Lite
- Assign appropriate security roles

**2. Grant Permissions**

Assign these security roles:
- Basic User
- Customer Service Representative (or custom role with contact access)

#### Part 3: WordPress Plugin Configuration

1. Go to **Settings → Dynamics Sync**

2. **Disable Demo Mode** (uncheck the box)

3. **Enter API Configuration:**

```
Client ID: [Your Application (client) ID]
Client Secret: [Your client secret value]
Tenant ID: [Your Directory (tenant) ID]
Resource URL: https://yourorg.crm.dynamics.com/
API Version: 9.2
```

4. **Configure OAuth Settings:**

```
☑ Enable OAuth Login
☑ Auto-Register Users
Redirect After Login: [Your profile page URL]
```

5. **Save Settings**

6. **Test Connection** (click the button at bottom of page)

✅ You should see: "Connection successful! API is working correctly."

---

## 📖 Usage Guide

### For End Users

#### OAuth Users (No WordPress Account)

**1. Add Login Button to Page**

Create a page and add:
```
[dynamics_login_independent]
```

**2. Add Profile Form to Page**

Create another page and add:
```
[dynamics_profile_oauth]
```

**User Flow:**
1. User clicks "Sign In with Microsoft"
2. Redirected to Microsoft login
3. Grants permissions
4. Redirected back to your site
5. Can view/edit their Dynamics contact info
6. No WordPress account created

#### WordPress Users (Traditional)

**1. User Must Be Logged Into WordPress**

**2. Add Profile Shortcode**

Create a page and add:
```
[dynamics_user_profile]
```

**User Flow:**
1. User logs into WordPress
2. Visits profile page
3. Sees their Dynamics contact information
4. Can edit and save changes
5. Changes sync to Dynamics automatically

### For Administrators

#### Dashboard Widget

**Location:** WordPress Dashboard

**Features:**
- Total synced users count
- Success/Error/Info log counts
- Last sync timestamp
- Recent activity log (last 10 entries)

**Monitoring:**
- Green = Success
- Red = Error
- Blue = Info

#### View Detailed Logs

Logs are stored in database table `wp_dsl_logs`

**Access via:**
1. Dashboard widget (recent 10)
2. Database query (all logs)
3. Settings page (log statistics)

**Log Levels:**
- `success` - Successful operations
- `error` - Failed operations
- `info` - Informational messages

#### Clear Logs

Go to **Settings → Dynamics Sync → Advanced Settings**

Click **"Clear All Logs"** button

⚠️ This action cannot be undone!

---

## 🔧 Available Shortcodes

### Primary Shortcodes

| Shortcode | Description | Requirements |
|-----------|-------------|--------------|
| `[dynamics_profile_oauth]` | OAuth profile form | OAuth enabled |
| `[dynamics_login_independent]` | OAuth login button | OAuth enabled |
| `[dynamics_user_profile]` | WordPress user profile | User logged in |
| `[dynamics_login]` | OAuth login (creates WP user) | OAuth enabled |

### Shortcode Parameters

**dynamics_profile_oauth:**
```
[dynamics_profile_oauth title="My Profile"]
```

**dynamics_login_independent:**
```
[dynamics_login_independent text="Custom Button Text" class="my-custom-class"]
```

**dynamics_user_profile:**
```
[dynamics_user_profile title="My Dynamics Profile"]
```

---

## 🏗️ Architecture

### Plugin Structure

```
dynamics-sync-lite/
├── admin/
│   ├── css/
│   │   └── admin-style.css
│   └── js/
│       └── admin-script.js
├── includes/
│   ├── class-admin-widget.php
│   ├── class-demo-mode.php
│   ├── class-dynamics-api.php
│   ├── class-logger.php
│   ├── class-oauth-independent-profile.php
│   ├── class-oauth-login-session.php
│   ├── class-oauth-login.php
│   ├── class-settings.php
│   └── class-user-profile.php
├── public/
│   ├── css/
│   │   ├── oauth-profile-style.css
│   │   └── public-style.css
│   └── js/
│       ├── oauth-profile-script.js
│       └── public-script.js
├── templates/
│   ├── admin-widget.php
│   └── user-profile-form.php
├── dynamics-sync-lite.php
├── uninstall.php
└── README.md
```

### Key Classes

**DSL_Dynamics_API**
- Handles all Dynamics 365 API communication
- OAuth 2.0 token management
- Contact CRUD operations
- Error handling

**DSL_OAuth_Login_Session**
- Session-based OAuth authentication
- No WordPress user creation
- Microsoft Graph API integration

**DSL_OAuth_Login**
- OAuth with WordPress user creation
- Auto-registration support
- User profile mapping

**DSL_User_Profile**
- WordPress user profile management
- Dynamics contact sync
- Form handling and validation

**DSL_OAuth_Independent_Profile**
- Session-based profile management
- No WordPress dependency
- Direct Dynamics updates

**DSL_Admin_Widget**
- Dashboard statistics
- Activity monitoring
- Recent logs display

**DSL_Logger**
- Activity logging
- Database storage
- Auto-cleanup

### Data Flow

#### OAuth Flow (Session-Based)
```
User clicks "Sign In"
    ↓
Redirect to Microsoft Login
    ↓
User authenticates with Microsoft
    ↓
Microsoft redirects back with code
    ↓
Exchange code for access token
    ↓
Get user info from Microsoft Graph
    ↓
Store in PHP session (no WP user)
    ↓
User can access profile form
    ↓
Profile changes sync to Dynamics
```

#### WordPress User Flow
```
User logs into WordPress
    ↓
Loads profile form
    ↓
AJAX request to get Dynamics contact
    ↓
OAuth token obtained (cached)
    ↓
API call to Dynamics
    ↓
Contact data returned
    ↓
Form populated
    ↓
User edits and submits
    ↓
Data validated and sanitized
    ↓
API call to update/create contact
    ↓
WordPress user meta updated
    ↓
Success message shown
```

---

## 🔐 Security Features

### Authentication
- ✅ OAuth 2.0 with PKCE flow
- ✅ Secure token storage (transients)
- ✅ Token refresh handling
- ✅ Session management

### Data Protection
- ✅ Input sanitization (all fields)
- ✅ Output escaping (XSS prevention)
- ✅ SQL injection prevention (prepared statements)
- ✅ Nonce verification (CSRF protection)

### API Security
- ✅ HTTPS enforcement
- ✅ Token caching (performance + security)
- ✅ Rate limit handling
- ✅ Error message sanitization

### Access Control
- ✅ Capability checks (admin functions)
- ✅ User authentication verification
- ✅ Session validation
- ✅ AJAX nonce verification

---

## 🐛 Troubleshooting

### Common Issues

**❌ "Failed to obtain access token"**

**Causes:**
- Incorrect Client ID/Secret
- Expired client secret
- Wrong Tenant ID
- API permissions not granted

**Solutions:**
1. Verify credentials in Azure AD
2. Check client secret expiration
3. Ensure Tenant ID format: `xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx`
4. Grant admin consent for API permissions
5. Check Settings → Dynamics Sync logs

**❌ "Contact not found in Dynamics"**

**Causes:**
- Email doesn't match in Dynamics
- Contact doesn't exist
- Insufficient API permissions

**Solutions:**
1. Verify email address matches exactly
2. Check contact exists in Dynamics 365
3. Verify API permissions include contact read
4. Test with known existing contact

**❌ "Connection test failed"**

**Causes:**
- Resource URL format incorrect
- Network/firewall issues
- API endpoint unavailable

**Solutions:**
1. Ensure Resource URL ends with `/`
   - Correct: `https://yourorg.crm.dynamics.com/`
   - Wrong: `https://yourorg.crm.dynamics.com`
2. Verify your site uses HTTPS
3. Check firewall/network settings
4. Test API endpoint availability

**❌ "OAuth callback failed"**

**Causes:**
- Redirect URI not configured in Azure
- State verification failed
- Session not started

**Solutions:**
1. Add callback URL to Azure AD app
2. Clear browser cache and cookies
3. Check PHP session configuration
4. Verify site URL matches Azure redirect URI exactly

**❌ "Dashboard widget not showing"**

**Causes:**
- Widget not initialized
- Permission issues
- Plugin not fully activated

**Solutions:**
1. Deactivate and reactivate plugin
2. Check user has `manage_options` capability
3. Clear WordPress cache
4. Check for JavaScript errors in console

### Enable Debug Mode

**1. Enable WordPress Debug**

Edit `wp-config.php`:
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
```

**2. Enable Plugin Logging**

Settings → Dynamics Sync → Advanced Settings
- ☑ Enable Logging

**3. Check Logs**

- Dashboard widget shows recent logs
- Database: `wp_dsl_logs` table
- WordPress debug.log: `wp-content/debug.log`

### Getting Support

**Before requesting support:**
1. ✅ Check this troubleshooting section
2. ✅ Review activity logs in dashboard
3. ✅ Test connection in settings
4. ✅ Check browser console for errors
5. ✅ Verify all credentials are correct

**When reporting issues, include:**
- WordPress version
- PHP version
- Plugin version
- Error messages (full text)
- Steps to reproduce
- Browser console errors
- Relevant log entries

---

## 📊 Database Schema

### Log Table

**Table Name:** `wp_dsl_logs`

```sql
CREATE TABLE wp_dsl_logs (
    id bigint(20) NOT NULL AUTO_INCREMENT,
    log_time datetime NOT NULL,
    log_level varchar(20) NOT NULL,
    message text NOT NULL,
    context longtext,
    user_id bigint(20),
    ip_address varchar(45),
    PRIMARY KEY (id),
    KEY log_level (log_level),
    KEY log_time (log_time),
    KEY user_id (user_id)
);
```

### User Meta

**Keys:**
- `dsl_contact_id` - Dynamics contact GUID
- `dsl_last_sync` - Last sync timestamp
- `dsl_microsoft_id` - Microsoft user ID
- `phone` - User phone number
- `dsl_address_*` - Address components

### Options

**Plugin Settings:**
- `dsl_client_id`
- `dsl_client_secret`
- `dsl_tenant_id`
- `dsl_resource_url`
- `dsl_api_version`
- `dsl_enable_logging`
- `dsl_demo_mode`
- `dsl_enable_oauth_login`
- `dsl_oauth_auto_register`
- `dsl_oauth_redirect_url`

### Transients

- `dsl_access_token` - Cached OAuth token (expires in ~55 min)
- `dsl_oauth_state_*` - OAuth state verification (10 min)
- `dsl_last_log_clean` - Log cleanup timestamp (24 hours)

---

## 🎨 Customization

### CSS Customization

**Override Styles:**

```css
/* In your theme's style.css */

/* Profile Container */
.dsl-profile-container {
    background: #f5f5f5;
    border: 2px solid #333;
    max-width: 900px;
}

/* Primary Button */
.dsl-button-primary {
    background-color: #your-brand-color;
    border-radius: 8px;
}

/* OAuth Button */
.dsl-oauth-button {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}

/* Form Inputs */
.dsl-input {
    border-color: #your-color;
    border-radius: 8px;
}
```

### Add Custom Fields

**Step 1: Update API Class**

Edit `includes/class-dynamics-api.php`:

```php
// In get_contact_by_email()
$select = 'contactid,firstname,lastname,emailaddress1,telephone1,yourcustomfield';

// In update_contact()
if (isset($data['yourcustomfield'])) {
    $update_data['yourcustomfield'] = sanitize_text_field($data['yourcustomfield']);
}
```

**Step 2: Update Template**

Edit `templates/user-profile-form.php`:

```php
<div class="dsl-form-group">
    <label for="dsl-customfield">Your Custom Field</label>
    <input type="text" 
           id="dsl-customfield" 
           name="customfield" 
           class="dsl-input" />
</div>
```

**Step 3: Update JavaScript**

Edit `public/js/public-script.js`:

```javascript
// In loadProfile
$('#dsl-customfield').val(contact.yourcustomfield || '');

// In handleSubmit
customfield: $('#dsl-customfield').val().trim()
```

### Hooks and Filters

**Available Hooks:**

```php
// Before profile update
do_action('dsl_before_profile_update', $user_id, $data);

// After profile update
do_action('dsl_after_profile_update', $user_id, $contact_id, $data);

// Before OAuth login
do_action('dsl_before_oauth_login', $user_info);

// After OAuth login
do_action('dsl_after_oauth_login', $user_id, $user_info);

// Before API call
do_action('dsl_before_api_call', $method, $endpoint, $data);

// After API call
do_action('dsl_after_api_call', $method, $endpoint, $response);
```

**Available Filters:**

```php
// Filter contact data before display
$contact = apply_filters('dsl_contact_data', $contact, $user_id);

// Filter form fields
$fields = apply_filters('dsl_form_fields', $fields);

// Filter API request data
$data = apply_filters('dsl_api_request_data', $data, $contact_id);

// Filter OAuth redirect URL
$redirect_url = apply_filters('dsl_oauth_redirect_url', $url, $user_info);
```

### Translations

**Plugin is translation-ready!**

**Create Translation:**

1. Use POEdit or similar tool
2. Load `/languages/dynamics-sync-lite.pot` template
3. Translate strings
4. Save as `dynamics-sync-lite-{locale}.po` and `.mo`
5. Place in `/languages/` directory

**Example:**
- Spanish: `dynamics-sync-lite-es_ES.po`
- French: `dynamics-sync-lite-fr_FR.po`
- German: `dynamics-sync-lite-de_DE.po`

---

## 📈 Performance Optimization

### Token Caching
- Access tokens cached for ~55 minutes
- Reduces API calls significantly
- Automatic refresh when expired

### Database Optimization
- Indexed log table for fast queries
- Automatic log cleanup (30 days)
- Efficient user meta queries

### AJAX Optimization
- Debounced form submissions
- Loading states prevent duplicate requests
- Minimal data transfer

### Best Practices

**For High Traffic Sites:**

1. **Enable Object Caching:**
```php
// In wp-config.php
define('WP_CACHE', true);
```

2. **Use CDN for Assets:**
```php
// In functions.php
add_filter('dsl_asset_url', function($url) {
    return str_replace(site_url(), 'https://cdn.yoursite.com', $url);
});
```

3. **Database Optimization:**
```sql
-- Add index for better performance
ALTER TABLE wp_dsl_logs ADD INDEX idx_user_level (user_id, log_level);
```

4. **Consider Batch Operations:**
```php
// Custom code for bulk sync
do_action('dsl_bulk_sync_users', $user_ids);
```

---

## 🚧 Known Limitations

### Current Limitations

1. **One-to-One Mapping**
   - One WordPress user = One Dynamics contact
   - Email is the unique identifier

2. **Email as Primary Key**
   - Email must be unique in Dynamics
   - Email changes require manual intervention

3. **No Bulk Operations**
   - No mass import/export feature
   - Individual sync only

4. **Image Upload Not Supported**
   - Profile pictures not synced
   - Consider third-party integration

5. **Rate Limiting**
   - Subject to Dynamics 365 API limits
   - Token refresh every ~60 minutes

6. **No Offline Mode**
   - Requires active internet connection
   - No queued sync for offline edits

### Planned Features

- 🔄 Webhook support for real-time Dynamics → WordPress sync
- 📦 Bulk import/export functionality
- 🖼️ Profile image synchronization
- 📱 Mobile app integration
- 🔔 Email notifications for sync events
- 📊 Advanced analytics dashboard
- 🎯 Custom field mapping interface
- 🔗 Multi-entity support (beyond contacts)

---

## 🤝 Contributing

**Contributions are welcome!**

### How to Contribute

1. **Fork the Repository**
```bash
git clone https://github.com/yourusername/dynamics-sync-lite.git
```

2. **Create Feature Branch**
```bash
git checkout -b feature/amazing-feature
```

3. **Make Changes**
- Follow WordPress coding standards
- Add comments for complex logic
- Update documentation

4. **Test Thoroughly**
- Test with demo mode
- Test with real API
- Test error scenarios

5. **Commit Changes**
```bash
git commit -m 'Add amazing feature'
```

6. **Push to Branch**
```bash
git push origin feature/amazing-feature
```

7. **Open Pull Request**
- Describe changes clearly
- Include screenshots if applicable
- Reference any related issues

### Code Standards

**Follow WordPress Coding Standards:**

```php
// Good
function dsl_get_contact( $email ) {
    $api = DSL_Dynamics_API::get_instance();
    return $api->get_contact_by_email( $email );
}

// Use meaningful variable names
$contact_data = array(
    'firstname' => sanitize_text_field( $_POST['firstname'] ),
    'lastname'  => sanitize_text_field( $_POST['lastname'] )
);

// Add docblocks
/**
 * Get Dynamics contact by email
 *
 * @param string $email User email address
 * @return array|WP_Error Contact data or error
 */
```

### Testing Checklist

Before submitting PR:

- [ ] Demo mode works correctly
- [ ] Real API integration works
- [ ] All AJAX calls have nonce verification
- [ ] All inputs are sanitized
- [ ] All outputs are escaped
- [ ] No PHP errors or warnings
- [ ] No JavaScript console errors
- [ ] Mobile responsive
- [ ] Cross-browser compatible
- [ ] Documentation updated

---

## 📄 License

GPL v2 or later

```
This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.
```

---

## 👤 Author

**Your Name**  
Website: [https://yourwebsite.com](https://yourwebsite.com)  
GitHub: [@yourusername](https://github.com/yourusername)  
Email: your.email@example.com

---

## 🙏 Acknowledgments

- Microsoft Dynamics 365 API Documentation
- WordPress Plugin Development Best Practices
- The WordPress Community
- Azure Active Directory Documentation
- All contributors and testers

---

## 📞 Support

### Documentation
- **Wiki:** [GitHub Wiki](https://github.com/yourusername/dynamics-sync-lite/wiki)
- **FAQ:** [Frequently Asked Questions](https://github.com/yourusername/dynamics-sync-lite/wiki/FAQ)

### Get Help
- **Issues:** [GitHub Issues](https://github.com/yourusername/dynamics-sync-lite/issues)
- **Discussions:** [GitHub Discussions](https://github.com/yourusername/dynamics-sync-lite/discussions)

### Stay Updated
- **Changelog:** [CHANGELOG.md](CHANGELOG.md)
- **Releases:** [GitHub Releases](https://github.com/yourusername/dynamics-sync-lite/releases)

---

## 📝 Changelog

### Version 1.2.0 (Current)
- ✅ Fixed dashboard widget initialization
- ✅ Improved OAuth session handling
- ✅ Enhanced error messages
- ✅ Added clear logs functionality
- ✅ Updated documentation
- ✅ Performance optimizations

### Version 1.1.0
- Added OAuth independent profile
- Session-based authentication
- No WordPress user requirement
- Multiple authentication modes

### Version 1.0.0
- Initial release
- Basic Dynamics 365 integration
- OAuth 2.0 authentication
- User profile management
- Admin dashboard widget
- Activity logging

---

**Built with ❤️ for nonprofits and organizations using Microsoft Dynamics 365**

---