<?php
declare(strict_types=1);

use Evolve\Core\Http;
use Evolve\Core\Response;
use Evolve\Repository\ContentRepo;
use Evolve\Repository\JobRepo;
use Evolve\Service\ApplyService;

function page_home(): void
{
    $f = firm();
    $jobs = JobRepo::latest(6);
    $industries = ContentRepo::published('industries');
    $functions = ContentRepo::published('functions');
    $services = array_values(array_filter(ContentRepo::published('services'), fn ($s) => $s['slug'] !== 'contract-staffing'));
    $insights = ContentRepo::published('insights', 3);

    $list = '';
    foreach ($jobs as $job) {
        $list .= tpl_job_card($job);
    }
    if ($list === '') {
        $list = '<p class="muted">Nothing is open on the board today. You can still <a href="' . h(url('/resume')) . '">leave a resume</a>.</p>';
    }

    $fn = '';
    foreach ($functions as $function) {
        $fn .= '<a class="chip card-lift" href="' . h(url('/jobs' . query_string(['function' => $function['slug']]))) . '">' . h($function['name']) . '</a>';
    }

    $svg = static fn (string $d): string => '<svg viewBox="0 0 24 24" width="26" height="26" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" fill="none" aria-hidden="true">' . $d . '</svg>';
    $icons = [
        $svg('<path d="M3 8h18v11H3z M9 8V5h6v3 M3 13h18"/>'),
        $svg('<circle cx="9" cy="8" r="3.2"/><path d="M3 20c0-3.5 2.7-6 6-6s6 2.5 6 6"/><circle cx="17" cy="9" r="2.4"/><path d="M17 14c2.6 0 4 2 4 5"/>'),
        $svg('<circle cx="11" cy="11" r="6.5"/><path d="M16 16l5 5"/>'),
    ];
    $sv = '';
    foreach ($services as $i => $s) {
        $sv .= '<article class="card card-lift rise" style="animation-delay:' . ($i * 0.08) . 's"><span class="ico">' . $icons[$i % 3] . '</span><h2>' . h($s['name']) . '</h2><p>' . h($s['summary']) . '</p></article>';
    }

    $ind = '';
    foreach ($industries as $i) {
        $ind .= '<a class="chip card-lift" href="' . h(url('/jobs' . query_string(['industry' => $i['slug']]))) . '">' . h($i['name']) . '</a>';
    }

    $ins = '';
    foreach ($insights as $post) {
        $ins .= '<a class="row card-lift" href="' . h(url('/insights/' . $post['slug'])) . '"><strong>' . h($post['title']) . '</strong><span class="muted">' . h($post['excerpt']) . '</span></a>';
    }

    $body = '<section class="hero" aria-labelledby="hero-title">';
    $body .= '<div class="wrap hero-grid"><div class="hero-copy">';
    $body .= '<p class="kicker rise">Recruitment · Nagpur</p>';
    $body .= '<h1 id="hero-title" class="rise">The role is open. The step stays visible.</h1>';
    $body .= '<p class="lede rise">Search mandates across India. Apply without an account. A reference — or the same email — shows exactly where you stand.</p>';
    $body .= '<form class="search rise" action="' . h(url('/jobs')) . '" method="get" role="search">';
    $body .= '<label class="sr" for="home-q">Search roles</label>';
    $body .= '<input id="home-q" name="q" placeholder="Role, company or city" autocomplete="off">';
    $body .= '<button class="btn btn-gold" type="submit">Search roles</button></form>';
    $body .= '<div class="hero-links rise"><a class="btn btn-line" href="' . h(url('/resume')) . '">Submit a resume</a>';
    $body .= '<a href="' . h(url('/track')) . '">Already applied? Check status</a></div>';
    $body .= '</div>';
    $body .= '<div class="markstage rise" aria-hidden="true"><svg viewBox="0 0 32 32">';
    $body .= '<circle class="mark-ring" cx="16" cy="16" r="14.2"/>';
    $body .= '<path class="mark-e" fill="#F4EFE4" d="M4 6h10v4H8v4h4v4H8v4h6v4H4Z"/>';
    $body .= '<path class="mark-h" fill="#C6A15A" d="M16 6h4v8h4V6h4v20h-4v-8h-4v8h-4Z"/>';
    $body .= '</svg><p class="mark-cap"><span>Nagpur</span><span>India</span></p></div>';
    $body .= '</div></section>';

    $names = [];
    foreach ($industries as $industry) {
        $names[] = (string) $industry['name'];
    }
    if ($names === []) {
        $names = ['Manufacturing', 'Engineering', 'FMCG', 'Healthcare'];
    }
    $tape = '';
    foreach (array_merge($names, $names) as $name) {
        $tape .= '<span>' . h($name) . '</span>';
    }
    $body .= '<div class="marquee" aria-hidden="true"><div class="marquee-track">' . $tape . '</div></div>';

    $body .= '<section class="cred" aria-label="On record"><div class="wrap">';
    $body .= '<div><span class="kicker">Since</span><p>' . h($f['commenced']) . '</p></div>';
    $body .= '<div><span class="kicker">Desk</span><p>Nagpur</p></div>';
    $body .= '<div><span class="kicker">GSTIN</span><p>' . h($f['gstin']) . '</p></div>';
    $body .= '<div><span class="kicker">Udyam</span><p>' . h($f['udyam']) . '</p></div>';
    $body .= '</div></section>';

    $stat = static fn (string $n, string $label): string => '<div class="sb"><p class="big">' . $n . '</p><p class="muted">' . h($label) . '</p></div>';
    $body .= '<section class="statband" aria-label="At a glance"><div class="wrap statgrid">'
        . $stat((string) max(1, (int) date('Y') - 2020), 'Years on the desk')
        . $stat((string) count($functions), 'Functions we search')
        . $stat((string) count($industries), 'Sectors covered')
        . $stat((string) count($services), 'Ways to hire')
        . '</div></section>';

    $step = static fn (string $d, string $t, string $p): string => '<li><span class="stepdot">' . $d . '</span><h3>' . h($t) . '</h3><p class="muted">' . h($p) . '</p></li>';
    $body .= '<section class="section" aria-labelledby="hw"><div class="wrap"><div class="sec-head"><h2 id="hw">How applying works</h2></div><ol class="howto">'
        . $step($svg('<circle cx="11" cy="11" r="6.5"/><path d="M16 16l5 5"/>'), 'Find a role', 'Search the board by role, city or sector. No account.')
        . $step($svg('<path d="M6 3h8l4 4v14H6z M14 3v4h4 M9 13h6 M9 17h6"/>'), 'Apply once', 'Send the resume on the form. A reference comes back immediately.')
        . $step($svg('<path d="M4 12l5 5L20 6"/>'), 'Watch the step', 'The status page reads the reference or the same email.')
        . '</ol></div></section>';
    $body .= '<section class="section alt" aria-labelledby="or"><div class="wrap"><div class="sec-head"><h2 id="or">Open roles</h2><a href="' . h(url('/jobs')) . '">Whole board</a></div>';
    $body .= '<div class="jobs">' . $list . '</div></div></section>';
    $body .= '<section class="section" aria-labelledby="ts"><div class="wrap"><div class="sec-head"><h2 id="ts">How we search</h2><a href="' . h(url('/services')) . '">All services</a></div><div class="grid-3">' . $sv . '</div></div></section>';
    $body .= '<section class="section alt" aria-labelledby="rf"><div class="wrap"><div class="sec-head"><h2 id="rf">Roles we search</h2><a href="' . h(url('/functions')) . '">All functions</a></div><div class="chips">' . $fn . '</div></div></section>';
    $body .= '<section class="section" aria-labelledby="sec"><div class="wrap"><div class="sec-head"><h2 id="sec">Sectors</h2><a href="' . h(url('/industries')) . '">All industries</a></div><div class="chips">' . $ind . '</div></div></section>';
    if ($ins !== '') {
        $body .= '<section class="section alt" aria-labelledby="ins"><div class="wrap"><div class="sec-head"><h2 id="ins">From the desk</h2><a href="' . h(url('/insights')) . '">All notes</a></div><div class="jobs">' . $ins . '</div></div></section>';
    }

    $body .= '<section class="ctaband" aria-labelledby="cta"><div class="wrap"><h2 id="cta">Two ways in</h2><div class="ctagrid">'
        . '<div><h3>Looking for a role?</h3><p>Leave a resume once. The desk matches it to open searches.</p><a class="btn btn-gold" href="' . h(url('/resume')) . '">Submit resume</a></div>'
        . '<div><h3>Hiring a team?</h3><p>Send a short brief. The Nagpur desk reads every one.</p><a class="btn btn-line" href="' . h(url('/hire')) . '">Send a brief</a></div>'
        . '</div></div></section>';

    $jsonld = Response::jsonld([
        '@context' => 'https://schema.org',
        '@type' => 'EmploymentAgency',
        'name' => $f['name'],
        'description' => 'Recruitment agency in Nagpur. Candidates apply and check status with a reference or email.',
        'url' => $f['url'],
        'telephone' => $f['tel'],
        'email' => $f['email'],
        'foundingDate' => '2020-01-01',
        'address' => [
            '@type' => 'PostalAddress',
            'streetAddress' => 'Plot No. 51, Bhawani Nagar, Pardi',
            'addressLocality' => 'Nagpur',
            'addressRegion' => 'Maharashtra',
            'postalCode' => '440035',
            'addressCountry' => 'IN',
        ],
    ]);
    $body .= $jsonld;

    Evolve\Core\View::layout(
        'Evolve Hire | Recruitment agency in Nagpur',
        $body,
        ['canonical' => url('/')]
    );
}

