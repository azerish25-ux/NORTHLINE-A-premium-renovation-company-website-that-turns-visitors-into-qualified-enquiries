<?php
/** Material & Space: editorial layouts; planning and enquiry logic stay in their existing classes. */
declare(strict_types=1);
namespace Northline;

final class Studio
{
    public static function home(): string
    {
        $title = Content::group('nl-hero-title', Content::p('DESIGN & BUILD / PRINCE EDWARD ISLAND', 'nl-kicker') . Content::h('A better way<br>to <em>come home.</em>', 1));
        $intro = Content::group('nl-hero-bottom', Content::p('Thoughtful renovations. One considered process. Spaces made for the way you live.', 'nl-lead') . Content::button('Plan your renovation <span aria-hidden="true">↗</span>', 'plan-your-renovation/') . Content::p('<a href="' . Content::e(Content::url('projects/')) . '">Explore our work <span aria-hidden="true">↘</span></a>', 'nl-text-link'));
        $caption = Content::group('nl-hero-caption', Content::group('', Content::p('01 / SELECTED DESIGN STUDY', 'nl-kicker') . Content::h('<a href="' . Content::e(Content::url('project/birch-house/')) . '">Birch House <span aria-hidden="true">↗</span></a>') . Content::p('Oak. Limestone. Room to breathe.')) . Content::p('FICTIONAL DESIGN STUDY<br>ORIGINAL 3D VISUALISATION', 'nl-small'));
        $home = Content::group('nl-hero nl-hero-v2 nl-shell', Content::group('nl-hero-heading', $title . $intro) . Content::group('nl-hero-media', Content::image('birch-house-after.jpg', 'Original 3D kitchen study with an oak island, curved timber stools, limestone counter and garden glazing', 'nl-hero-image') . $caption));
        $home .= Content::group('nl-introduction nl-shell', Content::p('BUILT AROUND YOU', 'nl-kicker') . Content::h('Not just a different house.<br><em>A different feeling.</em>') . Content::group('', Content::p('The morning light at the kitchen counter. A room that finally works for everyone. The quiet satisfaction of a detail done properly.', 'nl-lead') . Content::p('We bring design and construction into one conversation, making more of the home you already have.')));
        $services = '';
        foreach ([['01','Kitchens','kitchens/','The centre of daily life, carefully reimagined.'], ['02','Extensions','extensions/','More space. A better connection to the whole house.'], ['03','Whole homes','whole-home/','A coherent vision, from the first room to the last.']] as [$number,$label,$path,$copy]) {
            $services .= Content::group('nl-service-row', Content::p($number,'nl-kicker') . Content::h('<a href="' . Content::e(Content::url($path)) . '">' . $label . ' <span aria-hidden="true">↗</span></a>', 3) . Content::p($copy));
        }
        $home .= Content::group('nl-services-band', Content::group('nl-shell', Content::group('nl-section-heading', Content::group('', Content::p('01 / WHAT WE DO','nl-kicker') . Content::h('Make more of<br>the home <em>you have.</em>')) . Content::p('A room, an addition, or a complete rethink. The same care at every scale.','nl-lead')) . Content::group('nl-services-grid',$services)));
        $home .= Content::group('nl-selected nl-shell', Content::group('nl-section-heading', Content::group('', Content::p('02 / SELECTED DESIGN STUDIES','nl-kicker') . Content::h('Spaces with<br><em>a point of view.</em>')) . Content::button('All six projects <span aria-hidden="true">↗</span>','projects/','is-style-outline')) . Content::dynamic('projects',['limit'=>2,'filters'=>false]) . Content::p('Six fictional homes. Original, matched-camera visualisations. A closer look at the decisions behind each space.','nl-small'));
        $home .= Content::dynamic('materials');
        $steps = '';
        foreach (['Tell us about your home and the way you live.','Explore the scope, timing and budget assumptions.','Save your brief, then choose a time to talk.'] as $i=>$text) $steps .= Content::p('<span>' . sprintf('%02d',$i+1) . '</span>' . $text);
        $home .= Content::group('nl-planning-feature nl-shell', Content::dynamic('plan') . Content::group('nl-planning-copy', Content::p('04 / BEFORE WE PICK UP A PENCIL','nl-kicker') . Content::h('Your ideas.<br><em>A clearer plan.</em>') . Content::p('You do not need to have every answer. Our project planner turns the first questions into a useful brief.','nl-lead') . Content::group('nl-planning-steps',$steps) . Content::button('Plan your renovation <span aria-hidden="true">↗</span>','plan-your-renovation/') . Content::p('Around 5 minutes · Illustrative allowances, not a quotation.','nl-small')));
        return $home . Content::cta();
    }

