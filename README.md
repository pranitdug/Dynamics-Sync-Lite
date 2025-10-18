# Dynamics Sync Lite

A WordPress plugin that seamlessly integrates with Microsoft Dynamics 365, allowing logged-in users to view and update their contact information in real-time.

## 🎯 Overview

Dynamics Sync Lite bridges the gap between WordPress and Microsoft Dynamics 365, enabling nonprofits and organizations to provide their users with a self-service portal for managing contact information. Users can view and update their details directly on your WordPress site, with changes automatically synced to Dynamics 365.

## ✨ Features

- **Real-time Sync**: Instant bidirectional synchronization with Dynamics 365
- **Secure Authentication**: OAuth 2.0 implementation for secure API access
- **User-Friendly Interface**: Clean, responsive form for profile management
- **Admin Dashboard Widget**: Monitor sync activity and view statistics at a glance
- **Activity Logging**: Comprehensive logging system for debugging and audit trails
- **WordPress Best Practices**: Built with security, performance, and maintainability in mind
- **Shortcode Support**: Easy integration into any page with `[dynamics_user_profile]`

## 📋 Requirements

- WordPress 5.8 or higher
- PHP 7.4 or higher
- Microsoft Dynamics 365 instance
- Azure AD application with API permissions
- HTTPS enabled (required for OAuth 2.0)

## 🚀 Installation

### 1. Clone the Repository

```bash
cd wp-content/plugins/
git clone https://github.com/pranitdug/dynamics-sync-lite.git
```

### 2. Activate the Plugin

- Go to WordPress Admin → Plugins
- Find "Dynamics Sync Lite" in the list
- Click "Activate"

### 3. Configure Azure AD Application

#### Step-by-Step Azure Setup:

1. **Register an Application in Azure AD**
   - Go to [Azure Portal](https://portal.azure.com)
   - Navigate to Azure Active Directory → App registrations
   - Click "New registration"
   - Name: "Dynamics Sync Lite" (or your preference)
   - Supported account types: "Accounts in this organizational directory only"
   - Click "Register"

2. **Note Your Application Details**
   - Copy the **Application (client) ID**
   - Copy the **Directory (tenant) ID**

3. **Create a Client Secret**
   - Go to "Certificates & secrets"
   - Click "New client secret"
   - Add a description and set expiration
   - Copy the **Value** (this is your client secret)
   - ⚠️ Important: Copy it immediately - you won't see it again!

4. **Configure API Permissions**
   - Go to "API permissions"
   - Click "Add a permission"
   - Select "Dynamics CRM"
   - Choose "Delegated permissions" or "Application permissions"
   - Select: `user_impersonation` (for delegated) or appropriate permissions
   - Click "Add permissions"
   - Click "Grant admin consent" (requires admin privileges)

5. **Configure Authentication (Optional)**
   - Go to "Authentication"
   - Add your WordPress site URL as a redirect URI if needed

### 4. Configure Plugin Settings

1. Go to **WordPress Admin → Settings → Dynamics Sync**

2. Enter your credentials:
   - **Client ID**: Your Application (client) ID from Azure
   - **Client Secret**: The secret value you copied
   - **Tenant ID**: Your Directory (tenant) ID
   - **Resource URL**: Your Dynamics URL (e.g., `https://yourorg.crm.dynamics.com/`)
   - **API Version**: Usually `9.2` (default)

3. Click "Save Settings"

4. Click "Test Connection" to verify everything works

## 📖 Usage

### For End Users

1. **Add the shortcode to any page:**
   ```
   [dynamics_user_profile]
   ```

2. **Users must be logged in** to view and edit their profile

3. **Profile data includes:**
   - First Name & Last Name
   - Email Address
   - Phone Number
   - Complete Address (Street, City, State, Postal Code, Country)

### For Administrators

1. **View Dashboard Widget**
   - Go to WordPress Dashboard
   - See "Dynamics Sync Activity" widget
   - Monitor sync statistics and recent activity

2. **Review Logs**
   - Logs are stored in the database
   - Enable/disable logging in settings
   - Logs automatically clean up after 30 days

## 🏗️ Architecture & Design Decisions

### Plugin Structure

```
dynamics-sync-lite/
├── includes/           # Core functionality classes
├── admin/             # Admin-specific assets
├── public/            # Frontend assets
├── templates/         # PHP templates
└── languages/         # Translation files
```

### Key Design Patterns

1. **Singleton Pattern**: Used for main classes to ensure single instances
2. **Separation of Concerns**: Clear separation between API, settings, and UI logic
3. **Security First**: 
   - Nonce verification on all AJAX calls
   - Input sanitization and validation
   - Capability checks for admin functions
   - HTTPS enforcement for API calls

### API Communication Flow

```
User Form Submission
    ↓
WordPress AJAX Handler (with nonce verification)
    ↓
Data Sanitization & Validation
    ↓
OAuth 2.0 Token Request (cached for performance)
    ↓
Dynamics 365 API Call (PATCH or POST)
    ↓
Response Handling & User Feedback
    ↓
WordPress User Meta Update
    ↓
Activity Logging
```

### Security Measures

- **OAuth 2.0**: Secure authentication with token caching
- **Nonce Verification**: All AJAX requests verified
- **Data Sanitization**: All inputs sanitized before processing
- **HTTPS Only**: API calls require secure connection
- **Capability Checks**: Admin functions restricted appropriately
- **SQL Injection Prevention**: Using WordPress prepared statements

### Performance Optimizations

- **Token Caching**: Access tokens cached to minimize API calls
- **Transient Storage**: Using WordPress transients for temporary data
- **Lazy Loading**: Components loaded only when needed
- **Database Indexing**: Log table properly indexed for fast queries

## 🔧 Development

### File Structure Explained

**Core Classes:**
- `class-dynamics-api.php`: Handles all Dynamics 365 API communication
- `class-settings.php`: Manages admin settings page
- `class-user-profile.php`: Handles user profile form and updates
- `class-admin-widget.php`: Dashboard widget functionality
- `class-logger.php`: Activity logging system

**Templates:**
- `user-profile-form.php`: Frontend profile form
- `admin-widget.php`: Dashboard widget display

### Adding Custom Fields

To add new fields to sync with Dynamics:

1. **Update the API class** (`class-dynamics-api.php`):
```php
// In get_contact_by_email method, add to $select
$endpoint = "contacts?\$filter=emailaddress1 eq '{$email}'&\$select=contactid,firstname,lastname,emailaddress1,telephone1,yourcustomfield";

// In update_contact method, add sanitization
if (isset($data['yourcustomfield'])) {
    $update_data['yourcustomfield'] = sanitize_text_field($data['yourcustomfield']);
}
```

2. **Update the form template** (`user-profile-form.php`):
```html
<div class="dsl-form-group">
    <label for="dsl-customfield">Your Custom Field</label>
    <input type="text" id="dsl-customfield" name="customfield" class="dsl-input" />
</div>
```

3. **Update the JavaScript** (`public-script.js`):
```javascript
// In loadProfile method
$('#dsl-customfield').val(contact.yourcustomfield || '');

// In handleSubmit method
customfield: $('#dsl-customfield').val().trim(),
```

### Hooks and Filters

The plugin provides several hooks for extensibility:

```php
// Before profile update
do_action('dsl_before_profile_update', $user_id, $data);

// After profile update
do_action('dsl_after_profile_update', $user_id, $contact_id, $data);

// Filter contact data before display
$contact = apply_filters('dsl_contact_data', $contact, $user_id);
```

## 🐛 Known Limitations

1. **Single Contact Per User**: Plugin assumes one-to-one mapping between WordPress users and Dynamics contacts
2. **Email as Primary Key**: Uses email address to match contacts (must be unique)
3. **No Bulk Import**: Manual sync only (no mass import of existing contacts)
4. **Token Expiration**: Requires periodic token refresh (handled automatically)
5. **Rate Limiting**: Subject to Dynamics 365 API rate limits
6. **No Image Sync**: Profile pictures not currently supported

## 🔍 Troubleshooting

### Common Issues

**Problem: "Failed to authenticate with Dynamics"**
- Verify Client ID, Client Secret, and Tenant ID are correct
- Ensure API permissions are granted in Azure AD
- Check that admin consent has been provided

**Problem: "Contact not found in Dynamics"**
- User's email must match exactly in Dynamics
- Check that contact exists in your Dynamics instance
- Verify API permissions allow reading contacts

**Problem: "Connection test failed"**
- Ensure Resource URL ends with `/` (e.g., `https://yourorg.crm.dynamics.com/`)
- Verify your site is using HTTPS
- Check firewall/network settings

**Problem: "Updates not saving"**
- Check browser console for JavaScript errors
- Verify nonce is being generated correctly
- Review activity logs for error messages

### Enable Debug Logging

1. Go to Settings → Dynamics Sync
2. Enable "Enable Logging"
3. Reproduce the issue
4. Check database table `wp_dsl_logs` for details

### Getting Support

- Review the activity logs in the admin dashboard
- Check browser console for JavaScript errors
- Verify API credentials are correct
- Test connection using the built-in test feature

## 📊 Database Schema

### Log Table: `wp_dsl_logs`

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

### User Meta Fields

- `dsl_contact_id`: Dynamics contact ID
- `dsl_last_sync`: Timestamp of last successful sync

## 🎨 Customization

### Styling

Override plugin styles by adding to your theme's CSS:

```css
/* Customize form container */
.dsl-profile-container {
    background: #f5f5f5;
    border: 2px solid #333;
}

/* Customize buttons */
.dsl-button-primary {
    background-color: #your-color;
}
```

### Translations

Plugin is translation-ready. To add translations:

1. Use POEdit or similar tool
2. Create `.po` and `.mo` files
3. Place in `/languages/` directory
4. Name format: `dynamics-sync-lite-{locale}.mo`

## 🚀 Bonus Features Implemented

- ✅ **Admin Dashboard Widget**: Real-time stats and activity monitoring
- ✅ **Activity Logging**: Comprehensive logging for all API calls and user actions
- ✅ **Auto-cleanup**: Logs automatically purge after 30 days
- ✅ **Connection Testing**: Built-in API connection test
- ✅ **Responsive Design**: Mobile-friendly forms and admin interface

## 🔐 Security Best Practices

1. **Never commit credentials** to Git
2. **Use environment variables** for sensitive data in production
3. **Regular updates** to WordPress and PHP
4. **HTTPS only** - enforce SSL on your site
5. **Limit API permissions** to only what's needed
6. **Rotate secrets** periodically in Azure AD
7. **Monitor logs** for suspicious activity

## 📝 Contributing

Contributions are welcome! Please follow these guidelines:

1. Fork the repository
2. Create a feature branch: `git checkout -b feature/amazing-feature`
3. Commit your changes: `git commit -m 'Add amazing feature'`
4. Push to the branch: `git push origin feature/amazing-feature`
5. Open a Pull Request

### Code Standards

- Follow WordPress Coding Standards
- Comment complex logic
- Write meaningful commit messages
- Test thoroughly before submitting

## 📄 License

GPL v2 or later. See LICENSE file for details.

## 👤 Author

**Your Name**
- Website: https://yourwebsite.com
- GitHub: [@pranitdug](https://github.com/pranitdug)

## 🙏 Acknowledgments

- Microsoft Dynamics 365 API Documentation
- WordPress Plugin Development Best Practices
- The WordPress Community

## 📞 Support

For issues and questions:
- GitHub Issues: [Create an issue](https://github.com/pranitdug/dynamics-sync-lite/issues)
- Documentation: [Wiki](https://github.com/pranitdug/dynamics-sync-lite/wiki)

---

**Built with ❤️ for nonprofits and organizations using Microsoft Dynamics 365**