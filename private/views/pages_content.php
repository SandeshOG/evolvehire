<?php
declare(strict_types=1);

use Evolve\Repository\ContentRepo;
use Evolve\Repository\JobRepo;

/** Static/content pages: about, work, services, industries, functions, insights, case studies, companies, careers, credentials. */

function page_about(): void
{
    $f = firm();
    $body = '<section class="hero"><div class="wrap"><p class="kicker">About the desk</p>';
    $body .= '<h1>We recruit. We do not supply contractors.</h1>';
    $body .= '<p class="lede">A company sends one role. We find people for that role, from Nagpur, for work across India. Open since ' . h($f['commenced']) . '.</p></div></section>';
    $body .= '<section class="section"><div class="wrap"><div class="grid-3">';
    $body .= '<article class="card card-lift"><h2><span class="num">1</span> The brief</h2><p>Role, place, and what good looks like. One conversation turns that into a mandate the desk can work.</p></article>';
    $body .= '<article class="card card-lift"><h2><span class="num">2</span> The people</h2><p>Each resume stays on the desk with a reference. When a role fits, the person hears about it — and can watch the step.</p></article>';
    $body .= '<article class="card card-lift"><h2><span class="num">3</span> The step</h2><p>Submitted, in review, shortlisted, interview, hired, or not selected. The status page says which one, honestly.</p></article>';
    $body .= '</div>';
    $body .= '<figure class="card founder"><img src="' . h(url('/brand/founder.jpg')) . '" alt="" width="96" height="96">';
    $body .= '<figcaption><strong>' . h($f['proprietor']) . '</strong><br><span class="muted">Proprietor · Nagpur</span></figcaption></figure>';
    $body .= '</div></section>';
    Evolve\Core\View::layout('About Evolve Hire', $body, ['canonical' => url('/about')]);
}

function page_work(): void
{
    $services = ContentRepo::published('services');
    $industries = ContentRepo::published('industries');
    $companies = ContentRepo::published('companies');

    $sv = '';
    foreach ($services as $i => $s) {
        $sv .= '<article class="card"><h2><span class="num">' . ($i + 1) . '</span> ' . h($s['name']) . '</h2><p>' . h($s['summary']) . '</p></article>';
    }
    $ind = '';
    foreach ($industries as $i) {
        $ind .= '<a class="chip card-lift" href="' . h(url('/jobs' . query_string(['industry' => $i['slug']]))) . '">' . h($i['name']) . '</a>';
    }
    $co = '';
    foreach ($companies as $c) {
        $link = $c['website'] !== '' ? ' — <a href="' . h($c['website']) . '" rel="noopener">website</a>' : '';
        $co .= '<article class="card"><h2>' . h($c['name']) . '</h2><p class="muted">' . h($c['summary']) . $link . '</p></article>';
    }
    if ($co === '') {
        $co = '<p class="muted">None are cleared to name yet. Inventing a logo row would be a lie, so this space stays empty until a client agrees.</p>';
    }

    $body = '<section class="section"><div class="wrap">';
    $body .= '<h1>The work, not a borrowed logo wall</h1>';
    $body .= '<p class="lede-ink">A client name is printed only when that organisation has agreed. Until then, this page shows the work itself — the kind of hiring, and the sectors it sits in.</p>';
    $body .= '<h2>What the desk does</h2><div class="grid-3">' . $sv . '</div>';
    $body .= '<h2>Sectors this work covers</h2><div class="chips">' . $ind . '</div>';
    $body .= '<h2>Named organisations</h2>' . $co;
    $body .= '</div></section>';
    Evolve\Core\View::layout('The work | Evolve Hire', $body, ['canonical' => url('/work')]);
}

function page_services(): void
{
    $services = ContentRepo::published('services');
    $sv = '';
    foreach ($services as $i => $s) {
        $sv .= '<article class="card card-lift"><h2><span class="num">' . ($i + 1) . '</span> ' . h($s['name']) . '</h2><p>' . h($s['summary']) . '</p><p class="muted">' . h($s['body']) . '</p></article>';
    }
    $body = '<section class="section"><div class="wrap">';
    $body .= '<h1>Recruitment solutions</h1>';
    $body .= '<p class="lede-ink">Every mandate starts with a brief: the role, the place, and what good looks like.</p>';
    $body .= '<div class="grid-3">' . $sv . '</div>';
    $body .= '<div class="cta-band"><div><h2>Have a role that does not fit a label?</h2><p class="muted">Send the brief anyway. The desk reads each one.</p></div><a class="btn btn-gold" href="' . h(url('/hire')) . '">Send the brief</a></div>';
    $body .= '</div></section>';
    Evolve\Core\View::layout('Recruitment services | Evolve Hire', $body, ['canonical' => url('/services')]);
}