    public static function materials(array $attrs = []): string
    {
        static $instance = 0;
        $prefix = 'nl-material-' . ++$instance . '-';
        $studies = [
            'oak'=>['01','Quarter-sawn oak','Grain, rhythm, warmth.','Fine reeding gives the island a quieter rhythm. Rounded timber rails and a softened seat turn the stools into furniture, rather than symbols of furniture.','Quarter-sawn oak / matte hardwax','Reeded joinery, eased edges, turned legs','Close architectural study of the reeded oak island and bent-timber stool'],
            'limestone'=>['02','Honed limestone','A softer kind of solid.','A generous slab, a softened arris and a recessed basin. The counter is modelled as a constructed object, with an opening, thickness and junctions that hold up at close range.','Honed limestone / brushed brass','Undermount basin, solid slab, satin mixer','Close architectural study of a limestone counter with a recessed sink and curved brass mixer'],
            'limewash'=>['03','Mineral limewash','Light does the decorating.','Mineral colour catches daylight without competing with the grain of the oak. Subtle relief, woven linen and brushed metal give a restrained palette depth.','Mineral plaster / woven linen','Layered pigment, fabric folds, reflected light','Close architectural study of limewashed surfaces, woven linen and kitchen joinery'],
        ];
        $controls = '<div class="nl-material-controls" role="group" aria-label="Choose a material study" hidden>';
        $panels = '';
        foreach ($studies as $key=>[$number,$name,$title,$copy,$finish,$detail,$alt]) {
            $id = $prefix . $key;
            $controls .= '<button type="button" data-material-target="' . $key . '" aria-controls="' . $id . '" aria-pressed="false"><span class="nl-material-chip nl-chip-' . $key . '" aria-hidden="true"></span>' . Content::e($name) . '<span class="nl-material-check" aria-hidden="true">✓</span></button>';
            $panels .= '<figure id="' . $id . '" class="nl-material-panel" data-material-panel="' . $key . '"><img src="' . Content::e(Content::asset('material-' . $key . '.jpg')) . '" alt="' . Content::e($alt) . '" width="1600" height="1067" loading="lazy" decoding="async"><figcaption><div><p class="nl-kicker">' . $number . ' / ' . strtoupper($name) . '</p><h3>' . Content::e($title) . '</h3><p>' . Content::e($copy) . '</p></div><dl><div><dt>FINISH PALETTE</dt><dd>' . Content::e($finish) . '</dd></div><div><dt>IN THE DETAIL</dt><dd>' . Content::e($detail) . '</dd></div></dl></figcaption></figure>';
        }
        $heading = Content::e((string)($attrs['heading'] ?? 'A few things, chosen well.'));
        return '<section class="nl-material-atelier" aria-labelledby="' . $prefix . 'heading" data-material-study><div class="nl-shell"><div class="nl-section-heading"><div><p class="nl-kicker">03 / THE MATERIAL LIBRARY</p><h2 id="' . $prefix . 'heading">' . $heading . '</h2></div><p class="nl-lead">Natural grain. A softened edge. The way a surface holds the light.</p></div>' . $controls . '</div>' . $panels . '<p class="nl-material-status nl-small" aria-live="polite"></p><p class="nl-small">Original Blender material studies. Concept finishes, not a product specification.</p></div></section>';
    }
}
