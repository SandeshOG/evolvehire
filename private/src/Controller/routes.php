<?php
declare(strict_types=1);

/**
 * Route table. One line per public page; controllers are the page_ functions
 * in private/views/. Dispatch happens in app.php.
 */
return static function (\Evolve\Core\Router $r): void {
    // Public site
    $r->get('/', 'page_home');
    $r->get('/jobs', 'page_jobs');
    $r->get('/jobs/{slug}', 'page_job');
    $r->get('/resume', 'page_resume');
    $r->get('/hire', 'page_hire');
    $r->get('/track', 'page_track');
    $r->get('/job-alerts', 'page_job_alerts');
    $r->get('/contact', 'page_contact');
    $r->get('/about', 'page_about');
    $r->get('/work', 'page_work');
    $r->get('/services', 'page_services');
    $r->get('/industries', 'page_industries');
    $r->get('/functions', 'page_functions');
    $r->get('/insights', 'page_insights');
    $r->get('/insights/{slug}', 'page_insight');
    $r->get('/case-studies', 'page_case_studies');
    $r->get('/case-studies/{slug}', 'page_case_study');
    $r->get('/companies', 'page_companies');
    $r->get('/careers', 'page_careers');
    $r->get('/credentials', 'page_credentials');
    $r->get('/sitemap.xml', 'page_sitemap');
    $r->get('/robots.txt', 'page_robots');

    // Desk
    $r->get('/gate', 'page_gate');
    $r->post('/gate', 'page_gate');
    $r->post('/logout', 'page_logout');
    $r->get('/change-password', 'page_change_password');
    $r->post('/change-password', 'page_change_password');
    $r->get('/desk', 'page_desk');
    $r->post('/desk', 'page_desk');
    $r->get('/file/{kind}/{id}', 'page_file');
    $r->get('/forgot', 'page_forgot');
    $r->post('/forgot', 'page_forgot');
    $r->get('/set-password', 'page_set_password');
    $r->post('/set-password', 'page_set_password');
    $r->get('/admin', 'page_admin');
    $r->post('/admin', 'page_admin');

    // POST handlers on GET pages (forms render themselves)
    $r->post('/jobs/{slug}', 'page_job');
    $r->post('/resume', 'page_resume');
    $r->post('/hire', 'page_hire');
    $r->post('/track', 'page_track');
    $r->post('/job-alerts', 'page_job_alerts');
    $r->post('/contact', 'page_contact');
};