function page_industries(): void
{
    $industries = ContentRepo::published('industries');
    $cards = '';
    foreach ($industries as $i) {
        $cards .= '<a class="card card-lift" href="' . h(url('/jobs' . query_string(['industry' => $i['slug']]))) . '"><h2>' . h($i['name']) . '</h2><p class="muted">' . h($i['summary']) . '</p></a>';
    }
    $body = '<section class="section"><div class="wrap">';
    $body .= '<h1>Where the mandates sit</h1>';
    $body .= '<p class="lede-ink">The sectors this desk recruits for. Each card opens the board filtered to that sector.</p>';
    $body .= '<div class="grid-3">' . $cards . '</div></div></section>';
    Evolve\Core\View::layout('Industries we recruit for | Evolve Hire', $body, ['canonical' => url('/industries')]);
}

function page_functions(): void
{
    $functions = ContentRepo::published('functions');
    $cards = '';
    foreach ($functions as $fn) {
        $cards .= '<a class="card card-lift fn" href="' . h(url('/jobs' . query_string(['function' => $fn['slug']]))) . '"><h2>' . h($fn['name']) . '</h2><p class="muted">' . h($fn['summary']) . '</p></a>';
    }
    $body = '<section class="section"><div class="wrap">';
    $body .= '<h1>The families of roles</h1>';
    $body .= '<p class="lede-ink">Roles group into functions. Pick one to see the open mandates in that family.</p>';
    $body .= '<div class="grid-3">' . $cards . '</div></div></section>';
    Evolve\Core\View::layout('Functions we hire | Evolve Hire', $body, ['canonical' => url('/functions')]);
}

function page_insights(): void
{
    $posts = ContentRepo::published('insights');
    $list = '';
    foreach ($posts as $p) {
        $date = substr((string) ($p['published_at'] ?? ''), 0, 10);
        $list .= '<a class="row card-lift insight" href="' . h(url('/insights/' . $p['slug'])) . '">';
        $list .= '<span class="muted">' . h($date) . '</span><strong>' . h($p['title']) . '</strong><span class="muted">' . h($p['excerpt']) . '</span></a>';
    }
    if ($list === '') {
        $list = '<p class="muted">Notes from the desk will appear here. Nothing is published yet.</p>';
    }
    $body = '<section class="section"><div class="wrap narrow">';
    $body .= '<h1>Insights</h1>';
    $body .= '<p class="lede-ink">Notes from the desk on hiring in and around Nagpur.</p>';
    $body .= $list . '</div></section>';
    Evolve\Core\View::layout('Insights | Evolve Hire', $body, ['canonical' => url('/insights')]);
}

function page_insight(array $p): void
{
    $slug = (string) ($p['slug'] ?? '');
    $post = ContentRepo::bySlug('insights', $slug);
    if (!$post) {
        http_response_code(404);
        Evolve\Core\View::layout('Not found | Evolve Hire', '<section class="section"><div class="wrap"><h1>That page is not on this site.</h1></div></section>');
        return;
    }
    $date = substr((string) ($post['published_at'] ?? ''), 0, 10);
    $body = '<section class="section"><div class="wrap narrow">';
    $body .= '<p class="kicker" style="color:var(--brand)"><a href="' . h(url('/insights')) . '">Insights</a> · ' . h($date) . '</p>';
    $body .= '<h1>' . h($post['title']) . '</h1>';
    $body .= '<div class="prose">' . nl2br(h($post['body'])) . '</div></div></section>';
    $jsonld = \Evolve\Core\Response::jsonld([
        '@context' => 'https://schema.org',
        '@type' => 'Article',
        'headline' => $post['title'],
        'datePublished' => $date,
        'articleBody' => mb_substr(strip_tags((string) $post['body']), 0, 5000),
        'author' => ['@type' => 'Organization', 'name' => 'Evolve Hire'],
    ]);
    $body .= $jsonld;
    Evolve\Core\View::layout($post['title'] . ' | Evolve Hire', $body, [
        'canonical' => url('/insights/' . $post['slug']),
        'og_type' => 'article',
        'description' => (string) $post['excerpt'],
    ]);
}

function page_case_studies(): void
{
    $posts = ContentRepo::published('case_studies');
    $list = '';
    foreach ($posts as $p) {
        $list .= '<a class="row card-lift" href="' . h(url('/case-studies/' . $p['slug'])) . '">';
        $list .= '<strong>' . h($p['title']) . '</strong><span class="muted">' . h($p['excerpt']) . '</span><span class="tags">' . h($p['industry']) . '</span></a>';
    }
    if ($list === '') {
        $list = '<p class="muted">Case notes are published only when a client has agreed to share them. Nothing is cleared yet.</p>';
    }
    $body = '<section class="section"><div class="wrap narrow">';
    $body .= '<h1>Case studies</h1>';
    $body .= '<p class="lede-ink">How a mandate ran, with the numbers, once the client says yes.</p>';
    $body .= $list . '</div></section>';
    Evolve\Core\View::layout('Case studies | Evolve Hire', $body, ['canonical' => url('/case-studies')]);
}

