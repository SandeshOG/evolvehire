<?php
declare(strict_types=1);

use Evolve\Repository\AlertRepo;
use Evolve\Repository\CandidateRepo;
use Evolve\Repository\ContentRepo;
use Evolve\Service\AlertService;
use Evolve\Service\IntakeService;

/** Candidate pages: submit CV, track status, job alerts. */

function page_resume(): void
{
    $errors = [];
    $done = null;
    $showOptional = isset($_POST['with_details']) || isset($_POST['preferred_location']) || isset($_POST['designation']);
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        try {
            check_csrf();
            $result = IntakeService::submitCv($_POST, $_FILES['resume'] ?? []);
            $done = $result;
        } catch (Evolve\Core\ValidationError $e) {
            $errors['form'] = $e->getMessage();
        } catch (Throwable $e) {
            log_error($e);
            $errors['form'] = 'Could not save the resume.';
        }
    }
    $industries = ContentRepo::published('industries');
    $functions = ContentRepo::published('functions');

    $body = '<section class="section"><div class="wrap narrow">';
    $body .= '<h1>Submit your resume</h1>';
    $body .= '<p class="lede-ink">No account. You get a reference. Check the step later with that reference or this email.</p>';
    if ($done !== null) {
        $body .= tpl_notice_good('<strong>Resume stored.</strong> Keep this reference.');
        $body .= '<p class="refbox">' . h($done['ref_code']) . '</p>';
        $body .= '<p class="muted">Check the step with this reference or the same email on the <a href="' . h(url('/track')) . '">status page</a>.</p>';
    } else {
        $body .= tpl_error_summary($errors);
        $body .= '<form class="card" method="post" enctype="multipart/form-data" novalidate>';
        $body .= '<input type="hidden" name="csrf" value="' . h(csrf()) . '">';
        $body .= '<input type="text" name="companyUrl" class="hp" tabindex="-1" autocomplete="off" aria-hidden="true">';
        $body .= tpl_field('name', 'Name', 'text', field('name', 120), ['required' => true, 'autocomplete' => 'name'], '', $errors['name'] ?? '');
        $body .= tpl_field('mobile', 'Mobile', 'tel', field('mobile', 20), ['required' => true, 'autocomplete' => 'tel'], '', $errors['mobile'] ?? '');
        $body .= tpl_field('email', 'Email', 'email', field('email', 190), ['required' => true, 'autocomplete' => 'email'], '', $errors['email'] ?? '');
        $body .= '<details class="more"' . ($showOptional ? ' open' : '') . '><summary>Add a few details (optional)</summary><div class="grid-2">';
        $body .= tpl_field('current_location', 'Current location', 'text', field('current_location', 120));
        $body .= tpl_field('experience', 'Experience', 'text', field('experience', 80), ['placeholder' => 'e.g. 4 years']);
        $body .= tpl_field('designation', 'Current role', 'text', field('designation', 120));
        $body .= tpl_field('preferred_location', 'Preferred city', 'text', field('preferred_location', 120));
        $body .= tpl_select('preferred_industry', 'Preferred industry', array_column_keyed($industries, 'slug', 'name'), field('preferred_industry', 180));
        $body .= tpl_select('preferred_function', 'Preferred function', array_column_keyed($functions, 'slug', 'name'), field('preferred_function', 180));
        $body .= tpl_field('expected_salary', 'Expected salary', 'text', field('expected_salary', 80));
        $body .= tpl_field('notice_period', 'Notice period', 'text', field('notice_period', 80));
        $body .= '</div></details>';
        $body .= tpl_field('resume', 'Resume', 'file', '', ['accept' => '.pdf,.doc,.docx', 'required' => true], 'PDF, DOC or DOCX, up to 1.5 MB.', $errors['resume'] ?? '');
        $body .= '<button class="btn btn-navy" type="submit">Submit resume</button>';
        $body .= '</form>';
    }
    $body .= '</div></section>';
    Evolve\Core\View::layout('Submit your resume | Evolve Hire', $body, ['canonical' => url('/resume')]);
}

