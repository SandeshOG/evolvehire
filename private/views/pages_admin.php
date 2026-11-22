<?php
declare(strict_types=1);

use Evolve\Core\Http;
use Evolve\Repository\AlertRepo;
use Evolve\Repository\ApplicationRepo;
use Evolve\Repository\CandidateRepo;
use Evolve\Repository\ContentRepo;
use Evolve\Repository\JobRepo;
use Evolve\Repository\LeadRepo;
use Evolve\Repository\SecurityRepo;
use Evolve\Repository\UserRepo;
use Evolve\Service\AlertService;
use Evolve\Service\AuthService;
use Evolve\Service\JobService;

/** Owner admin: overview, jobs CRUD, pipeline, candidates, leads, alerts, catalogue, staff, settings, audit. */

function admin_sections(): array
{
    return [
        'overview' => 'Overview',
        'jobs' => 'Jobs',
        'applications' => 'Applications',
        'candidates' => 'Candidates',
        'leads' => 'Leads',
        'alerts' => 'Alerts',
        'catalogue' => 'Catalogue',
        'staff' => 'Staff',
        'settings' => 'Settings',
        'audit' => 'Audit log',
    ];
}

function page_admin(): void
{
    Evolve\Core\Response::noindex();
    $u = AuthService::requireRole('owner');
    $section = array_key_exists($_GET['section'] ?? '', admin_sections()) ? (string) $_GET['section'] : 'overview';
    $error = '';
    $ok = '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        try {
            check_csrf();
            $action = (string) ($_POST['action'] ?? '');

            if ($action === 'job_save') {
                $id = (int) ($_POST['id'] ?? 0);
                $newId = JobService::save($_POST, $id);
                $ok = $id > 0 ? 'Role updated.' : 'Role saved.';
                $_GET['section'] = 'jobs';
                $section = 'jobs';
            } elseif ($action === 'job_delete') {
                JobService::delete((int) ($_POST['id'] ?? 0));
                $ok = 'Role deleted.';
                $section = 'jobs';
            } elseif ($action === 'job_edit') {
                redirect('/admin?section=jobs&id=' . (int) ($_POST['id'] ?? 0));
            } elseif ($action === 'app_status') {
                ApplicationRepo::setStatus((int) ($_POST['id'] ?? 0), (string) ($_POST['status'] ?? ''), field('note', 500), $u['email']);
                $ok = 'Step saved.';
                $section = 'applications';
            } elseif ($action === 'cv_status') {
                CandidateRepo::setStatus((int) ($_POST['id'] ?? 0), (string) ($_POST['status'] ?? ''), $u['email']);
                $ok = 'CV step saved.';
                $section = 'candidates';
            } elseif ($action === 'collection_save') {
                $table = (string) ($_POST['table'] ?? '');
                ContentRepo::save($table, $_POST);
                Http::audit('catalogue_save', $table);
                $ok = 'Saved.';
                $section = 'catalogue';
            } elseif ($action === 'collection_delete') {
                $table = (string) ($_POST['table'] ?? '');
                ContentRepo::delete($table, (int) ($_POST['id'] ?? 0));
                Http::audit('catalogue_delete', $table);
                $ok = 'Deleted.';
                $section = 'catalogue';
            } elseif ($action === 'setting_save') {
                ContentRepo::saveSetting(field('key', 40), (string) ($_POST['value'] ?? ''));
                $ok = 'Setting saved.';
                $section = 'settings';
            } elseif ($action === 'staff_save') {
                $email = require_email(field('email', 190));
                $name = field('staff_name', 120);
                $password = (string) ($_POST['password'] ?? '');
                if ($name === '' || strlen($password) < 12) {
                    throw new Evolve\Core\ValidationError('Name and a password of 12 or more characters are required.');
                }
                $existing = UserRepo::byEmail($email);
                if ($existing && $existing['role'] === 'owner') {
                    throw new Evolve\Core\ValidationError('That email is the owner. Use a different staff email.');
                }
                UserRepo::upsertStaff($email, $name, password_hash($password, PASSWORD_DEFAULT));
                Http::audit('staff_save', $email);
                $ok = 'Staff login saved.';
                $section = 'staff';
            } elseif ($action === 'staff_remove') {
                $id = (int) ($_POST['id'] ?? 0);
                $target = UserRepo::byId($id);
                if ($target && $target['role'] === 'owner') {
                    if (UserRepo::countOwners() <= 1) {
                        throw new Evolve\Core\ValidationError('Keep at least one owner.');
                    }
                }
                UserRepo::remove($id);
                Http::audit('staff_remove', (string) ($target['email'] ?? (string) $id));
                $ok = 'Removed.';
                $section = 'staff';
            } elseif ($action === 'alert_digest') {
                $sent = AlertService::matchAndNotify();
                $ok = $sent > 0 ? $sent . ' alert mails queued.' : 'No new matches today.';
                $section = 'alerts';
            } else {
                throw new Evolve\Core\ValidationError('Unknown action.');
            }
        } catch (Evolve\Core\ValidationError $e) {
            $error = $e->getMessage();
        } catch (Throwable $e) {
            log_error($e);
            $error = 'Could not save that.';
        }
    }

    $body = '<section class="section"><div class="wrap">';
    $body .= '<div class="deskbar"><div><p class="muted">' . h($u['email']) . ' · owner</p><h1>Owner</h1></div>';
    $body .= '<div class="desk-actions"><a class="btn btn-line" href="' . h(url('/desk')) . '">Desk</a>';
    $body .= '<form method="post" action="' . h(url('/logout')) . '"><input type="hidden" name="csrf" value="' . h(csrf()) . '"><button class="btn btn-navy" type="submit">Sign out</button></form></div></div>';

    $nav = '<nav class="tabs" aria-label="Admin sections"><ul>';
    foreach (admin_sections() as $key => $label) {
        $sel = $key === $section ? ' aria-current="page"' : '';
        $cls = $key === $section ? ' class="on"' : '';
        $nav .= '<li><a' . $cls . ' href="' . h(url('/admin?section=' . $key)) . '"' . $sel . '>' . h($label) . '</a></li>';
    }
    $nav .= '</ul></nav>';
    $body .= $nav;

    if ($error !== '') {
        $body .= tpl_notice_bad($error);
    }
    if ($ok !== '') {
        $body .= tpl_notice_good('<strong>' . h($ok) . '</strong>');
    }

    if ($section === 'overview') {
        $counts = [
            'Applications' => ApplicationRepo::countAll(),
            'CVs on file' => CandidateRepo::countAll(),
            'Hiring briefs' => LeadRepo::countAll(),
            'Alerts' => AlertRepo::countAll(),
            'Roles' => (int) db()->query('SELECT COUNT(*) FROM jobs')->fetchColumn(),
            'Staff' => UserRepo::countAll(),
        ];
        $pipeline = ApplicationRepo::pipelineCounts();
        $notices = SecurityRepo::notifications(8);
        $body .= '<p class="lede-ink">Counts come from the database. Zero means zero.</p>';
        $body .= '<div class="grid-3 statgrid">';
        foreach ($counts as $label => $n) {
            $body .= '<article class="card stat"><h2>' . h($label) . '</h2><p class="big">' . (int) $n . '</p></article>';
        }
        $body .= '</div><h2>Pipeline</h2><div class="pipemap">';
        foreach (['new', 'screening', 'shortlisted', 'interview', 'selected', 'joined', 'rejected'] as $st) {
            $body .= '<a class="chip" href="' . h(url('/admin?section=applications&status=' . $st)) . '">' . h(status_title($st)) . ' <span class="count">' . (int) ($pipeline[$st] ?? 0) . '</span></a>';
        }
        $body .= '</div><h2>Notices</h2><ul class="msglist">';
        foreach ($notices as $n) {
            $body .= '<li><span class="muted">' . h(substr((string) $n['created_at'], 0, 16)) . ' · ' . h($n['kind']) . '</span><br>' . h($n['subject']) . '</li>';
        }
        if ($notices === []) {
            $body .= '<li class="muted">Nothing yet.</li>';
        }
        $body .= '</ul>';
    } elseif ($section === 'jobs') {
        $editId = (int) ($_GET['id'] ?? 0);
        $job = $editId > 0 ? JobRepo::byId($editId) : null;
        $industries = ContentRepo::published('industries');
        $functions = ContentRepo::published('functions');
        $body .= '<h2>' . ($job ? 'Edit role' : 'Add a role') . '</h2>';
        $body .= admin_job_form($job, $industries, $functions);
        $body .= '<h2>On the board</h2><div class="table-wrap"><table><tr><th>Role</th><th>Status</th><th>Flags</th><th></th></tr>';
        foreach (JobRepo::adminList() as $row) {
            $body .= '<tr><td>' . h($row['title']) . '<br><span class="muted">' . h($row['company']) . ' · ' . h($row['slug']) . '</span></td>';
            $body .= '<td>' . h($row['status']) . '</td>';
            $body .= '<td>' . h(trim(((int) $row['featured'] === 1 ? 'Featured ' : '') . ((int) $row['urgent'] === 1 ? 'Urgent ' : '') . ((int) $row['internal'] === 1 ? 'Internal' : ''))) . '</td>';
            $body .= '<td><div class="rowbtns">';
            $body .= '<form method="post"><input type="hidden" name="csrf" value="' . h(csrf()) . '"><input type="hidden" name="action" value="job_edit"><input type="hidden" name="id" value="' . (int) $row['id'] . '"><button class="btn btn-line btn-sm" type="submit">Edit</button></form>';
            $body .= '<form method="post"><input type="hidden" name="csrf" value="' . h(csrf()) . '"><input type="hidden" name="action" value="job_delete"><input type="hidden" name="id" value="' . (int) $row['id'] . '"><button class="btn btn-line btn-sm danger" type="submit">Delete</button></form>';
            $body .= '</div></td></tr>';
        }
        $body .= '</table></div>';
    } elseif ($section === 'applications') {
        $filter = in_array($_GET['status'] ?? '', ['new', 'screening', 'shortlisted', 'interview', 'selected', 'rejected', 'joined', ''], true) ? (string) ($_GET['status'] ?? '') : '';
        $apps = ApplicationRepo::deskList($filter);
        $body .= '<div class="pipemap">';
        foreach (['', 'new', 'screening', 'shortlisted', 'interview', 'selected', 'joined', 'rejected'] as $st) {
            $body .= '<a class="chip' . ($filter === $st ? ' on' : '') . '" href="' . h(url('/admin?section=applications' . ($st !== '' ? '&status=' . $st : ''))) . '">' . h($st === '' ? 'All' : status_title($st)) . '</a>';
        }
        $body .= '</div><div class="table-wrap"><table><tr><th>Who</th><th>Role</th><th>Step</th><th></th></tr>';
        foreach ($apps as $app) {
            $body .= '<tr><td>' . h($app['name']) . '<br><span class="muted">' . h($app['email']) . ' · ' . h($app['ref_code']) . '</span></td>';
            $body .= '<td>' . h($app['job_title'] ?: 'General resume') . '</td>';
            $body .= '<td><form method="post" class="status-form"><input type="hidden" name="csrf" value="' . h(csrf()) . '">';
            $body .= '<input type="hidden" name="action" value="app_status"><input type="hidden" name="id" value="' . (int) $app['id'] . '">';
            $body .= '<select name="status" aria-label="Step for ' . h($app['name']) . '">';
            foreach (['new', 'screening', 'shortlisted', 'interview', 'selected', 'joined', 'rejected'] as $st) {
                $sel = $st === $app['status'] ? ' selected' : '';
                $body .= '<option value="' . h($st) . '"' . $sel . '>' . h(status_title($st)) . '</option>';
            }
            $body .= '</select><input name="note" placeholder="Note" maxlength="500"><button class="btn btn-gold btn-sm" type="submit">Save</button></form></td>';
            $body .= '<td>';
            if ((string) $app['resume_path'] !== '') {
                $body .= '<a class="btn btn-line btn-sm" href="' . h(url('/file/resume/' . (int) $app['id'])) . '">Resume</a>';
            }
            $body .= '</td></tr>';
        }
        if ($apps === []) {
            $body .= '<tr><td colspan="4" class="muted">No applications in this view.</td></tr>';
        }
        $body .= '</table></div>';
    } elseif ($section === 'candidates') {
        $search = field_get('q', 120);
        $cvs = CandidateRepo::deskList($search);
        $body .= '<form class="search" method="get" role="search" style="max-width:32rem">';
        $body .= '<label class="sr" for="adq">Search candidates</label><input id="adq" name="q" value="' . h($search) . '" placeholder="Name, email, city, role">';
        $body .= '<input type="hidden" name="section" value="candidates"><button class="btn btn-navy" type="submit">Search</button></form>';
        $body .= '<div class="table-wrap"><table><tr><th>Who</th><th>Step</th><th></th></tr>';
        foreach ($cvs as $cv) {
            $body .= '<tr><td>' . h($cv['name']) . '<br><span class="muted">' . h($cv['email']) . ' · ' . h($cv['ref_code']) . '</span></td>';
            $body .= '<td><form method="post" class="status-form"><input type="hidden" name="csrf" value="' . h(csrf()) . '">';
            $body .= '<input type="hidden" name="action" value="cv_status"><input type="hidden" name="id" value="' . (int) $cv['id'] . '">';
            $body .= '<select name="status" aria-label="Step for ' . h($cv['name']) . '">';
            foreach (['new', 'reviewing', 'shortlisted', 'placed', 'archived'] as $st) {
                $sel = $st === $cv['status'] ? ' selected' : '';
                $body .= '<option value="' . h($st) . '"' . $sel . '>' . h(status_title($st)) . '</option>';
            }
            $body .= '</select><button class="btn btn-gold btn-sm" type="submit">Save</button></form></td>';
            $body .= '<td>';
            if ((string) ($cv['resume_path'] ?? '') !== '') {
                $body .= '<a class="btn btn-line btn-sm" href="' . h(url('/file/cv/' . (int) $cv['id'])) . '">Resume</a>';
            }
            $body .= '</td></tr>';
        }
        if ($cvs === []) {
            $body .= '<tr><td colspan="3" class="muted">No CVs in this view.</td></tr>';
        }
        $body .= '</table></div>';
    } elseif ($section === 'leads') {
        $leads = LeadRepo::deskList();
        $messages = Evolve\Service\IntakeService::contactMessages();
        $body .= '<h2>Hiring briefs</h2><div class="table-wrap"><table><tr><th>Company</th><th>Role</th><th>Contact</th><th></th></tr>';
        foreach ($leads as $lead) {
            $body .= '<tr><td>' . h($lead['company']) . '<br><span class="muted">' . h($lead['industry']) . ' · ' . (int) $lead['openings'] . '</span></td>';
            $body .= '<td>' . h($lead['role_title']) . '<br><span class="muted">' . h(trim(($lead['location'] ?? '') . ' · ' . ($lead['salary'] ?? ''), ' ·')) . '</span></td>';
            $body .= '<td>' . h($lead['contact']) . '<br><span class="muted">' . h($lead['email']) . '</span></td>';
            $body .= '<td>' . ((string) ($lead['jd_path'] ?? '') !== '' ? '<a class="btn btn-line btn-sm" href="' . h(url('/file/jd/' . (int) $lead['id'])) . '">JD</a>' : '') . '</td></tr>';
        }
        if ($leads === []) {
            $body .= '<tr><td colspan="4" class="muted">No briefs yet.</td></tr>';
        }
        $body .= '</table></div>';
        $body .= '<h2>Website Messages</h2><ul class="msglist">';
        foreach ($messages as $m) {
            $body .= '<li><strong>' . h($m['name']) . '</strong> <span class="muted">' . h($m['email']) . '</span><br>' . h($m['message']) . '</li>';
        }
        if ($messages === []) {
            $body .= '<li class="muted">No messages yet.</li>';
        }
        $body .= '</ul>';
    } elseif ($section === 'alerts') {
        $alerts = AlertRepo::all();
        $body .= '<p class="lede-ink">Alert subscriptions. The digest runs on cron (or the button below): new published roles are matched against these filters and queued as mail.</p>';
        $body .= '<form method="post"><input type="hidden" name="csrf" value="' . h(csrf()) . '"><input type="hidden" name="action" value="alert_digest">';
        $body .= '<button class="btn btn-navy" type="submit">Run digest now</button></form>';
        $body .= '<div class="table-wrap"><table><tr><th>Email</th><th>Preferences</th><th></th></tr>';
        foreach ($alerts as $a) {
            $body .= '<tr><td>' . h($a['email']) . '</td>';
            $body .= '<td>' . h(trim(($a['industry'] ?? '') . ' · ' . ($a['function_name'] ?? '') . ' · ' . ($a['location'] ?? '') . ' · ' . ($a['experience'] ?? '') . ' · ' . ($a['work_mode'] ?? '') . ' · ' . ($a['employment_type'] ?? ''), ' ·')) . '</td>';
            $body .= '<td><a class="muted" href="' . h(url('/job-alerts?unsubscribe=' . $a['unsubscribe_token'])) . '">unsubscribe link</a></td></tr>';
        }
        if ($alerts === []) {
            $body .= '<tr><td colspan="3" class="muted">No subscriptions yet.</td></tr>';
        }
        $body .= '</table></div>';
    } elseif ($section === 'catalogue') {
        $tables = ['industries' => 'Industries', 'functions' => 'Functions', 'services' => 'Services', 'insights' => 'Insights', 'case_studies' => 'Case studies', 'companies' => 'Companies'];
        $table = array_key_exists($_GET['table'] ?? '', $tables) ? (string) $_GET['table'] : 'industries';
        $rows = ContentRepo::adminList($table);
        $body .= '<div class="pipemap">';
        foreach ($tables as $t => $label) {
            $body .= '<a class="chip' . ($table === $t ? ' on' : '') . '" href="' . h(url('/admin?section=catalogue&table=' . $t)) . '">' . h($label) . '</a>';
        }
        $body .= '</div>';
        $body .= admin_collection_form($table);
        $body .= '<div class="table-wrap"><table><tr><th>Name / title</th><th>Slug</th><th>Published</th><th></th></tr>';
        foreach ($rows as $row) {
            $title = (string) ($row['title'] ?? $row['name']);
            $body .= '<tr><td>' . h($title) . '</td><td>' . h($row['slug']) . '</td>';
            $body .= '<td>' . ((int) $row['published'] === 1 ? 'Yes' : 'No') . '</td>';
            $body .= '<td><form method="post"><input type="hidden" name="csrf" value="' . h(csrf()) . '">';
            $body .= '<input type="hidden" name="action" value="collection_delete"><input type="hidden" name="table" value="' . h($table) . '">';
            $body .= '<input type="hidden" name="id" value="' . (int) $row['id'] . '"><button class="btn btn-line btn-sm danger" type="submit">Delete</button></form></td></tr>';
        }
        $body .= '</table></div>';
    } elseif ($section === 'staff') {
        $body .= '<h2>Add or reset staff</h2>';
        $body .= '<form class="card grid-2" method="post" novalidate>';
        $body .= '<input type="hidden" name="csrf" value="' . h(csrf()) . '"><input type="hidden" name="action" value="staff_save">';
        $body .= tpl_field('staff_name', 'Name', 'text', field('staff_name', 120), ['required' => true]);
        $body .= tpl_field('email', 'Email', 'email', field('email', 190), ['required' => true, 'autocomplete' => 'off']);
        $body .= tpl_field('password', 'Password', 'password', '', ['required' => true, 'minlength' => 12, 'autocomplete' => 'new-password'], 'At least 12 characters. The staff member changes it at first login.');
        $body .= '<button class="btn btn-navy" type="submit">Save staff</button></form>';
        $body .= '<h2>Seats</h2><div class="table-wrap"><table><tr><th>Who</th><th>Role</th><th></th></tr>';
        foreach (UserRepo::all() as $usr) {
            $body .= '<tr><td>' . h($usr['name']) . '<br><span class="muted">' . h($usr['email']) . '</span></td>';
            $body .= '<td>' . h($usr['role']) . ((int) $usr['must_change_password'] === 1 ? ' · must change password' : '') . '</td>';
            $body .= '<td>';
            if ((int) $usr['id'] !== (int) $u['id']) {
                $body .= '<form method="post"><input type="hidden" name="csrf" value="' . h(csrf()) . '">';
                $body .= '<input type="hidden" name="action" value="staff_remove"><input type="hidden" name="id" value="' . (int) $usr['id'] . '">';
                $body .= '<button class="btn btn-line btn-sm danger" type="submit">Remove</button></form>';
            }
            $body .= '</td></tr>';
        }
        $body .= '</table></div>';
    } elseif ($section === 'settings') {
        $settings = db()->query('SELECT skey, svalue FROM settings ORDER BY skey')->fetchAll();
        $known = ['notify_email', 'email', 'phone_display', 'address'];
        $body .= '<p class="lede-ink">Owner-set values that override the defaults on the public site. Keys use lower-case letters, numbers and underscores.</p>';
        $body .= '<form class="card grid-2" method="post" novalidate>';
        $body .= '<input type="hidden" name="csrf" value="' . h(csrf()) . '"><input type="hidden" name="action" value="setting_save">';
        $body .= tpl_field('key', 'Key', 'text', field('key', 40), ['required' => true, 'pattern' => '[a-z0-9_]{2,40}'], 'e.g. notify_email');
        $body .= tpl_field('value', 'Value', 'text', (string) ($_POST['value'] ?? ''));
        $body .= '<button class="btn btn-navy" type="submit">Save setting</button></form>';
        $body .= '<div class="table-wrap"><table><tr><th>Key</th><th>Value</th></tr>';
        foreach ($settings as $s) {
            $body .= '<tr><td>' . h($s['skey']) . '</td><td>' . h(mb_substr((string) $s['svalue'], 0, 80)) . '</td></tr>';
        }
        if ($settings === []) {
            $body .= '<tr><td colspan="2" class="muted">No settings yet. Useful keys: notify_email, email, phone_display, address, city, website, proprietor, gstin, udyam.</td></tr>';
        }
        $body .= '</table></div>';
    } elseif ($section === 'audit') {
        $rows = SecurityRepo::auditTrail(100);
        $body .= '<div class="table-wrap"><table><tr><th>When</th><th>Event</th><th>Actor</th></tr>';
        foreach ($rows as $r) {
            $body .= '<tr><td>' . h(substr((string) $r['created_at'], 0, 19)) . '</td><td>' . h($r['event']) . '</td><td>' . h($r['actor']) . '</td></tr>';
        }
        if ($rows === []) {
            $body .= '<tr><td colspan="3" class="muted">Nothing recorded yet.</td></tr>';
        }
        $body .= '</table></div>';
    }

    $body .= '</div></section>';
    Evolve\Core\View::layout('Desk | Evolve Hire', $body, ['robots' => 'noindex,nofollow'], true);
}

