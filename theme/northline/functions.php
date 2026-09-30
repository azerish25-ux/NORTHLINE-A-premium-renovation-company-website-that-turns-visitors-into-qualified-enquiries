<?php
/** NORTHLINE block-theme setup. All business workflow code lives in the plugin. */
declare(strict_types=1);
if (!defined('ABSPATH')) exit;
add_action('after_setup_theme', static function (): void {
    add_theme_support('wp-block-styles');
    add_theme_support('editor-styles');
    add_theme_support('post-thumbnails');
    add_editor_style(['assets/site.css', 'assets/tokens.css', 'assets/studio.css']);
});
add_action('wp_enqueue_scripts', static function (): void {
    wp_enqueue_style('northline-site', get_stylesheet_directory_uri() . '/assets/site.css', [], '2.0.0');
    wp_enqueue_style('northline-tokens', get_stylesheet_directory_uri() . '/assets/tokens.css', ['northline-site'], '2.0.0');
    wp_enqueue_style('northline-studio', get_stylesheet_directory_uri() . '/assets/studio.css', ['northline-tokens'], '2.0.0');
    wp_enqueue_script('northline-studio', get_stylesheet_directory_uri() . '/assets/studio.js', ['northline-site'], '2.0.0', ['strategy' => 'defer', 'in_footer' => true]);
    wp_enqueue_script('northline-site', get_stylesheet_directory_uri() . '/assets/site.js', [], '2.0.0', ['strategy' => 'defer', 'in_footer' => true]);
});
add_action('init', static function (): void {
    register_block_pattern_category('northline', ['label' => 'NORTHLINE editorial layouts']);
    if (class_exists('Northline\\Studio')) register_block_pattern('northline/material-space-home', [
        'title' => 'Material & Space / complete homepage',
        'description' => 'Editable editorial homepage with original visualisations and the material library.',
        'categories' => ['northline'],
        'content' => \Northline\Studio::home(),
    ]);
});
add_action('wp_head', static function (): void {
    echo '<meta name="theme-color" content="#f6f3ec"><meta name="referrer" content="strict-origin-when-cross-origin">';
    if (is_front_page()) echo '<link rel="preload" as="image" href="' . esc_url(get_stylesheet_directory_uri() . '/assets/images/birch-house-after.jpg') . '">';
    if (is_page('consultation')) echo '<meta name="robots" content="noindex,nofollow"><meta name="referrer" content="no-referrer">';
    if (is_singular() && !is_page('consultation')) {
        $description = is_front_page() ? 'Thoughtful renovations, one considered process. Explore NORTHLINE’s fictional architectural design studies and plan a project with transparent illustrative allowances.' : wp_strip_all_tags(get_the_excerpt());
        if ($description) echo '<meta name="description" content="' . esc_attr(wp_trim_words($description, 28, '')) . '">';
    }
}, 2);
add_action('admin_notices', static function (): void {
    if (current_user_can('activate_plugins') && !class_exists('Northline\\Blocks')) echo '<div class="notice notice-warning"><p>NORTHLINE needs the included <strong>NORTHLINE Project Planner</strong> plugin for its editable case studies, gallery and enquiry workflow.</p></div>';
});
