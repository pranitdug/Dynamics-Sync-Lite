# Dynamics Sync Lite

A WordPress plugin that enables seamless synchronization between WordPress user data and Microsoft Dynamics 365 CRM. Built for nonprofits and organizations that need to empower their users to manage their own contact information stored in Dynamics 365.

## 🎯 Overview

**Dynamics Sync Lite** allows logged-in WordPress users to view and update their contact information (name, email, phone, address) stored in Microsoft Dynamics 365. All changes are synchronized in real-time via secure API connections.

### Key Features

✅ **OAuth 2.0 Authentication** - Secure connection to Dynamics 365 using Azure AD  
✅ **Real-Time Sync** - Fetch and update contact data via REST API  
✅ **User-Friendly Interface** - Simple shortcode-based form for frontend display  
✅ **Security First** - Nonce verification, HTTPS enforcement, data sanitization  
✅ **Activity Logging** - Track all API calls and user actions  
✅ **Webhook Support** - Receive updates from Dynamics 365 (bonus feature)  
✅ **Admin Dashboard Widget** - View recent activity at a glance (bonus feature)  
✅ **WordPress Best Practices** - Follows WordPress coding standards and security guidelines

---

## 📋 Requirements

- **WordPress**: 6.0 or higher
- **PHP**: 7.4 or higher
- **SSL Certificate**: Required for secure API communication
- **Microsoft Dynamics 365**: Active instance with API access
- **Azure AD App**: Registered application with appropriate permissions

---

## 🚀 Installation

### 1. Download and Install

```bash
# Clone the repository
git clone https://github.com/pranitdug/Dynamics-Sync-Lite.git

# Or download as ZIP and extract to wp-content/plugins/
```

### 2. Activate Plugin

1. Navigate to **WordPress Admin → Plugins**
2. Find "Dynamics Sync Lite"
3. Click **Activate**

### 3. Configure Azure AD Application

#### Step 3.1: Register Application in Azure Portal