function page_track(): void
{
    Evolve\Core\Response::noindex();
    $errors = [];
    $apps = [];
    $cvs = [];
    $looked = false;
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        try {
            check_csrf();
            Evolve\Core\Http::rateLimit('track', 15, 600);
            $ref = strtoupper(field('ref', 16));
            $email = strtolower(field('email', 190));
            if ($ref === '' && $email === '') {
                throw new Evolve\Core\ValidationError('Enter a reference or an email.');
            }
            if ($ref !== '') {
                $app = Evolve\Repository\ApplicationRepo::byRef($ref);
                $apps = $app ? [$app] : [];
                $cv = CandidateRepo::byRef($ref);
                $cvs = $cv ? [$cv] : [];
            } else {
                $email = require_email($email);
                $apps = Evolve\Repository\ApplicationRepo::byEmail($email);
                $cvs = CandidateRepo::byEmailLimit($email);
            }
            $looked = true;
        } catch (Evolve\Core\ValidationError $e) {
            $errors['form'] = $e->getMessage();
        } catch (Throwable $e) {
            log_error($e);
            $errors['form'] = 'Could not check that status.';
        }
    }

    $body = '<section class="section"><div class="wrap narrow">';
    $body .= '<h1>Check status</h1>';
    $body .= '<p class="lede-ink">Use the reference from the confirmation, or the email you applied with.</p>';
    $body .= tpl_error_summary($errors);
    $body .= '<form class="card grid-2" method="post" novalidate>';
    $body .= '<input type="hidden" name="csrf" value="' . h(csrf()) . '">';
    $body .= tpl_field('ref', 'Reference', 'text', field('ref', 16), ['placeholder' => 'EH…', 'autocomplete' => 'off']);
    $body .= tpl_field('email', 'Or email', 'email', field('email', 190), ['autocomplete' => 'email']);
    $body .= '<button class="btn btn-navy" type="submit">Show the step</button></form>';
    if ($looked && $apps === [] && $cvs === []) {
        $body .= '<p class="notice bad" role="status">Nothing is on file for that reference or email.</p>';
    }
    foreach ($apps as $app) {
        $body .= '<article class="card" aria-label="Application status">';
        $body .= '<p class="muted">' . h($app['job_title'] ?: 'General resume') . ' · ' . h($app['ref_code']) . '</p>';
        $body .= '<h2>' . h(status_title((string) $app['status'])) . '</h2>';
        $body .= '<p>' . h(status_note((string) $app['status'])) . '</p>';
        $body .= tpl_steps((string) $app['status']);
        $body .= '</article>';
    }
    foreach ($cvs as $cv) {
        $body .= '<article class="card" aria-label="Resume status">';
        $body .= '<p class="muted">Resume on file · ' . h($cv['ref_code']) . '</p>';
        $body .= '<h2>' . h(status_title((string) $cv['status'])) . '</h2>';
        $body .= '<p>' . h(status_note((string) $cv['status'])) . '</p>';
        $body .= '</article>';
    }
    $body .= '<h2>What the steps mean</h2><div class="grid-3">';
    foreach ([['new', 'Submitted', 'The file is in. Nobody has moved it yet.'], ['screening', 'In review', 'Someone is reading it against the brief.'], ['shortlisted', 'Shortlisted', 'You are in the smaller group.'], ['interview', 'Interview', 'Interview is the current step.'], ['selected', 'Hired', 'This one is hired.'], ['rejected', 'Not selected', 'This one is not moving ahead.']] as $s) {
        $body .= '<article class="card"><h3>' . h($s[1]) . '</h3><p class="muted">' . h($s[2]) . '</p></article>';
    }
    $body .= '</div></div></section>';
    Evolve\Core\View::layout('Application status | Evolve Hire', $body, ['canonical' => url('/track'), 'robots' => 'noindex,follow']);
}