function page_jobs(): void
{
    $filters = [
        'q' => field_get('q', 120),
        'industry' => field_get('industry', 190),
        'function' => field_get('function', 190),
        'location' => field_get('location', 120),
        'experience' => field_get('experience', 80),
        'work_mode' => field_get('work_mode', 40),
        'employment_type' => field_get('employment_type', 40),
        'sort' => field_get('sort', 40),
    ];
    $validSort = in_array($filters['sort'], ['', 'newest', 'salary', 'expiry'], true) ? $filters['sort'] : '';
    $filters['sort'] = $validSort;
    $filters['page'] = max(1, (int) ($_GET['page'] ?? 1));

    $result = JobRepo::search($filters);
    $industries = ContentRepo::published('industries');
    $functions = ContentRepo::published('functions');
    $locations = JobRepo::distinct('location');

    $cards = '';
    foreach ($result['rows'] as $job) {
        $cards .= tpl_job_card($job);
    }
    if ($cards === '') {
        $cards = '<p class="card">No open roles match this search. <a href="' . h(url('/resume')) . '">Leave a resume</a> or <a href="' . h(url('/jobs')) . '">clear the filters</a>.</p>';
    }

    $action = url('/jobs');
    $body = '<section class="section"><div class="wrap"><h1>Open roles</h1>';
    $body .= '<p class="lede-ink">Search live mandates. Filters run on the database, and the address in your browser is the search — you can share it.</p>';
    $body .= '<form class="card filters" method="get" action="' . h($action) . '">';
    $body .= tpl_field('q', 'Search', 'text', $filters['q'], ['placeholder' => 'Title, company, city', 'autocomplete' => 'off']);
    $body .= tpl_select('industry', 'Industry', array_column_keyed($industries, 'slug', 'name'), $filters['industry']);
    $body .= tpl_select('function', 'Function', array_column_keyed($functions, 'slug', 'name'), $filters['function']);
    $body .= tpl_select('location', 'Location', array_fill_keys($locations, null) ? array_combine($locations, $locations) : [], $filters['location']);
    $body .= tpl_select('experience', 'Experience', array_combine(experience_bands(), experience_bands()), $filters['experience']);
    $body .= '<details class="more"><summary>More filters</summary><div class="grid-2">';
    $body .= tpl_select('work_mode', 'Work mode', work_modes(), $filters['work_mode']);
    $body .= tpl_select('employment_type', 'Employment type', employment_types(), $filters['employment_type']);
    $body .= '</div></details>';
    $body .= tpl_select('sort', 'Sort by', ['newest' => 'Newest', 'salary' => 'Salary', 'expiry' => 'Closing soon'], $filters['sort'] !== '' ? $filters['sort'] : 'newest');
    $body .= '<div class="filter-actions"><button class="btn btn-navy" type="submit">Apply filters</button>';
    $body .= '<a class="btn btn-line" href="' . h(url('/jobs')) . '">Clear</a></div></form>';
    $body .= '<p class="muted" role="status">' . (int) $result['total'] . ' role' . ($result['total'] === 1 ? '' : 's') . '</p>';
    $body .= '<div class="jobs">' . $cards . '</div>';

    if ($result['pages'] > 1) {
        $body .= '<nav class="pager" aria-label="Pages">';
        $prev = $result['page'] - 1;
        $next = $result['page'] + 1;
        if ($prev >= 1) {
            $body .= '<a class="btn btn-line" href="' . h(url('/jobs' . query_string(array_merge($filters, ['page' => $prev])))) . '">Previous</a>';
        }
        $body .= '<span class="muted">Page ' . $result['page'] . ' of ' . $result['pages'] . '</span>';
        if ($next <= $result['pages']) {
            $body .= '<a class="btn btn-line" href="' . h(url('/jobs' . query_string(array_merge($filters, ['page' => $next])))) . '">Next</a>';
        }
        $body .= '</nav>';
    }
    $body .= '</div></section>';

    Evolve\Core\View::layout('Jobs | Evolve Hire', $body, ['canonical' => url('/jobs' . query_string($filters))]);
}