1. Go to [Azure Portal](https://portal.azure.com)
2. Navigate to **Azure Active Directory → App registrations**
3. Click **New registration**
4. Enter application name: `WordPress Dynamics Sync`
5. Select **Accounts in this organizational directory only**
6. Click **Register**

#### Step 3.2: Create Client Secret

1. In your app registration, go to **Certificates & secrets**
2. Click **New client secret**
3. Enter description: `WordPress Plugin Secret`
4. Set expiration (e.g., 24 months)
5. Click **Add**
6. **Copy the secret value immediately** (you won't see it again)

#### Step 3.3: Configure API Permissions

1. Go to **API permissions**
2. Click **Add a permission**
3. Select **Dynamics CRM**
4. Choose **Application permissions**
5. Add `user_impersonation` permission
6. Click **Grant admin consent**

#### Step 3.4: Note Required Values

You'll need these values for plugin configuration:
- **Application (client) ID**
- **Directory (tenant) ID**
- **Client secret** (from Step 3.2)
- **Dynamics 365 URL** (e.g., `https://yourorg.crm.dynamics.com`)

### 4. Configure Plugin Settings

1. Navigate to **WordPress Admin → Dynamics Sync → Settings**
2. Enter the values from Azure AD:
   - **Client ID**: Your application client ID
   - **Client Secret**: Your client secret
   - **Tenant ID**: Your directory tenant ID
   - **Dynamics 365 URL**: Your Dynamics instance URL
3. Enable **Logging** (recommended)
4. Click **Save Settings**
5. Click **Test Connection** to verify configuration

---

## 📝 Usage

### Basic Shortcode

Display the contact form on any page or post:

```php
[dynamics_contact_form]
```

### Shortcode Parameters

```php
// With custom title
[dynamics_contact_form title="Update Your Information"]

// Without title
[dynamics_contact_form show_title="no"]
```

### Example Implementation

1. Create a new page: **My Profile**
2. Add the shortcode: `[dynamics_contact_form]`
3. Publish the page
4. Users must be logged in to see and use the form

---

## 🔧 Technical Architecture

### File Structure

```
dynamics-sync-lite/
├── dynamics-sync-lite.php          # Main plugin file
├── includes/
│   ├── class-dsl-api.php           # Dynamics 365 API handler
│   ├── class-dsl-settings.php      # Admin settings page
│   ├── class-dsl-shortcodes.php    # Shortcode renderer
│   ├── class-dsl-ajax.php          # AJAX request handler
│   ├── class-dsl-logger.php        # Activity logger
│   ├── class-dsl-webhook.php       # Webhook listener (bonus)
│   └── class-dsl-dashboard.php     # Dashboard widget (bonus)
├── assets/
│   ├── css/
│   │   ├── frontend.css            # Frontend styles
│   │   └── admin.css               # Admin styles
│   └── js/
│       ├── frontend.js             # Frontend JavaScript
│       └── admin.js                # Admin JavaScript
└── README.md                        # This file
```

### Data Flow

```
User Submits Form
    ↓
WordPress AJAX Handler
    ↓
Nonce Verification
    ↓
Data Sanitization
    ↓
OAuth Token Request (Azure AD)
    ↓
API Call (Dynamics 365)
    ↓
Response Processing
    ↓
Activity Logging
    ↓
User Feedback
```

### Security Measures

1. **Nonce Verification**: All AJAX requests verified with WordPress nonces
2. **Capability Checks**: Admin functions restricted to appropriate user roles
3. **Data Sanitization**: All input sanitized using WordPress functions
4. **HTTPS Enforcement**: SSL verification enabled for all API calls
5. **Token Caching**: Access tokens cached securely with automatic expiry
6. **Webhook Secret**: Shared secret validates incoming webhook requests
7. **SQL Injection Prevention**: Prepared statements for all database queries

### API Integration Details

**Authentication**: OAuth 2.0 Client Credentials Flow

**Endpoint**: Dynamics 365 Web API v9.2

**Methods Used**:
- `GET /contacts` - Fetch contact by email
- `PATCH /contacts({id})` - Update contact information

**Fields Synced**:
- `firstname` - First name
- `lastname` - Last name
- `emailaddress1` - Email address (read-only)
- `telephone1` - Phone number
- `address1_line1` - Street address
- `address1_city` - City
- `address1_stateorprovince` - State/Province
- `address1_postalcode` - Postal code
- `address1_country` - Country

---

## 🎁 Bonus Features

### 1. Dashboard Widget

Administrators can view recent sync activity directly from the WordPress dashboard:

- **Statistics**: Updates today, this week, and errors
- **Recent Activity**: Last 10 successful updates
- **Quick Access**: Link to full logs

### 2. Webhook Listener

Receive real-time updates from Dynamics 365:

**Endpoint**: `https://yoursite.com/wp-json/dynamics-sync-lite/v1/webhook`

**Configuration in Dynamics 365**:
1. Create a webhook in Dynamics Power Automate
2. Set trigger: When a contact is updated
3. Set action: HTTP POST to webhook URL
4. Add header: `X-Webhook-Secret: [your-secret-from-plugin]`
5. Body: Contact data (JSON)

**Security**: Webhook requests must include the secret from plugin settings

### 3. Activity Logging

All interactions are logged for audit and debugging:

- User actions (view, update)
- API calls (success, failure)
- Error messages
- Timestamps

**View Logs**: WordPress Admin → Dynamics Sync → Logs

---

## 🛠️ Design Decisions

### Why OAuth 2.0?

Client credentials flow provides server-to-server authentication without requiring individual user credentials. This is ideal for automated synchronization scenarios.

### Why Transient Caching?

Access tokens are cached using WordPress transients to minimize API calls and improve performance. Tokens are automatically refreshed when expired.

### Why Separate Classes?

Single Responsibility Principle - each class handles one specific aspect (API, settings, logging, etc.), making the code maintainable and testable.

### Why AJAX?

Provides a smooth user experience without page reloads. Form submission and data loading happen asynchronously with visual feedback.

---

## ⚠️ Known Limitations

1. **Email Cannot Be Changed**: The plugin uses email as the primary identifier. Changing email requires admin intervention in Dynamics 365.

2. **Single Contact Per User**: Each WordPress user maps to one Dynamics contact via email address.

3. **Custom Fields Not Supported**: Only standard Dynamics 365 contact fields are supported. Custom fields require code modification.

4. **No Bulk Operations**: Updates are processed one at a time. Bulk import/export not available.

5. **Rate Limiting**: Dynamics 365 API has rate limits. Heavy usage may require throttling implementation.

6. **Token Refresh**: If Azure AD app credentials change, cached tokens must be cleared manually or wait for expiry.

---

## 🐛 Troubleshooting

### "Authentication failed"

**Cause**: Invalid credentials or permissions

**Solution**:
1. Verify Client ID, Secret, and Tenant ID in settings
2. Ensure Azure AD app has `user_impersonation` permission
3. Grant admin consent for permissions
4. Check that client secret hasn't expired

### "Contact not found"

**Cause**: No Dynamics contact exists with user's email

**Solution**:
1. Verify email address matches exactly in Dynamics 365
2. Create contact in Dynamics 365 with matching email
3. Check email field mapping in Dynamics

### "Connection timeout"

**Cause**: Network issues or firewall blocking

**Solution**:
1. Verify Dynamics 365 URL is correct
2. Check server firewall allows outbound HTTPS to *.dynamics.com
3. Ensure WordPress site has SSL certificate
4. Test connection from server command line

### Webhook Not Receiving Updates

**Cause**: Incorrect configuration or secret mismatch

**Solution**:
1. Verify webhook URL is accessible publicly
2. Check webhook secret matches exactly
3. Ensure Power Automate flow is enabled
4. Review webhook logs in Dynamics

---

## 📊 Performance Considerations

- **Token Caching**: Reduces authentication overhead
- **Selective Field Loading**: Only necessary fields are retrieved
- **Lazy Loading**: Contact data loaded only when form is displayed
- **Database Indexes**: Log table indexed for fast queries
- **Log Rotation**: Consider implementing automatic old log cleanup

---

## 🔐 Privacy & Compliance

- User data is transmitted over HTTPS only
- No data stored locally except activity logs
- Logs can be disabled via settings
- Webhook requires shared secret authentication
- Plugin follows WordPress security best practices

---

## 🤝 Contributing

Contributions are welcome! Please follow these guidelines:

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

### Code Standards

- Follow WordPress Coding Standards
- Add PHPDoc comments for all functions
- Sanitize and validate all input
- Escape all output
- Write meaningful commit messages

---

## 📄 License

This plugin is licensed under GPL v2 or later.

---