function page_case_study(array $p): void
{
    $slug = (string) ($p['slug'] ?? '');
    $post = ContentRepo::bySlug('case_studies', $slug);
    if (!$post) {
        http_response_code(404);
        Evolve\Core\View::layout('Not found | Evolve Hire', '<section class="section"><div class="wrap"><h1>That page is not on this site.</h1></div></section>');
        return;
    }
    $body = '<section class="section"><div class="wrap narrow">';
    $body .= '<p class="kicker" style="color:var(--brand)"><a href="' . h(url('/case-studies')) . '">Case studies</a> · ' . h($post['industry']) . '</p>';
    $body .= '<h1>' . h($post['title']) . '</h1>';
    $body .= '<div class="prose">' . nl2br(h($post['body'])) . '</div></div></section>';
    Evolve\Core\View::layout($post['title'] . ' | Evolve Hire', $body, [
        'canonical' => url('/case-studies/' . $post['slug']),
        'og_type' => 'article',
        'description' => (string) $post['excerpt'],
    ]);
}

function page_companies(): void
{
    $companies = ContentRepo::published('companies');
    $cards = '';
    foreach ($companies as $c) {
        $link = $c['website'] !== '' ? ' — <a href="' . h($c['website']) . '" rel="noopener">website</a>' : '';
        $cards .= '<article class="card"><h2>' . h($c['name']) . '</h2><p class="muted">' . h($c['summary']) . $link . '</p></article>';
    }
    if ($cards === '') {
        $cards = '<p class="muted">None are cleared to name yet. When an organisation agrees to be listed, it appears here — with its permission on record.</p>';
    }
    $body = '<section class="section"><div class="wrap">';
    $body .= '<h1>Named only with permission</h1>';
    $body .= '<p class="lede-ink">A logo wall means nothing. A name means a client agreed. This page stays honest about which is which.</p>';
    $body .= '<div class="grid-3">' . $cards . '</div></div></section>';
    Evolve\Core\View::layout('Named companies | Evolve Hire', $body, ['canonical' => url('/companies')]);
}

function page_careers(): void
{
    $jobs = JobRepo::careers();
    $list = '';
    foreach ($jobs as $job) {
        $list .= tpl_job_card($job);
    }
    if ($list === '') {
        $list = '<p class="muted">There is no open role on the team right now. When one opens it is listed here. You can still <a href="' . h(url('/resume')) . '">submit your CV</a>.</p>';
    }
    $body = '<section class="section"><div class="wrap narrow">';
    $body .= '<h1>Careers at Evolve Hire</h1>';
    $body .= '<p class="lede-ink">Seats on this side of the desk. Internal roles are listed here first.</p>';
    $body .= $list . '</div></section>';
    Evolve\Core\View::layout('Careers at Evolve Hire', $body, ['canonical' => url('/careers')]);
}

function page_credentials(): void
{
    $f = firm();
    $facts = [
        'Proprietor' => $f['proprietor'],
        'Constitution' => $f['constitution'],
        'Trade name' => 'Evolve Hire',
        'Activity' => $f['activity'] . ' (NIC ' . $f['nic'] . ')',
        'GSTIN' => $f['gstin'],
        'Udyam' => $f['udyam'],
        'Phone' => $f['phone'],
        'Email' => $f['email'],
    ];
    $timeline = [
        ['Commenced', $f['commenced'], 'The desk opened.'],
        ['GST', $f['gst_from'], 'Registration under the goods and services tax.'],
        ['Udyam', $f['udyam_on'], 'MSME registration.'],
        ['Establishment', $f['shop_on'], 'Maharashtra shops and establishments notice (receipt ' . $f['establishment_receipt'] . ', application ' . $f['establishment_app'] . ').'],
    ];
    $body = '<section class="section"><div class="wrap">';
    $body .= '<h1>What is on the certificates</h1>';
    $body .= '<p class="lede-ink">These details live here, not on the front page. They come from the Udyam certificate, GST REG-06 and the Maharashtra establishment notice.</p>';
    $body .= '<div class="split"><dl class="facts-dl card">';
    foreach ($facts as $k => $v) {
        $body .= '<div><dt>' . h($k) . '</dt><dd>' . h((string) $v) . '</dd></div>';
    }
    $body .= '</dl><ol class="timeline">';
    foreach ($timeline as $t) {
        $body .= '<li class="card"><h2>' . h($t[0]) . '</h2><p class="gold">' . h($t[1]) . '</p><p class="muted">' . h($t[2]) . '</p></li>';
    }
    $body .= '</ol></div></div></section>';
    Evolve\Core\View::layout('Firm papers | Evolve Hire', $body, ['canonical' => url('/credentials')]);
}