function admin_job_form(?array $job, array $industries, array $functions): string
{
    $v = fn (string $k, string $d = '') => h((string) ($job[$k] ?? $d));
    $html = '<form class="card grid-2 jobform" method="post" novalidate>';
    $html .= '<input type="hidden" name="csrf" value="' . h(csrf()) . '">';
    $html .= '<input type="hidden" name="action" value="job_save">';
    $html .= '<input type="hidden" name="id" value="' . (int) ($job['id'] ?? 0) . '">';
    $html .= tpl_field('title', 'Title', 'text', $v('title'), ['required' => true]);
    $html .= tpl_field('company', 'Company', 'text', $v('company'), ['required' => true]);
    $html .= tpl_field('slug', 'Slug (web address)', 'text', $v('slug'), [], 'Lower-case letters, numbers and dashes. Leave blank to make it from the title.');
    $html .= tpl_select('industry', 'Industry', array_column_keyed($industries, 'slug', 'name'), (string) ($job['industry'] ?? ''));
    $html .= tpl_select('function', 'Function', array_column_keyed($functions, 'slug', 'name'), (string) ($job['function_name'] ?? ''));
    $html .= tpl_field('location', 'Location', 'text', $v('location'), ['placeholder' => 'e.g. Nagpur']);
    $html .= tpl_field('experience_label', 'Experience label', 'text', $v('experience_label'), ['placeholder' => 'e.g. 2-5 years']);
    $html .= tpl_field('experience_min', 'Experience from (years)', 'number', $v('experience_min'), ['min' => 0, 'max' => 50, 'step' => '0.5']);
    $html .= tpl_field('experience_max', 'Experience to (years)', 'number', $v('experience_max'), ['min' => 0, 'max' => 50, 'step' => '0.5']);
    $html .= tpl_field('salary_label', 'Salary label', 'text', $v('salary_label'), ['placeholder' => 'e.g. 6-8 LPA']);
    $html .= tpl_field('salary_min', 'Salary from (LPA)', 'number', $v('salary_min'), ['min' => 0, 'step' => '0.5']);
    $html .= tpl_field('salary_max', 'Salary to (LPA)', 'number', $v('salary_max'), ['min' => 0, 'step' => '0.5']);
    $html .= tpl_select('employment_type', 'Employment type', employment_types(), (string) ($job['employment_type'] ?? ''));
    $html .= tpl_select('work_mode', 'Work mode', work_modes(), (string) ($job['work_mode'] ?? ''));
    $html .= tpl_field('expires_at', 'Closing date', 'date', substr($v('expires_at'), 0, 10));
    $html .= tpl_select('status', 'Status', ['draft' => 'Draft', 'published' => 'Published', 'closed' => 'Closed'], (string) ($job['status'] ?? 'draft'));
    $checks = '<fieldset class="checks"><legend>Flags</legend>';
    foreach ([['featured', 'Featured on the board', (int) ($job['featured'] ?? 0) === 1], ['urgent', 'Urgent badge', (int) ($job['urgent'] ?? 0) === 1], ['internal', 'Internal (careers page only)', (int) ($job['internal'] ?? 0) === 1]] as [$k, $label, $on]) {
        $checks .= '<label class="check"><input type="checkbox" name="' . $k . '" value="1"' . ($on ? ' checked' : '') . '> ' . h($label) . '</label>';
    }
    $checks .= '</fieldset>';
    $html .= $checks;
    foreach ([['description', 'The role'], ['responsibilities', 'Responsibilities'], ['requirements', 'Requirements'], ['eligibility', 'Eligibility'], ['interview_process', 'Interview process'], ['application_instructions', 'How to apply']] as [$k, $label]) {
        $html .= '<div style="grid-column:1/-1">' . tpl_field($k, $label, 'textarea', (string) ($job[$k] ?? '')) . '</div>';
    }
    $html .= '<button class="btn btn-navy" type="submit">' . ($job ? 'Save role' : 'Add role') . '</button>';
    $html .= '</form>';
    return $html;
}