function page_job_alerts(): void
{
    $errors = [];
    $done = false;
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        try {
            check_csrf();
            AlertService::subscribe($_POST);
            $done = true;
        } catch (Evolve\Core\ValidationError $e) {
            $errors['form'] = $e->getMessage();
        } catch (Throwable $e) {
            log_error($e);
            $errors['form'] = 'Could not save the preferences.';
        }
    }

    // Unsubscribe via signed link: /job-alerts?unsubscribe=token
    $unsub = field_get('unsubscribe', 64);
    $unsubDone = false;
    if ($unsub !== '') {
        if (AlertRepo::removeByToken($unsub)) {
            $unsubDone = true;
        }
    }

    $industries = ContentRepo::published('industries');
    $functions = ContentRepo::published('functions');

    $body = '<section class="section"><div class="wrap narrow">';
    $body .= '<h1>Job alerts</h1>';
    $body .= '<p class="lede-ink">Tell the desk what fits. When a matching role is published, you hear about it. Unsubscribe any time with the link in the mail.</p>';
    if ($unsubDone) {
        $body .= tpl_notice_good('<strong>You are off the list.</strong> No further alerts will be sent to that address.');
    }
    if ($done) {
        $body .= tpl_notice_good('<strong>Your preferences are saved.</strong>');
    }
    if (!$done) {
        $body .= tpl_error_summary($errors);
        $body .= '<form class="card grid-2" method="post" novalidate>';
        $body .= '<input type="hidden" name="csrf" value="' . h(csrf()) . '">';
        $body .= '<input type="text" name="companyUrl" class="hp" tabindex="-1" autocomplete="off" aria-hidden="true">';
        $body .= tpl_field('email', 'Email', 'email', field('email', 190), ['required' => true, 'autocomplete' => 'email'], '', $errors['email'] ?? '');
        $body .= tpl_select('industry', 'Industry', array_column_keyed($industries, 'slug', 'name'), field('industry', 180));
        $body .= tpl_select('function', 'Function', array_column_keyed($functions, 'slug', 'name'), field('function', 180));
        $body .= tpl_field('location', 'Location', 'text', field('location', 120), ['placeholder' => 'e.g. Nagpur']);
        $body .= tpl_select('experience', 'Experience', array_combine(experience_bands(), experience_bands()), field('experience', 80));
        $body .= tpl_select('work_mode', 'Work mode', work_modes(), field('work_mode', 40));
        $body .= tpl_select('employment_type', 'Employment type', employment_types(), field('employment_type', 40));
        $body .= '<button class="btn btn-navy" type="submit">Save preferences</button></form>';
    }
    $body .= '</div></section>';
    Evolve\Core\View::layout('Job alerts | Evolve Hire', $body, ['canonical' => url('/job-alerts')]);
}

function page_hire(): void
{
    $errors = [];
    $done = false;
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        try {
            check_csrf();
            IntakeService::submitBrief($_POST, $_FILES['jd'] ?? []);
            $done = true;
        } catch (Evolve\Core\ValidationError $e) {
            $errors['form'] = $e->getMessage();
        } catch (Throwable $e) {
            log_error($e);
            $errors['form'] = 'Could not save the brief.';
        }
    }
    $industries = ContentRepo::published('industries');

    $body = '<section class="section"><div class="wrap narrow">';
    $body .= '<h1>For companies</h1>';
    $body .= '<p class="lede-ink">We recruit for the role you send. We are not a staffing contractor. The brief is saved on the desk first.</p>';
    if ($done) {
        $body .= tpl_notice_good('<strong>The requirement is stored.</strong> Someone from Evolve Hire will read it from the desk.');
    } else {
        $body .= tpl_error_summary($errors);
        $body .= '<form class="card grid-2" method="post" enctype="multipart/form-data" novalidate>';
        $body .= '<input type="hidden" name="csrf" value="' . h(csrf()) . '">';
        $body .= '<input type="text" name="companyUrl" class="hp" tabindex="-1" autocomplete="off" aria-hidden="true">';
        $body .= tpl_field('company', 'Company name', 'text', field('company', 180), ['required' => true, 'autocomplete' => 'organization'], '', $errors['company'] ?? '');
        $body .= tpl_field('contact', 'Contact person', 'text', field('contact', 120), ['required' => true, 'autocomplete' => 'name'], '', $errors['contact'] ?? '');
        $body .= tpl_field('email', 'Business email', 'email', field('email', 190), ['required' => true, 'autocomplete' => 'email'], '', $errors['email'] ?? '');
        $body .= tpl_field('phone', 'Phone', 'tel', field('phone', 20), ['required' => true, 'autocomplete' => 'tel'], '', $errors['phone'] ?? '');
        $body .= tpl_select('industry', 'Industry', array_column_keyed($industries, 'slug', 'name'), field('industry', 180));
        $body .= tpl_field('role_title', 'Position / role', 'text', field('role_title', 180), ['required' => true], '', $errors['role_title'] ?? '');
        $body .= tpl_field('openings', 'Number of openings', 'number', field('openings', 6) ?: '1', ['min' => 1, 'max' => 5000, 'value' => 1]);
        $body .= tpl_field('location', 'Hiring location', 'text', field('location', 120));
        $body .= tpl_field('experience', 'Experience required', 'text', field('experience', 80), ['placeholder' => 'e.g. 2-4 years']);
        $body .= tpl_field('salary', 'Salary / CTC', 'text', field('salary', 80), ['placeholder' => 'e.g. 4-6 LPA']);
        $body .= tpl_select('employment_type', 'Employment type', employment_types(), field('employment_type', 40));
        $body .= tpl_select('work_mode', 'Work mode', work_modes(), field('work_mode', 40));
        $body .= '<div style="grid-column:1/-1">' . tpl_field('jd', 'Job description (optional)', 'file', '', ['accept' => '.pdf,.doc,.docx'], 'PDF, DOC or DOCX, up to 1.5 MB.') . '</div>';
        $body .= '<div style="grid-column:1/-1">' . tpl_field('detail', 'Additional requirements', 'textarea', field('detail', 1000)) . '</div>';
        $body .= '<button class="btn btn-navy" type="submit">Send the brief</button></form>';
    }
    $body .= '</div></section>';
    Evolve\Core\View::layout('For companies | Evolve Hire', $body, ['canonical' => url('/hire')]);
}

