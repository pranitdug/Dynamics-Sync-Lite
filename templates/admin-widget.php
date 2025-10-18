<?php
/**
 * Admin Dashboard Widget Template
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="dsl-widget-stats">
    <div class="dsl-stat-box">
        <div class="dsl-stat-number"><?php echo esc_html($stats['synced_users']); ?></div>
        <div class="dsl-stat-label"><?php _e('Synced Users', 'dynamics-sync-lite'); ?></div>
        <div class="dsl-stat-progress">
            <div class="dsl-progress-bar" style="width: <?php echo esc_attr($stats['sync_percentage']); ?>%;"></div>
        </div>
        <div class="dsl-stat-info">
            <?php printf(__('%d of %d users (%d%%)', 'dynamics-sync-lite'), 
                $stats['synced_users'], 
                $stats['total_users'], 
                $stats['sync_percentage']
            ); ?>
        </div>
    </div>
    
    <div class="dsl-stat-grid">
        <div class="dsl-stat-item dsl-stat-success">
            <span class="dsl-stat-icon">✓</span>
            <div class="dsl-stat-content">
                <div class="dsl-stat-count"><?php echo esc_html($stats['success_count']); ?></div>
                <div class="dsl-stat-text"><?php _e('Successful', 'dynamics-sync-lite'); ?></div>
            </div>
        </div>
        
        <div class="dsl-stat-item dsl-stat-error">
            <span class="dsl-stat-icon">✕</span>
            <div class="dsl-stat-content">
                <div class="dsl-stat-count"><?php echo esc_html($stats['error_count']); ?></div>
                <div class="dsl-stat-text"><?php _e('Errors', 'dynamics-sync-lite'); ?></div>
            </div>
        </div>
        
        <div class="dsl-stat-item dsl-stat-info">
            <span class="dsl-stat-icon">ℹ</span>
            <div class="dsl-stat-content">
                <div class="dsl-stat-count"><?php echo esc_html($stats['info_count']); ?></div>
                <div class="dsl-stat-text"><?php _e('Info Logs', 'dynamics-sync-lite'); ?></div>
            </div>
        </div>
    </div>
    
    <?php if ($stats['last_sync']): ?>
    <div class="dsl-last-sync">
        <?php _e('Last sync:', 'dynamics-sync-lite'); ?> 
        <strong><?php echo esc_html(human_time_diff(strtotime($stats['last_sync']), current_time('timestamp'))); ?> <?php _e('ago', 'dynamics-sync-lite'); ?></strong>
    </div>
    <?php endif; ?>
</div>

<div class="dsl-widget-logs">
    <h3><?php _e('Recent Activity', 'dynamics-sync-lite'); ?></h3>
    
    <?php if (!empty($recent_logs)): ?>
    <ul class="dsl-log-list">
        <?php foreach ($recent_logs as $log): ?>
        <li class="dsl-log-item dsl-log-<?php echo esc_attr($log['log_level']); ?>">
            <span class="dsl-log-time" title="<?php echo esc_attr($log['log_time']); ?>">
                <?php echo esc_html(human_time_diff(strtotime($log['log_time']), current_time('timestamp'))); ?> <?php _e('ago', 'dynamics-sync-lite'); ?>
            </span>
            <span class="dsl-log-level"><?php echo esc_html($log['log_level']); ?></span>
            <span class="dsl-log-message"><?php echo esc_html($log['message']); ?></span>
        </li>
        <?php endforeach; ?>
    </ul>
    <?php else: ?>
    <p class="dsl-no-logs"><?php _e('No activity logged yet.', 'dynamics-sync-lite'); ?></p>
    <?php endif; ?>
    
    <div class="dsl-widget-footer">
        <a href="<?php echo admin_url('options-general.php?page=dynamics-sync-lite'); ?>" class="button button-secondary">
            <?php _e('View Settings', 'dynamics-sync-lite'); ?>
        </a>
    </div>
</div>