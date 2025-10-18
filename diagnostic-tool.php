<?php
/**
 * Diagnostic Tool for Dynamics API Issues
 * Add this code to your WordPress theme's functions.php temporarily
 * Or create a new page template with this code
 */

// Only show to admins
if (!current_user_can('manage_options')) {
    wp_die('Admin only');
}

?>
<style>
    .diagnostic-container {
        background: #f5f5f5;
        padding: 20px;
        margin: 20px 0;
        border-radius: 5px;
        font-family: monospace;
    }
    .diagnostic-section {
        background: white;
        padding: 15px;
        margin: 10px 0;
        border-left: 4px solid #0073aa;
        border-radius: 3px;
    }
    .diagnostic-section h3 {
        margin-top: 0;
        color: #0073aa;
    }
    .success { color: #00a32a; font-weight: bold; }
    .error { color: #d63638; font-weight: bold; }
    .warning { color: #dba617; font-weight: bold; }
    .info { color: #2271b1; }
    pre {
        background: #f0f0f0;
        padding: 10px;
        overflow-x: auto;
        border-radius: 3px;
    }
    .config-table {
        width: 100%;
        border-collapse: collapse;
    }
    .config-table td {
        padding: 10px;
        border-bottom: 1px solid #ddd;
    }
    .config-table td:first-child {
        font-weight: bold;
        width: 200px;
        background: #f9f9f9;
    }
</style>

<div class="diagnostic-container">
    <h1>🔧 Dynamics 365 API Diagnostic Tool</h1>
    
    <?php
    // Get all settings
    $client_id = get_option('dsl_client_id', '');
    $client_secret = get_option('dsl_client_secret', '');
    $tenant_id = get_option('dsl_tenant_id', '');
    $resource_url = get_option('dsl_resource_url', '');
    $api_version = get_option('dsl_api_version', '9.2');
    $demo_mode = get_option('dsl_demo_mode', '0');
    
    ?>
    
    <!-- Configuration Check -->
    <div class="diagnostic-section">
        <h3>📋 Configuration Status</h3>
        <table class="config-table">
            <tr>
                <td>Demo Mode</td>
                <td><?php echo $demo_mode === '1' ? '<span class="warning">⚠️ ENABLED</span>' : '<span class="success">✓ Disabled</span>'; ?></td>
            </tr>
            <tr>
                <td>Client ID</td>
                <td><?php echo !empty($client_id) ? '<span class="success">✓ Set (' . substr($client_id, 0, 8) . '...)</span>' : '<span class="error">✗ NOT SET</span>'; ?></td>
            </tr>
            <tr>
                <td>Client Secret</td>
                <td><?php echo !empty($client_secret) ? '<span class="success">✓ Set (' . substr($client_secret, 0, 5) . '...)</span>' : '<span class="error">✗ NOT SET</span>'; ?></td>
            </tr>
            <tr>
                <td>Tenant ID</td>
                <td><?php echo !empty($tenant_id) ? '<span class="success">✓ Set (' . substr($tenant_id, 0, 8) . '...)</span>' : '<span class="error">✗ NOT SET</span>'; ?></td>
            </tr>
            <tr>
                <td>Resource URL</td>
                <td><?php 
                    if (empty($resource_url)) {
                        echo '<span class="error">✗ NOT SET</span>';
                    } elseif (strpos($resource_url, 'https://') === 0 && substr($resource_url, -1) === '/') {
                        echo '<span class="success">✓ Valid</span><br/><code>' . esc_html($resource_url) . '</code>';
                    } else {
                        echo '<span class="error">✗ Invalid Format</span><br/><code>' . esc_html($resource_url) . '</code>';
                    }
                ?></td>
            </tr>
            <tr>
                <td>API Version</td>
                <td><code><?php echo esc_html($api_version); ?></code></td>
            </tr>
        </table>
    </div>
    
    <!-- PHP & WordPress Checks -->
    <div class="diagnostic-section">
        <h3>🖥️ Server & WordPress Status</h3>
        <table class="config-table">
            <tr>
                <td>PHP Version</td>
                <td><code><?php echo phpversion(); ?></code> <?php echo version_compare(phpversion(), '7.4', '>=') ? '<span class="success">✓</span>' : '<span class="error">✗ (Need 7.4+)</span>'; ?></td>
            </tr>
            <tr>
                <td>WordPress Version</td>
                <td><code><?php echo get_bloginfo('version'); ?></code></td>
            </tr>
            <tr>
                <td>cURL Enabled</td>
                <td><?php echo function_exists('curl_version') ? '<span class="success">✓ Yes</span>' : '<span class="error">✗ No</span>'; ?></td>
            </tr>
            <tr>
                <td>OpenSSL</td>
                <td><?php echo extension_loaded('openssl') ? '<span class="success">✓ Enabled</span>' : '<span class="error">✗ Disabled</span>'; ?></td>
            </tr>
            <tr>
                <td>HTTPS on Site</td>
                <td><?php echo is_ssl() ? '<span class="success">✓ Yes</span>' : '<span class="warning">⚠️ No (may cause issues)</span>'; ?></td>
            </tr>
        </table>
    </div>
    
    <!-- Token Test -->
    <div class="diagnostic-section">
        <h3>🔐 Token Request Test</h3>
        <?php
        if (empty($client_id) || empty($client_secret) || empty($tenant_id)) {
            echo '<span class="error">Cannot test - missing credentials</span>';
        } else {
            $token_url = "https://login.microsoftonline.com/{$tenant_id}/oauth2/v2.0/token";
            $scope = rtrim($resource_url, '/') . '/.default';
            
            echo '<p><strong>Token URL:</strong></p>';
            echo '<pre>' . esc_html($token_url) . '</pre>';
            
            echo '<p><strong>Scope:</strong></p>';
            echo '<pre>' . esc_html($scope) . '</pre>';
            
            echo '<p><strong>Attempting token request...</strong></p>';
            
            $response = wp_remote_post($token_url, array(
                'body' => array(
                    'grant_type' => 'client_credentials',
                    'client_id' => $client_id,
                    'client_secret' => $client_secret,
                    'scope' => $scope
                ),
                'timeout' => 30,
                'sslverify' => true
            ));
            
            if (is_wp_error($response)) {
                echo '<span class="error">✗ Request Failed</span><br/>';
                echo '<strong>Error:</strong> ' . esc_html($response->get_error_message()) . '<br/>';
                echo '<strong>Code:</strong> ' . esc_html($response->get_error_code());
            } else {
                $status = wp_remote_retrieve_response_code($response);
                $body = json_decode(wp_remote_retrieve_body($response), true);
                
                echo '<strong>Status Code:</strong> ' . esc_html($status) . '<br/>';
                
                if ($status === 200) {
                    echo '<span class="success">✓ Token obtained successfully!</span><br/>';
                    echo '<strong>Token (first 50 chars):</strong> ' . esc_html(substr($body['access_token'], 0, 50)) . '...<br/>';
                    echo '<strong>Expires In:</strong> ' . esc_html($body['expires_in']) . ' seconds';
                } else {
                    echo '<span class="error">✗ Token request failed</span><br/>';
                    echo '<strong>Response:</strong><br/>';
                    echo '<pre>' . wp_json_encode($body, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . '</pre>';
                    
                    // Helpful hints
                    if (isset($body['error'])) {
                        echo '<div style="background: #fff3cd; padding: 10px; margin-top: 10px; border-radius: 3px;">';
                        echo '<strong>Error Code:</strong> ' . esc_html($body['error']) . '<br/>';
                        if (isset($body['error_description'])) {
                            echo '<strong>Description:</strong> ' . esc_html($body['error_description']) . '<br/>';
                        }
                        
                        // Provide hints based on error
                        if (strpos($body['error_description'] ?? '', 'invalid_client') !== false) {
                            echo '<strong>💡 Hint:</strong> Client ID or Secret is incorrect, or credentials were never configured for this app.';
                        } elseif (strpos($body['error_description'] ?? '', 'invalid_scope') !== false) {
                            echo '<strong>💡 Hint:</strong> Scope format is wrong or Dynamics API permissions not granted.';
                        } elseif (strpos($body['error_description'] ?? '', 'tenant identifier') !== false) {
                            echo '<strong>💡 Hint:</strong> Tenant ID is incorrect or in wrong format.';
                        }
                        echo '</div>';
                    }
                }
            }
        }
        ?>
    </div>
    
    <!-- Recent Logs -->
    <div class="diagnostic-section">
        <h3>📜 Recent Logs (Last 20 entries)</h3>
        <?php
        global $wpdb;
        $table_name = $wpdb->prefix . 'dsl_logs';
        
        // Check if table exists
        $table_check = $wpdb->get_var("SHOW TABLES LIKE '{$table_name}'");
        
        if ($table_check) {
            $logs = $wpdb->get_results(
                "SELECT * FROM {$table_name} ORDER BY log_time DESC LIMIT 20",
                ARRAY_A
            );
            
            if (!empty($logs)) {
                echo '<table class="config-table" style="width: 100%;">';
                echo '<tr style="background: #f0f0f0;"><td><strong>Time</strong></td><td><strong>Level</strong></td><td><strong>Message</strong></td></tr>';
                foreach ($logs as $log) {
                    $level_class = 'log-' . $log['log_level'];
                    echo '<tr>';
                    echo '<td><code style="font-size: 12px;">' . esc_html(substr($log['log_time'], 0, 19)) . '</code></td>';
                    echo '<td><span class="' . $level_class . '">' . esc_html(strtoupper($log['log_level'])) . '</span></td>';
                    echo '<td>' . esc_html($log['message']);
                    if (!empty($log['context'])) {
                        echo '<br/><small style="color: #666;">' . esc_html(substr($log['context'], 0, 100)) . '...</small>';
                    }
                    echo '</td>';
                    echo '</tr>';
                }
                echo '</table>';
            } else {
                echo '<p><span class="info">No logs found. Try testing the connection first.</span></p>';
            }
        } else {
            echo '<p><span class="error">Log table does not exist. Plugin may not be activated properly.</span></p>';
        }
        ?>
    </div>
    
    <!-- Recommendations -->
    <div class="diagnostic-section" style="background: #fff3cd; border-left-color: #ffc107;">
        <h3>✅ Next Steps</h3>
        <ol>
            <li>Make sure <strong>ALL configuration fields are filled</strong> (see Configuration Status above)</li>
            <li>Verify <strong>Tenant ID format</strong> - should be a GUID like: <code>xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx</code></li>
            <li>Check <strong>Client Secret expiration</strong> in Azure AD - create new one if expired</li>
            <li>Verify <strong>API permissions granted</strong> in Azure AD with admin consent</li>
            <li><strong>Create Application User</strong> in Dynamics 365 with your Client ID</li>
            <li>If still failing, check the error details above to identify the exact issue</li>
        </ol>
    </div>
    
    <style>
        .log-error { color: #d63638; }
        .log-success { color: #00a32a; }
        .log-info { color: #2271b1; }
    </style>
</div>

<?php
// End of diagnostic tool
?>