function page_contact(): void
{
    $errors = [];
    $done = false;
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        try {
            check_csrf();
            IntakeService::submitContact($_POST);
            $done = true;
        } catch (Evolve\Core\ValidationError $e) {
            $errors['form'] = $e->getMessage();
        } catch (Throwable $e) {
            log_error($e);
            $errors['form'] = 'Could not save the message.';
        }
    }
    $f = firm();

    $body = '<section class="section"><div class="wrap narrow">';
    $body .= '<h1>Contact Evolve Hire</h1>';
    $body .= '<p class="lede-ink">' . h($f['address']) . '</p>';
    $body .= '<div class="grid-3">';
    $body .= '<a class="card card-lift" href="tel:' . h($f['tel']) . '"><h2>Call</h2><p class="muted">' . h($f['phone']) . '</p></a>';
    $body .= '<a class="card card-lift" href="mailto:' . h($f['email']) . '"><h2>Email</h2><p class="muted">' . h($f['email']) . '</p></a>';
    $body .= '<a class="card card-lift" href="' . h($f['whatsapp']) . '"><h2>WhatsApp</h2><p class="muted">Message the desk</p></a>';
    $body .= '</div>';
    if ($done) {
        $body .= tpl_notice_good('<strong>Message stored.</strong> It is on the desk even if email is slow.');
    } else {
        $body .= tpl_error_summary($errors);
        $body .= '<form class="card grid-2" method="post" novalidate>';
        $body .= '<input type="hidden" name="csrf" value="' . h(csrf()) . '">';
        $body .= '<input type="text" name="companyUrl" class="hp" tabindex="-1" autocomplete="off" aria-hidden="true">';
        $body .= tpl_field('name', 'Name', 'text', field('name', 120), ['required' => true, 'autocomplete' => 'name'], '', $errors['name'] ?? '');
        $body .= tpl_field('email', 'Email', 'email', field('email', 190), ['required' => true, 'autocomplete' => 'email'], '', $errors['email'] ?? '');
        $body .= tpl_field('phone', 'Phone', 'tel', field('phone', 20), ['autocomplete' => 'tel'], '', $errors['phone'] ?? '');
        $body .= '<div style="grid-column:1/-1">' . tpl_field('message', 'Message', 'textarea', field('message', 2000), ['required' => true, 'minlength' => 8], '', $errors['message'] ?? '') . '</div>';
        $body .= '<button class="btn btn-navy" type="submit">Send the message</button></form>';
    }
    $body .= '</div></section>';
    Evolve\Core\View::layout('Contact Evolve Hire | Nagpur', $body, ['canonical' => url('/contact')]);
}
