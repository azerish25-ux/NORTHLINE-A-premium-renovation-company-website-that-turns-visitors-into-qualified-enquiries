<?php
/** One-time native-block content installation; existing edited pages are preserved. */
declare(strict_types=1);
namespace Northline;

final class Content
{
    public static function e(string $value): string { return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
    public static function url(string $path = ''): string { return function_exists('home_url') ? home_url('/' . ltrim($path, '/')) : ($GLOBALS['nl_preview_base'] ?? '/') . ltrim($path, '/'); }
    public static function asset(string $file): string { return function_exists('get_stylesheet_directory_uri') ? get_stylesheet_directory_uri() . '/assets/images/' . $file : ($GLOBALS['nl_preview_assets'] ?? '/assets/images/') . $file; }

    public static function group(string $class, string $content, string $tag = 'div'): string
    {
        $attrs = ['className' => $class, 'layout' => ['type' => 'default']];
        if ($tag !== 'div') $attrs['tagName'] = $tag;
        return '<!-- wp:group ' . json_encode($attrs) . ' --><' . $tag . ' class="wp-block-group ' . self::e($class) . '">' . $content . '</' . $tag . '><!-- /wp:group -->';
    }
    public static function p(string $text, string $class = ''): string
    {
        return '<!-- wp:paragraph' . ($class ? ' ' . json_encode(['className' => $class]) : '') . ' --><p' . ($class ? ' class="' . self::e($class) . '"' : '') . '>' . $text . '</p><!-- /wp:paragraph -->';
    }
    public static function h(string $text, int $level = 2, string $class = ''): string
    {
        $attrs = ['level' => $level];
        if ($class) $attrs['className'] = $class;
        return '<!-- wp:heading ' . json_encode($attrs) . ' --><h' . $level . ' class="wp-block-heading' . ($class ? ' ' . self::e($class) : '') . '">' . $text . '</h' . $level . '><!-- /wp:heading -->';
    }
    public static function image(string $file, string $alt, string $class = ''): string
    {
        return '<!-- wp:image ' . json_encode(['sizeSlug' => 'full', 'linkDestination' => 'none', 'className' => $class]) . ' --><figure class="wp-block-image size-full ' . self::e($class) . '"><img src="' . self::e(self::asset($file)) . '" alt="' . self::e($alt) . '"/></figure><!-- /wp:image -->';
    }
    public static function button(string $label, string $path, string $class = ''): string
    {
        return '<!-- wp:buttons --><div class="wp-block-buttons"><!-- wp:button ' . json_encode(['className' => $class]) . ' --><div class="wp-block-button ' . self::e($class) . '"><a class="wp-block-button__link wp-element-button" href="' . self::e(self::url($path)) . '">' . $label . '</a></div><!-- /wp:button --></div><!-- /wp:buttons -->';
    }
    public static function dynamic(string $name, array $attrs = []): string { return '<!-- wp:northline/' . $name . ' ' . json_encode($attrs, JSON_UNESCAPED_SLASHES) . ' /-->'; }
    public static function details(string $question, string $answer): string { return '<!-- wp:details --><details class="wp-block-details"><summary>' . self::e($question) . '</summary>' . self::p(self::e($answer)) . '</details><!-- /wp:details -->'; }
    private static function intro(string $kicker, string $title, string $description): string { return self::group('nl-page-intro nl-shell', self::p($kicker, 'nl-kicker') . self::h($title, 1) . self::p($description, 'nl-lead')); }
    public static function projects(): array
    {
        $data = json_decode((string) file_get_contents(__DIR__ . '/../content/projects.json'), true, 512, JSON_THROW_ON_ERROR);
        return array_column($data, null, 'slug');
    }
    public static function cta(): string
    {
        return self::group('nl-closing nl-shell', self::group('nl-closing-copy', self::p('YOUR HOME / THE NEXT CHAPTER', 'nl-kicker') . self::h('A good project starts<br>with a <em>good conversation.</em>') . self::p('Tell us what is working, what is not, and what you hope your home could become. We will help put the possibilities in order.')) . self::button('Plan your renovation <span aria-hidden="true">↗</span>', 'plan-your-renovation/'));
    }
    private static function serviceRows(): string
    {
        $html = '';
        foreach ([['01', 'Kitchens', 'kitchens/', 'The centre of daily life, carefully reimagined.'], ['02', 'Extensions', 'extensions/', 'More space. A better relationship with the whole house.'], ['03', 'Whole homes', 'whole-home/', 'A coherent vision, from the first room to the last.']] as [$n, $title, $url, $description]) {
            $html .= self::group('nl-service-row', self::p($n, 'nl-kicker') . self::h('<a href="' . self::e(self::url($url)) . '">' . $title . ' <span aria-hidden="true">↗</span></a>', 3) . self::p($description));
        }
        return $html;
    }
    private static function processRows(): string
    {
        $html = '';
        foreach ([
            ['01', 'Listen & define', 'Your home, your priorities.', 'We start with what the house does well and what needs to change. The project brief records how you live, the spaces affected, the intended timescale and the budget you are comfortable discussing. The first conversation tests scope rather than selling a predetermined solution.'],
            ['02', 'Explore & resolve', 'A design grounded in the existing house.', 'Measured information, concept options and material studies turn the brief into a direction. Surveys and specialist input address the unknowns. Layouts are tested against furniture, circulation, services and the practical sequence of construction.'],
            ['03', 'Detail & build', 'Decisions made at the right time.', 'Technical drawings, specifications and an agreed scope give the construction team a clear basis for the work. Progress reviews keep decisions visible. Samples and junction details are checked before they are repeated throughout the project.'],
            ['04', 'Settle & support', 'A proper finish, not just a finish date.', 'The final review covers the visible details and the systems behind them. Handover includes care information, operating guidance and a record of outstanding items. A considered home should be straightforward to look after as well as enjoyable to live in.'],
        ] as [$n, $title, $sub, $text]) {
            $html .= self::group('nl-process-row', self::p($n, 'nl-process-number') . self::group('', self::h($title) . self::p($sub, 'nl-kicker')) . self::p($text));
        }
        return $html;
    }
    public static function pages(): array
    {
        $home = self::group('nl-hero nl-shell', self::group('nl-hero-heading', self::p('DESIGN & BUILD / PRINCE EDWARD ISLAND', 'nl-kicker') . self::h('A better way<br>to <em>come home.</em>', 1) . self::group('nl-hero-bottom', self::p('Thoughtful renovations.<br>One considered process.<br>A home that feels like you.') . self::button('Explore our work <span aria-hidden="true">↘</span>', 'projects/', 'is-style-outline'))) . self::group('nl-hero-media', self::image('birch-house-after.jpg', 'Original architectural visualisation of an oak and limestone kitchen opening towards a garden', 'nl-hero-image') . self::group('nl-hero-caption', self::p('01 / BIRCH HOUSE', 'nl-kicker') . self::p('Oak. Limestone. Room to breathe.') . self::p('Fictional design study / Original visualisation', 'nl-small'))));
        $home .= self::group('nl-introduction nl-shell', self::p('BUILT AROUND YOU', 'nl-kicker') . self::h('Not just a different house.<br><em>A different feeling.</em>') . self::p('The morning light at the kitchen counter. A room that finally works for everyone. The quiet satisfaction of a detail done properly. We bring design and construction together to make those everyday moments better.', 'nl-lead'));
        $home .= self::group('nl-services-band', self::group('nl-shell', self::group('nl-section-heading', self::p('01 / WHAT WE DO', 'nl-kicker') . self::h('Make more of<br>the home <em>you have.</em>')) . self::serviceRows()));
        $home .= self::group('nl-selected nl-shell', self::group('nl-section-heading', self::group('', self::p('02 / SELECTED DESIGN STUDIES', 'nl-kicker') . self::h('Spaces with<br><em>a point of view.</em>')) . self::button('All six projects ↗', 'projects/', 'is-style-outline')) . self::dynamic('projects', ['limit' => 2, 'filters' => false]) . self::p('Every project shown is a fictional design study, with original matched architectural visualisations.', 'nl-small'));
        $home .= self::group('nl-planning-feature nl-shell', self::dynamic('plan') . self::group('nl-planning-copy', self::p('03 / BEFORE WE PICK UP A PENCIL', 'nl-kicker') . self::h('Your ideas.<br><em>A clearer plan.</em>') . self::p('A renovation begins with questions. Our project planner turns them into a useful brief: your priorities, your timescale and an honest conversation about budget.', 'nl-lead') . self::p('Receive an illustrative range with the assumptions visible. Save your brief. Then choose a time to talk. No invented instant quote. No pressure to have every answer.') . self::button('Plan your renovation ↗', 'plan-your-renovation/') . self::p('Around 5 minutes · No obligation · Your brief stays yours', 'nl-small')));
        $home .= self::group('nl-material-section nl-shell', self::group('nl-material-copy', self::p('A SMALLER PALETTE. A RICHER EXPERIENCE.', 'nl-kicker') . self::h('Materials that<br><em>belong together.</em>') . self::p('Natural grain, a softened edge, the way a surface holds the light. We favour a few well-chosen materials over a room full of competing statements.')) . self::group('nl-material-swatches', self::group('nl-swatch nl-oak', self::p('01 / OAK')) . self::group('nl-swatch nl-stone', self::p('02 / LIMESTONE')) . self::group('nl-swatch nl-lime', self::p('03 / LIMEWASH'))));
        $home .= self::cta();
        $studio = self::intro('THE STUDIO / OUR POINT OF VIEW', 'Thoughtful by design.<br><em>Accountable by nature.</em>', 'NORTHLINE imagines a simpler relationship between the people who design a home and the people who build it. One conversation, carried carefully from first sketch to final detail.');
        $studio .= self::image('alder-house-after.jpg', 'Living-room design study with linen seating, oak flooring and a stone hearth', 'nl-wide-image');
        $studio .= self::group('nl-editorial nl-shell', self::p('A FICTIONAL PRACTICE WITH A REAL STANDARD', 'nl-kicker') . self::group('nl-prose', self::h('Good design is not<br><em>an extra layer.</em>') . self::p('It is the arrangement of things that make a home easier to live in: where daylight falls, how rooms connect, where belongings go and how materials meet. The most important decisions are often the least visible in a photograph.') . self::p('Our design-and-build model is built around continuity. The brief should survive the move from concept to technical detail. Materials should be chosen with their installation in mind. The programme should acknowledge the realities of working in an existing house.') . self::h('Clarity is part of the craft.', 3) . self::p('A beautiful proposal is not enough when the scope is unclear. We make assumptions explicit, record decisions and explain what needs further investigation. A provisional allowance is labelled as one. A change in scope is discussed before it becomes a surprise.') . self::p('NORTHLINE is a fictional portfolio practice. There are no invented awards, client reviews or completed-project claims here. The work demonstrates an editable website and a considered enquiry process.')));
        $studio .= self::cta();
        $approach = self::intro('OUR APPROACH / FROM FIRST QUESTION TO FINAL DETAIL', 'A clear process.<br><em>Room for good ideas.</em>', 'Renovation involves unknowns. A considered process does not pretend otherwise; it gives each question the right time, evidence and owner.') . self::group('nl-shell nl-process-list', self::processRows());
        $approach .= self::group('nl-editorial nl-shell', self::p('WHAT WE MAKE EXPLICIT', 'nl-kicker') . self::group('nl-prose', self::h('The practical things<br><em>matter just as much.</em>') . self::p('Before construction, an agreed scope should identify inclusions, exclusions, provisional items and responsibilities. The programme should account for approvals, lead times, site access and decisions that depend on opening up the existing building.') . self::p('Changes should be described and priced before approval wherever practical. Progress reviews should address decisions needed next, not merely report what happened last week. At handover, the finished space should come with operating information, maintenance guidance and a clear route for resolving defects.') . self::details('Can we live at home during the renovation?', 'Sometimes, but not by assumption. The answer depends on the extent of work, access, dust separation, facilities and the household’s needs. Temporary accommodation may be more practical; it is excluded from the illustrative range.') . self::details('When does an estimate become a quotation?', 'The planner produces a broad illustrative allowance only. A priced proposal requires a measured scope, design decisions, site investigation and confirmation of responsibilities and exclusions. The website does not create a construction contract.') . self::details('What happens when something unexpected is found?', 'Record the finding, investigate the options and explain the effect on scope, cost and programme. A contingency is a planning allowance, not permission to spend without discussion.')));
        $pages = [
            '' => ['slug' => 'home', 'title' => 'Home', 'content' => $home],
            'studio' => ['slug' => 'studio', 'title' => 'The studio', 'content' => $studio],
            'approach' => ['slug' => 'approach', 'title' => 'Our approach', 'content' => $approach . self::cta()],
            'kitchens' => ['slug' => 'kitchens', 'title' => 'Kitchen renovations', 'content' => self::service('kitchen')],
            'extensions' => ['slug' => 'extensions', 'title' => 'Home extensions', 'content' => self::service('extension')],
            'whole-home' => ['slug' => 'whole-home', 'title' => 'Whole-home renovations', 'content' => self::service('whole-home')],
            'projects' => ['slug' => 'projects', 'title' => 'Selected projects', 'content' => self::intro('THE PROJECT INDEX / SIX FICTIONAL DESIGN STUDIES', 'Different homes.<br>The same <em>care.</em>', 'Explore the problem, the decisions and the details behind each proposal. Every case study includes matched views from the same camera, not unrelated before-and-after photographs.') . self::group('nl-shell nl-gallery-page', self::dynamic('projects')) . self::cta()],
            'plan-your-renovation' => ['slug' => 'plan-your-renovation', 'title' => 'Plan your renovation', 'content' => self::intro('YOUR PROJECT / A CONSIDERED BEGINNING', 'Let’s make room<br>for <em>your plans.</em>', 'A few useful questions. A clear project brief. An illustrative budget range with its assumptions in plain sight.') . self::group('nl-shell nl-planner-shell', self::dynamic('planner'))],
            'consultation' => ['slug' => 'consultation', 'title' => 'Your consultation', 'content' => self::intro('YOUR PRIVATE PROJECT DESK', 'The next step:<br><em>a conversation.</em>', 'Review your saved brief and reserve a 45-minute introductory consultation. Appointment times include their timezone.') . self::group('nl-shell nl-planner-shell', self::dynamic('planner', ['mode' => 'consultation']))],
            'privacy' => ['slug' => 'privacy', 'title' => 'Privacy & project information', 'content' => self::privacy()],
        ];
        return $pages;
    }
    private static function service(string $type): string
    {
        $data = [
            'kitchen' => ['KITCHEN RENOVATIONS', 'The room where<br><em>life happens.</em>', 'A kitchen has to do more than look beautiful. It should make the ordinary rhythms of the day feel easier, from the first cup of coffee to the last conversation after dinner.', 'birch-house', 'Start with the way you use it.', 'We look at preparation, cooking, storage, seating and circulation as one arrangement. The right island is not necessarily the largest one. The most useful storage is not always the most visible. A thoughtful plan gives each activity room without turning the kitchen into a collection of separate zones.', 'Resolve the working details.', 'Joinery, appliances, extraction, lighting and services need to be coordinated before fabrication. We consider worktop junctions, clearances, cleaning and maintenance alongside colour and material. The result should work with cupboard doors open and several people in the room, not only in a photograph.', 'Bring photographs of the existing room, approximate dimensions, the appliances you want to retain and a list of the everyday frustrations you would like to solve.'],
            'extension' => ['HOME EXTENSIONS', 'More than<br><em>additional space.</em>', 'A good extension changes the relationship between rooms, daylight and the garden. The new square footage is only part of the opportunity.', 'tidal-house', 'Improve the whole, not just the addition.', 'Before extending, we ask how the existing house will work with the new space. An extension can solve a poor connection to the garden, create a generous gathering room or bring light deeper into the plan. It can also create a dark middle room when the relationship is not properly considered.', 'Make the junctions matter.', 'Old and new construction rarely meet without complexity. Levels, drainage, foundations, insulation, roof edges and movement need coordination. We make space for surveys, approvals and specialist advice, then develop a design that is clear about what is retained and what is new.', 'Bring a simple site sketch, photographs of the relevant elevation and garden, any existing drawings, and a description of access or boundary constraints. Avoid documents containing unnecessary personal information.'],
            'whole-home' => ['WHOLE-HOME RENOVATIONS', 'A house that<br><em>makes sense together.</em>', 'Bring the rooms, materials and practical systems of your home into one coherent plan, without erasing what made you choose it in the first place.', 'alder-house', 'Begin with the whole picture.', 'A whole-home renovation is an opportunity to resolve the relationships between spaces before committing to individual finishes. We map circulation, storage, daylight and services, then establish which changes have the greatest effect on daily life. Not every room needs the same intervention.', 'Plan the work around real life.', 'Phasing, access, temporary facilities and the condition of the building shape the programme. A beautiful finish cannot compensate for unresolved ventilation, tired services or a sequence that makes occupation impractical. The project needs a coordinated technical plan as well as a visual one.', 'Bring an approximate floor plan, a room-by-room list of priorities, known issues with the building and an indication of whether you hope to remain in occupation.'],
        ][$type];
        [$kicker, $title, $lead, $project, $h1, $p1, $h2, $p2, $prepare] = $data;
        return self::intro('WHAT WE DO / ' . $kicker, $title, $lead) . self::image($project . '-after.jpg', self::projects()[$project]['title'] . ' proposed architectural visualisation', 'nl-wide-image') . self::group('nl-editorial nl-shell', self::p('SCOPE / DETAIL / DAILY LIFE', 'nl-kicker') . self::group('nl-prose', self::h($h1) . self::p($p1) . self::h($h2, 3) . self::p($p2) . self::h('What to bring to the first conversation', 3) . self::p($prepare) . self::details('What does the project planner include?', 'Your project type, existing property size, affected area, finish level, desired timing, budget and priorities. The illustrative range includes construction, a design allowance and contingency, with exclusions shown. It is not a survey, quotation or availability guarantee.') . self::details('Can we start with an uncertain budget?', 'Yes. Select “Exploring the right budget”. You will still see the illustrative assumptions. The consultation can explore priorities, scope and phasing without pretending that a precise price is known.'))) . self::group('nl-shell nl-related', self::p('SEE THE THINKING IN PRACTICE', 'nl-kicker') . self::h(self::projects()[$project]['title']) . self::button('Read the complete design study ↗', 'project/' . $project . '/', 'is-style-outline')) . self::cta();
    }
    public static function caseStudy(array $project): string
    {
        $content = self::intro('DESIGN STUDY / ' . strtoupper(Brief::TYPES[$project['type']]), $project['headline'], $project['title'] . ' — ' . $project['location'] . '. An original fictional proposal exploring daily life, material and light.') . self::image($project['slug'] . '-after.jpg', $project['title'] . ' proposed architectural visualisation', 'nl-wide-image');
        $facts = '';
        foreach (['PROJECT' => $project['title'], 'SCOPE' => $project['area'], 'PROGRAMME' => $project['programme'], 'STATUS' => 'Fictional / Concept design'] as $label => $value) $facts .= self::group('', self::p($label, 'nl-kicker') . self::p($value));
        $content .= self::group('nl-project-facts nl-shell', $facts);
        foreach ([['01 / THE STARTING POINT', 'What wasn’t working.', 'problem'], ['02 / THE DESIGN DECISIONS', 'A more considered arrangement.', 'decision'], ['03 / THE MATERIAL PALETTE', 'A few things, chosen well.', 'materialsText']] as [$kicker, $title, $key]) $content .= self::group('nl-editorial nl-shell', self::p($kicker, 'nl-kicker') . self::group('nl-prose', self::h($title) . self::p(self::e($project[$key]))));
        $content .= self::group('nl-shell nl-case-comparison', self::group('nl-section-heading', self::p('THE CHANGE / SAME VIEWPOINT', 'nl-kicker') . self::h('Before. After.<br><em>The difference is in the detail.</em>')) . self::dynamic('comparison', ['before' => self::asset($project['slug'] . '-before.jpg'), 'after' => self::asset($project['slug'] . '-after.jpg'), 'label' => $project['title']]));
        $stages = '';
        foreach ($project['stages'] as $i => $text) $stages .= self::group('nl-stage', self::p(sprintf('%02d', $i + 1), 'nl-kicker') . self::p(self::e($text)));
        $content .= self::group('nl-editorial nl-shell', self::p('04 / THE CONSTRUCTION SEQUENCE', 'nl-kicker') . self::group('nl-prose', self::h('The less glamorous<br><em>work matters.</em>') . self::p('A proposed construction sequence, not a record of work carried out. Each stage would be developed with the appointed professionals and contractor.') . $stages));
        $content .= self::group('nl-editorial nl-shell', self::p('05 / THE PROPOSED RESULT', 'nl-kicker') . self::group('nl-prose', self::h('A home, better considered.') . self::p(self::e($project['result'])) . self::h('Material notes', 3) . self::p(self::e(implode(' / ', $project['materials']))) . self::p('Image provenance: original Blender Cycles architectural visualisations. Existing and proposed states use the same camera, lens and shell. Images are not construction drawings or photographs of completed work.', 'nl-small')));
        return $content . self::cta();
    }
    private static function privacy(): string
    {
        $sections = [
            'What the WordPress planner stores' => 'A submitted brief contains contact details, approximate property information, project preferences, priorities and consent. The system also stores the illustrative estimate, private reference images, a consultation booking, email-transport state and staff notes. References are processed to remove embedded metadata and are not published in the public Media Library.',
            'Why it is stored' => 'The information is used to prepare and discuss the project, send the requested brief and manage a consultation. The form does not subscribe you to marketing. Avoid uploading identification, financial records, access codes or photographs containing people who have not agreed to share them.',
            'Access and retention' => 'Authorized studio staff can access enquiries. The private brief link is a bearer credential: anyone holding it can view the brief and reserve its consultation. Do not share it publicly. It expires after 30 days. Enquiries and associated records are removed after 180 days without an update. Staff can erase an enquiry earlier through the protected dashboard.',
            'Email and service providers' => 'A deployed WordPress installation uses its configured hosting and email providers. The operator must identify those providers and its own privacy contact before accepting real enquiries. Optional spam protection may use Cloudflare Turnstile when configured. The default website does not load advertising, analytics or tracking pixels.',
            'The standalone browser demonstration' => 'The standalone preview does not submit information to a company. Demo enquiries and appointments are stored only in this browser. No email is sent and no real consultation is arranged. The demo studio desk is not a protected production dashboard. Clear demo data from that desk or clear this site’s browser storage to remove it.',
            'Before operating as a real business' => 'Replace the fictional practice notice with the operator’s verified identity, contact details, service area and appropriate privacy information. Configure secure hosting, backups, an email provider and a monitored consultation schedule. Review the illustrative rate card with a qualified local professional; the sample figures are not researched market prices.',
        ];
        $body = '';
        foreach ($sections as $title => $text) $body .= self::h($title, 2) . self::p(self::e($text));
        return self::intro('PRIVACY / PLAINLY EXPLAINED', 'Your project.<br><em>Your information.</em>', 'This website demonstrates a fictional renovation practice. Use fictional contact details and non-sensitive reference images when exploring the portfolio demo.') . self::group('nl-editorial nl-shell', self::p('DATA & DEMONSTRATION NOTICE', 'nl-kicker') . self::group('nl-prose', $body));
    }
    public static function seed(): array
    {
        $created = [];
        foreach (self::pages() as $page) {
            $existing = get_page_by_path($page['slug'], OBJECT, 'page');
            $id = $existing ? $existing->ID : wp_insert_post(['post_type' => 'page', 'post_status' => 'publish', 'post_title' => $page['title'], 'post_name' => $page['slug'], 'post_content' => wp_slash($page['content'])], true);
            if (is_wp_error($id)) throw new \RuntimeException($id->get_error_message());
            if (!$existing) $created[] = $page['slug'];
            if ($page['slug'] === 'home' && !get_option('page_on_front')) { update_option('show_on_front', 'page'); update_option('page_on_front', $id); }
            if ($page['slug'] === 'privacy' && !get_option('wp_page_for_privacy_policy')) update_option('wp_page_for_privacy_policy', $id);
        }
        foreach (array_values(self::projects()) as $index => $project) {
            if (get_page_by_path($project['slug'], OBJECT, 'nl_project')) continue;
            $id = wp_insert_post(['post_type' => 'nl_project', 'post_status' => 'publish', 'post_title' => $project['title'], 'post_name' => $project['slug'], 'post_content' => wp_slash(self::caseStudy($project)), 'post_excerpt' => $project['location'] . ' / ' . $project['area'], 'menu_order' => $index], true);
            if (is_wp_error($id)) throw new \RuntimeException($id->get_error_message());
            foreach (['nl_type' => $project['type'], 'nl_area' => $project['area'], 'nl_location' => $project['location'], 'nl_image' => self::asset($project['slug'] . '-after.jpg')] as $key => $value) update_post_meta($id, $key, $value);
            wp_set_object_terms($id, Brief::TYPES[$project['type']], 'nl_project_type');
            $created[] = $project['slug'];
        }
        update_option('northline_demo_seeded', true, false);
        flush_rewrite_rules();
        return $created;
    }
}