function admin_collection_form(string $table): string
{
    $fields = match ($table) {
        'industries', 'functions' => ['name' => 'Name', 'slug' => 'Slug', 'summary' => 'Summary', 'sort_order' => 'Sort order'],
        'services' => ['name' => 'Name', 'slug' => 'Slug', 'summary' => 'Summary', 'body' => 'Body', 'sort_order' => 'Sort order'],
        'insights', 'case_studies' => ['title' => 'Title', 'slug' => 'Slug', 'excerpt' => 'Excerpt', 'body' => 'Body', 'industry' => 'Industry'],
        'companies' => ['name' => 'Name', 'slug' => 'Slug', 'summary' => 'Summary', 'website' => 'Website'],
        default => [],
    };
    $html = '<form class="card grid-2" method="post" novalidate>';
    $html .= '<input type="hidden" name="csrf" value="' . h(csrf()) . '">';
    $html .= '<input type="hidden" name="action" value="collection_save">';
    $html .= '<input type="hidden" name="table" value="' . h($table) . '">';
    foreach ($fields as $name => $label) {
        $type = $name === 'body' ? 'textarea' : ($name === 'sort_order' ? 'number' : 'text');
        $html .= tpl_field($name, $label, $type, field($name, $name === 'body' ? 10000 : 300));
    }
    $html .= '<label class="check"><input type="checkbox" name="published" value="1" checked> Published</label>';
    $html .= '<button class="btn btn-navy" type="submit">Add to ' . h($table) . '</button>';
    $html .= '</form>';
    return $html;
}
