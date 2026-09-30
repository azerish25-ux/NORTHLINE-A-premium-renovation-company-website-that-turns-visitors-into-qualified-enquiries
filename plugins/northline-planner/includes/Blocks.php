<?php
declare(strict_types=1);
namespace Northline;

final class Blocks
{
    public static function register(): void
    {
        register_post_type('nl_project', ['labels' => ['name' => 'Project case studies', 'singular_name' => 'Project case study'], 'public' => true, 'show_in_rest' => true, 'has_archive' => false, 'rewrite' => ['slug' => 'project', 'with_front' => false], 'menu_icon' => 'dashicons-building', 'supports' => ['title', 'editor', 'excerpt', 'thumbnail', 'revisions', 'custom-fields']]);
        register_taxonomy('nl_project_type', 'nl_project', ['label' => 'Project types', 'public' => true, 'show_in_rest' => true, 'hierarchical' => true]);
        foreach (['nl_image', 'nl_type', 'nl_area', 'nl_location'] as $key) {
            register_post_meta('nl_project', $key, ['type' => 'string', 'single' => true, 'show_in_rest' => true, 'sanitize_callback' => 'sanitize_text_field', 'auth_callback' => static fn (): bool => current_user_can('edit_posts')]);
        }
        wp_register_script('northline-block-editor', plugins_url('assets/editor.js', NORTHLINE_PLANNER_FILE), ['wp-blocks', 'wp-element', 'wp-components', 'wp-block-editor', 'wp-server-side-render'], '2.0.0', true);
        foreach ([
            'planner' => ['mode' => ['type' => 'string', 'default' => 'planner']],
            'projects' => ['limit' => ['type' => 'number', 'default' => 6], 'filters' => ['type' => 'boolean', 'default' => true]],
            'comparison' => ['before' => ['type' => 'string', 'default' => ''], 'after' => ['type' => 'string', 'default' => ''], 'label' => ['type' => 'string', 'default' => 'Matched architectural view']],
            'materials' => ['heading' => ['type' => 'string', 'default' => 'A few things, chosen well.']],
            'plan' => ['label' => ['type' => 'string', 'default' => 'Birch House / Ground floor']],
        ] as $name => $attributes) {
            register_block_type('northline/' . $name, ['api_version' => 3, 'attributes' => $attributes, 'editor_script' => 'northline-block-editor', 'render_callback' => static fn (array $attrs): string => self::render($name, $attrs)]);
        }
        add_action('wp_enqueue_scripts', [self::class, 'assets']);
    }

    public static function assets(): void
    {
        $post = is_singular() ? get_post() : null;
        if (!$post || !has_block('northline/planner', $post)) {
            return;
        }
        wp_enqueue_script('northline-planner', plugins_url('assets/planner.js', NORTHLINE_PLANNER_FILE), [], '2.0.0', true);
        wp_localize_script('northline-planner', 'NORTHLINE', ['api' => esc_url_raw(rest_url('northline/v1/')), 'home' => home_url('/'), 'demo' => false, 'rateCard' => Estimate::RATE_CARD, 'timezone' => wp_timezone_string(), 'captchaSiteKey' => defined('NORTHLINE_TURNSTILE_SITE_KEY') ? NORTHLINE_TURNSTILE_SITE_KEY : '']);
        if (defined('NORTHLINE_TURNSTILE_SITE_KEY') && NORTHLINE_TURNSTILE_SITE_KEY !== '') {
            wp_enqueue_script('northline-turnstile', 'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit', [], null, ['strategy' => 'defer', 'in_footer' => true]);
        }
    }

    public static function render(string $name, array $attrs = []): string
    {
        return match ($name) {
            'planner' => self::planner($attrs['mode'] ?? 'planner'),
            'projects' => self::projects($attrs),
            'comparison' => self::comparison($attrs),
            'materials' => Studio::materials($attrs),
            'plan' => self::plan($attrs['label'] ?? 'Birch House / Ground floor'),
            default => '',
        };
    }

