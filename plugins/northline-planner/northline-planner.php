<?php
/**
 * Plugin Name: NORTHLINE Project Planner
 * Description: Accessible project planning, durable enquiries, private references and consultation booking.
 * Version: 1.0.0
 * Requires at least: 6.8
 * Requires PHP: 8.2
 * Author: NORTHLINE Studio
 * License: GPL-2.0-or-later
 * Text Domain: northline
 */

declare(strict_types=1);

namespace Northline;

if (!defined('ABSPATH')) {
    exit;
}

define('NORTHLINE_PLANNER_FILE', __FILE__);
define('NORTHLINE_PLANNER_PATH', __DIR__);

foreach (['Domain', 'Database', 'Mail', 'Api', 'Admin', 'Blocks', 'Content'] as $file) {
    require_once __DIR__ . '/includes/' . $file . '.php';
}

register_activation_hook(__FILE__, [Database::class, 'install']);
register_deactivation_hook(__FILE__, static function (): void {
    wp_clear_scheduled_hook('northline_process_outbox');
    wp_clear_scheduled_hook('northline_retention');
});

add_filter('cron_schedules', static function (array $schedules): array {
    $schedules['northline_five_minutes'] = ['interval' => 300, 'display' => 'Every five minutes'];
    return $schedules;
});
add_action('plugins_loaded', static function (): void {
    if (get_option('northline_schema_version') !== Database::VERSION) {
        Database::install();
    }
    if (!wp_next_scheduled('northline_process_outbox')) {
        wp_schedule_event(time() + 60, 'northline_five_minutes', 'northline_process_outbox');
    }
    if (!wp_next_scheduled('northline_retention')) {
        wp_schedule_event(time() + 3600, 'daily', 'northline_retention');
    }
});
add_action('northline_process_outbox', [Mail::class, 'process']);
add_action('northline_retention', [Database::class, 'retention']);
add_action('rest_api_init', [Api::class, 'routes']);
add_action('init', [Blocks::class, 'register']);
add_action('admin_menu', [Admin::class, 'menu']);
add_action('admin_post_northline_action', [Admin::class, 'action']);
add_action('admin_post_northline_image', [Admin::class, 'image']);
add_action('admin_notices', [Admin::class, 'notice']);
add_filter('wp_privacy_personal_data_exporters', [Database::class, 'exporter']);
add_filter('wp_privacy_personal_data_erasers', [Database::class, 'eraser']);

if (defined('WP_CLI') && WP_CLI) {
    \WP_CLI::add_command('northline seed', static function (): void {
        $result = Content::seed();
        \WP_CLI::success('Demo content installed without overwriting existing content: ' . wp_json_encode($result));
    });
    \WP_CLI::add_command('northline outbox', static function (): void {
        Mail::process();
        \WP_CLI::success('Due outbox messages processed. Check the dashboard for transport results.');
    });
}
