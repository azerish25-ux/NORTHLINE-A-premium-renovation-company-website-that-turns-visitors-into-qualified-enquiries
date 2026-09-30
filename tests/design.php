<?php
/** Native block and progressive markup contracts. No WordPress database required. */
declare(strict_types=1);
require_once __DIR__ . '/../plugins/northline-planner/includes/Domain.php';
require_once __DIR__ . '/../plugins/northline-planner/includes/Content.php';
require_once __DIR__ . '/../plugins/northline-planner/includes/Blocks.php';
use Northline\Content;
use Northline\Blocks;
use Northline\Studio;
$count = 0;
function design_check(bool $value, string $message): void {
    global $count;
    if (!$value) throw new RuntimeException($message);
    $count++; echo "PASS $message\n";
}
design_check(Content::dynamic('plan') === '<!-- wp:northline/plan {} /-->', 'empty attributes serialize as a JSON object');
$home = Content::pages()['']['content'];
design_check(str_contains($home, 'nl-hero-v2'), 'canonical homepage uses the editorial layout');
design_check(str_contains($home, 'wp:northline/materials {}'), 'material library remains an editable native block');
design_check(str_contains($home, 'fetchpriority="high"'), 'hero has explicit loading priority');
$a = Studio::materials(); $b = Studio::materials();
preg_match_all('/\bid="([^"]+)"/', $a . $b, $ids);
design_check(count($ids[1]) === count(array_unique($ids[1])), 'multiple material blocks have unique IDs');
design_check(substr_count($a, 'data-material-panel=') === 3, 'all material studies exist in server output');
design_check(str_contains($a, 'aria-live="polite"'), 'material changes have a live announcement');
design_check(!str_contains(Studio::materials(['heading' => '<script>alert(1)</script>']), '<script>'), 'editor-provided heading is escaped');
design_check(str_contains(Blocks::render('materials'), 'nl-material-atelier'), 'dynamic renderer resolves the material block');
design_check(count(Content::pages()) === 10 && count(Content::projects()) === 6, 'all existing pages and six studies remain available');
echo "$count design markup assertions passed.\n";