    private static function e(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public static function planner(string $mode = 'planner'): string
    {
        $mode = in_array($mode, ['planner', 'consultation', 'demo-desk'], true) ? $mode : 'planner';
        return '<div class="nl-planner" data-nl-planner="' . self::e($mode) . '"><div class="nl-loading" role="status"><span class="nl-kicker">NORTHLINE / PROJECT DESK</span><p>Preparing your project workspace…</p></div><noscript><p>The interactive planner requires JavaScript. No brief has been submitted. Enable JavaScript to use the planner.</p></noscript></div>';
    }

    public static function projects(array $attrs): string
    {
        $items = Content::projects();
        if (function_exists('get_posts')) {
            $posts = get_posts(['post_type' => 'nl_project', 'posts_per_page' => 60, 'orderby' => ['menu_order' => 'ASC', 'date' => 'ASC'], 'post_status' => 'publish']);
            if ($posts) {
                $items = array_map(static function ($post): array {
                    $fallback = Content::projects()[$post->post_name] ?? [];
                    return ['slug' => $post->post_name, 'title' => get_the_title($post), 'type' => get_post_meta($post->ID, 'nl_type', true) ?: ($fallback['type'] ?? 'whole-home'), 'location' => get_post_meta($post->ID, 'nl_location', true) ?: ($fallback['location'] ?? 'Concept project'), 'area' => get_post_meta($post->ID, 'nl_area', true) ?: ($fallback['area'] ?? ''), 'image' => get_the_post_thumbnail_url($post, 'large') ?: (get_post_meta($post->ID, 'nl_image', true) ?: Content::asset(($fallback['slug'] ?? 'birch-house') . '-after.jpg')), 'url' => get_permalink($post)];
                }, $posts);
            }
        }
        $limit = max(1, min(60, (int) ($attrs['limit'] ?? 6)));
        $html = '<section class="nl-projects" data-project-gallery aria-label="Selected project case studies">';
        if ($attrs['filters'] ?? true) {
            $html .= '<div class="nl-filters" aria-label="Filter projects">';
            foreach (['all' => 'All projects', 'kitchen' => 'Kitchens', 'extension' => 'Extensions', 'whole-home' => 'Whole homes'] as $value => $label) {
                $html .= '<button type="button" data-filter="' . $value . '" aria-pressed="' . ($value === 'all' ? 'true' : 'false') . '">' . $label . '</button>';
            }
            $html .= '</div><p class="nl-gallery-count nl-kicker" aria-live="polite"></p>';
        }
        $html .= '<div class="nl-project-grid">';
        foreach (array_slice(array_values($items), 0, $limit) as $index => $project) {
            $image = $project['image'] ?? Content::asset($project['slug'] . '-after.jpg');
            $url = $project['url'] ?? Content::url('project/' . $project['slug'] . '/');
            $html .= '<article class="nl-project-card" data-category="' . self::e($project['type']) . '"><a class="nl-project-image" href="' . self::e($url) . '"><img src="' . self::e($image) . '" width="1920" height="1280" loading="lazy" decoding="async" alt="' . self::e($project['title']) . ' — proposed architectural visualisation"><span class="nl-image-index">' . sprintf('%02d', $index + 1) . ' / NORTHLINE</span><span class="nl-image-arrow" aria-hidden="true">↗</span></a><div class="nl-project-caption"><div><p class="nl-kicker">' . self::e(Brief::TYPES[$project['type']] ?? 'Home renovation') . '</p><h3><a href="' . self::e($url) . '">' . self::e($project['title']) . '</a></h3></div><p>' . self::e($project['location']) . '<br><span>' . self::e($project['area']) . '</span></p></div></article>';
        }
        return $html . '</div></section>';
    }

    public static function comparison(array $attrs): string
    {
        static $index = 0;
        $index++;
        $label = self::e($attrs['label'] ?? 'Matched architectural view');
        return '<figure class="nl-comparison"><div class="nl-compare-stage" style="--split:50%"><img class="nl-compare-base" src="' . self::e($attrs['before'] ?? '') . '" alt="Before: ' . $label . '" width="1920" height="1280" loading="lazy"><div class="nl-compare-after"><img src="' . self::e($attrs['after'] ?? '') . '" alt="After: ' . $label . '" width="1920" height="1280" loading="lazy"></div><span class="nl-compare-label nl-before">EXISTING / CONCEPT</span><span class="nl-compare-label nl-after">PROPOSED / CONCEPT</span><span class="nl-compare-handle" aria-hidden="true">↔</span><input id="nl-compare-' . $index . '" class="nl-compare-input" type="range" min="0" max="100" value="50" aria-label="Reveal proposed view for ' . $label . '" aria-valuetext="50 percent of proposed view revealed"></div><figcaption><span>' . $label . '</span><span>Drag or use arrow keys · Same camera, two design states.</span></figcaption><p class="nl-image-disclosure">Original fictional architectural visualisations. Not photographs of completed client work.</p></figure>';
    }

    public static function plan(string $label): string
    {
        static $index = 0;
        $index++;
        $pattern = 'nl-grid-' . $index;
        return '<figure class="nl-floorplan"><svg viewBox="0 0 680 600" role="img" aria-label="Concept kitchen floor plan with a central island, rear joinery and garden daylight"><defs><pattern id="' . $pattern . '" width="24" height="24" patternUnits="userSpaceOnUse"><path d="M24 0H0V24" fill="none" stroke="currentColor" stroke-width=".4" opacity=".2"/></pattern></defs><rect width="680" height="600" fill="url(#' . $pattern . ')"/><g fill="none" stroke="currentColor"><path stroke-width="12" d="M140 480V112H286 M410 112H538V480H424 M304 480H140"/><path stroke-width="2" d="M285 106H410M285 118H410M304 480h120M304 488h120"/><path d="M538 204H548V400H538M544 204V400"/><rect x="155" y="127" width="264" height="46" stroke-width="2"/><path d="M201 127v46m44-46v46m44-46v46m44-46v46m44-46v46"/><rect x="253" y="256" width="172" height="92" rx="2" stroke-width="2"/><rect x="305" y="274" width="42" height="27" rx="4"/><path d="M325 270v12m-4-12h8"/><circle cx="279" cy="377" r="15"/><circle cx="338" cy="377" r="15"/><circle cx="397" cy="377" r="15"/><path stroke-dasharray="5 6" d="M150 60H530M100 120V472M305 520H422"/><path d="M140 52v16M538 52v16M92 112h16M92 480h16M454 312h142m-10-7 10 7-10 7M199 150H78m10-7-10 7 10 7"/><circle cx="190" cy="415" r="24"/><path d="m173 402 30 28m-29-2 30-27m-15-6v40m-20-20h40"/></g><g fill="currentColor" font-family="ui-monospace,monospace" font-size="10" letter-spacing="1.3"><text x="313" y="48">7 000</text><text x="67" y="310" transform="rotate(-90 67 310)">7 000</text><text x="268" y="239">02 / WORKING ISLAND</text><text x="457" y="335">GARDEN LIGHT →</text><text x="49" y="134">01 / JOINERY</text><text x="247" y="443">03 / ROOM TO GATHER</text><text x="140" y="556">GROUND FLOOR / CONCEPT DIAGRAM</text><text x="546" y="74">N</text><path d="M550 102V83m-4 8 4-8 4 8" fill="none" stroke="currentColor"/></g></svg><figcaption><span>' . self::e($label) . '</span><span>Concept diagram · Not a measured survey.</span></figcaption></figure>';
    }
}