/** Reindex a rows array by one column → keyed [col => otherCol]. */
function array_column_keyed(array $rows, string $keyCol, string $valCol): array
{
    $out = [];
    foreach ($rows as $r) {
        $out[$r[$keyCol]] = $r[$valCol];
    }
    return $out;
}

function page_job(array $p): void
{
    $slug = (string) ($p['slug'] ?? '');
    $job = JobRepo::bySlug($slug);
    if (!$job) {
        http_response_code(404);
        Evolve\Core\View::layout('Role not found | Evolve Hire', '<section class="section"><div class="wrap"><h1>This role is not open</h1><p class="muted">It may have closed. <a href="' . h(url('/jobs')) . '">See the board</a>.</p></div></section>');
        return;
    }

    $errors = [];
    $done = null;
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        try {
            check_csrf();
            $r = ApplyService::submit((int) $job['id'], $_POST, $_FILES['resume'] ?? []);
            $done = $r;
        } catch (Evolve\Core\ValidationError $e) {
            $errors['form'] = $e->getMessage();
        } catch (Throwable $e) {
            log_error($e);
            $errors['form'] = 'Could not save that application. Try again.';
        }
    }

    $facts = [
        'Company' => $job['company'],
        'Location' => $job['location'],
        'Industry' => $job['industry'],
        'Function' => $job['function_name'],
        'Experience' => $job['experience_label'],
        'Salary' => $job['salary_label'],
        'Work mode' => work_modes()[$job['work_mode']] ?? $job['work_mode'],
        'Employment' => employment_types()[$job['employment_type']] ?? $job['employment_type'],
    ];
    $body = '<section class="section"><div class="wrap"><div class="job-grid">';
    $body .= '<div>';
    $body .= '<p class="kicker" style="color:var(--brand)">' . h($job['company']) . '</p>';
    $body .= '<h1>' . h($job['title']) . '</h1>';
    $body .= '<dl class="facts-dl">';
    foreach ($facts as $k => $v) {
        if ((string) $v !== '') {
            $body .= '<div><dt>' . h($k) . '</dt><dd>' . h((string) $v) . '</dd></div>';
        }
    }
    $body .= '</dl>';
    foreach ([['The role', $job['description']], ['Responsibilities', $job['responsibilities']], ['Requirements', $job['requirements']], ['Eligibility', $job['eligibility']], ['Interview process', $job['interview_process']], ['How to apply', $job['application_instructions']]] as [$heading, $text]) {
        if (trim((string) $text) !== '') {
            $body .= '<h2>' . h($heading) . '</h2><div class="prose">' . nl2br(h($text)) . '</div>';
        }
    }
    $body .= '</div>';
    $body .= '<aside class="apply-aside"><h2>Apply</h2>';
    if ($done !== null) {
        $body .= tpl_notice_good('<strong>Application stored.</strong> Keep the reference.');
        $body .= '<p class="refbox">' . h($done['ref']) . '</p>';
        $body .= '<p class="muted">Check it on the <a href="' . h(url('/track')) . '">status page</a> with this code or your email.</p>';
    } else {
        if ($errors !== []) {
            $body .= tpl_error_summary($errors);
        }
        $body .= '<form class="apply-form" method="post" enctype="multipart/form-data" novalidate>';
        $body .= '<input type="hidden" name="csrf" value="' . h(csrf()) . '">';
        $body .= '<input type="text" name="companyUrl" class="hp" tabindex="-1" autocomplete="off" aria-hidden="true">';
        $body .= tpl_field('name', 'Name', 'text', field('name', 120), ['required' => true, 'autocomplete' => 'name'], '', $errors['name'] ?? '');
        $body .= tpl_field('mobile', 'Mobile', 'tel', field('mobile', 20), ['required' => true, 'autocomplete' => 'tel'], '', $errors['mobile'] ?? '');
        $body .= tpl_field('email', 'Email', 'email', field('email', 190), ['required' => true, 'autocomplete' => 'email'], 'You will use this email to check the step.', $errors['email'] ?? '');
        $body .= tpl_field('current_location', 'Current location', 'text', field('current_location', 120));
        $body .= tpl_field('experience', 'Experience', 'text', field('experience', 80), ['placeholder' => 'e.g. 3 years']);
        $body .= tpl_field('notice_period', 'Notice period', 'text', field('notice_period', 80));
        $body .= tpl_field('expected_salary', 'Expected salary', 'text', field('expected_salary', 80));
        $body .= tpl_field('resume', 'Resume', 'file', '', ['accept' => '.pdf,.doc,.docx', 'required' => true], 'PDF, DOC or DOCX, up to 1.5 MB.', $errors['resume'] ?? '');
        $body .= '<button class="btn btn-gold" type="submit">Submit application</button>';
        $body .= '</form>';
    }
    $body .= '</aside></div></div></section>';

    $validThrough = $job['expires_at'] ?: date('Y-m-d', strtotime('+6 months'));
    $jsonld = Response::jsonld([
        '@context' => 'https://schema.org',
        '@type' => 'JobPosting',
        'title' => $job['title'],
        'description' => strip_tags(nl2br(h($job['description'] . "\n" . $job['requirements']))),
        'datePosted' => substr((string) ($job['published_at'] ?? date('Y-m-d')), 0, 10),
        'validThrough' => $validThrough,
        'employmentType' => strtoupper(str_replace('-', '_', $job['employment_type'] ?: 'FULL_TIME')),
        'hiringOrganization' => ['@type' => 'Organization', 'name' => $job['company']],
        'jobLocation' => [
            '@type' => 'Place',
            'address' => ['@type' => 'PostalAddress', 'addressLocality' => $job['location'], 'addressCountry' => 'IN'],
        ],
    ]);
    $body .= $jsonld;

    Evolve\Core\View::layout($job['title'] . ' | Evolve Hire', $body, [
        'canonical' => url('/jobs/' . $job['slug']),
        'og_type' => 'article',
        'description' => mb_substr($job['description'] ?: $job['title'] . ' at ' . $job['company'], 0, 160),
    ]);
}
