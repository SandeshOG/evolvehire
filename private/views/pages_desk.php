<?php
declare(strict_types=1);

use Evolve\Core\Csrf;
use Evolve\Core\Http;
use Evolve\Repository\ApplicationRepo;
use Evolve\Repository\CandidateRepo;
use Evolve\Repository\LeadRepo;
use Evolve\Repository\UserRepo;
use Evolve\Service\AuthService;
use Evolve\Service\UploadService;

/** Staff desk: applications pipeline, CV bank, briefs, jobs, password resets. */

function page_gate(): void
{
    Evolve\Core\Response::noindex();
    $u = user();
    if ($u) {
        redirect($u['role'] === 'owner' ? '/admin' : '/desk');
    }
    $error = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        try {
            check_csrf();
            $u = AuthService::login(field('email', 190), (string) ($_POST['password'] ?? ''));
            redirect($u['role'] === 'owner' ? '/admin' : '/desk');
        } catch (Evolve\Core\ValidationError $e) {
            $error = $e->getMessage();
        } catch (Throwable $e) {
            log_error($e);
            $error = 'Could not sign in.';
        }
    }
    $body = '<section class="section"><div class="wrap narrow"><p class="kicker" style="color:var(--brand)">Desk only</p><h1>Staff and owner</h1>';
    if ($error !== '') {
        $body .= tpl_notice_bad($error);
    }
    $body .= '<form class="card grid" method="post" novalidate>';
    $body .= '<input type="hidden" name="csrf" value="' . h(csrf()) . '">';
    $body .= tpl_field('email', 'Email', 'email', field('email', 190), ['required' => true, 'autocomplete' => 'username']);
    $body .= tpl_field('password', 'Password', 'password', '', ['required' => true, 'autocomplete' => 'current-password']);
    $body .= '<button class="btn btn-navy" type="submit">Enter the desk</button></form>';
    $body .= '<p class="muted"><a href="' . h(url('/forgot')) . '">Forgot the password?</a></p>';
    $body .= '</div></section>';
    Evolve\Core\View::layout('Desk | Evolve Hire', $body, ['robots' => 'noindex,nofollow'], true);
}

function page_logout(): void
{
    check_csrf();
    AuthService::logout();
    redirect('/gate');
}

function page_change_password(): void
{
    Evolve\Core\Response::noindex();
    $u = AuthService::requireRole('owner', 'staff');
    $error = '';
    $ok = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        try {
            check_csrf();
            AuthService::changeOwnPassword((int) $u['id'], (string) ($_POST['current'] ?? ''), (string) ($_POST['new'] ?? ''));
            $ok = 'Password changed.';
        } catch (Evolve\Core\ValidationError $e) {
            $error = $e->getMessage();
        } catch (Throwable $e) {
            log_error($e);
            $error = 'Could not change the password.';
        }
    }
    $body = '<section class="section"><div class="wrap narrow"><h1>Change your password</h1>';
    $body .= '<p class="muted">A staff password was set for you. Set your own before using the desk.</p>';
    if ($error !== '') {
        $body .= tpl_notice_bad($error);
    }
    if ($ok !== '') {
        $body .= tpl_notice_good('<strong>' . h($ok) . '</strong>');
    }
    $body .= '<form class="card grid" method="post" novalidate>';
    $body .= '<input type="hidden" name="csrf" value="' . h(csrf()) . '">';
    $body .= tpl_field('current', 'Current password', 'password', '', ['required' => true, 'autocomplete' => 'current-password']);
    $body .= tpl_field('new', 'New password', 'password', '', ['required' => true, 'minlength' => 12, 'autocomplete' => 'new-password'], 'At least 12 characters.');
    $body .= '<button class="btn btn-navy" type="submit">Save password</button></form></div></section>';
    Evolve\Core\View::layout('Change password | Evolve Hire', $body, ['robots' => 'noindex,nofollow'], true);
}

function desk_tabs(string $active, bool $owner): string
{
    $tabs = [
        'applications' => ['Applications', '/desk'],
        'candidates' => ['CVs', '/desk?tab=candidates'],
        'leads' => ['Hiring requests', '/desk?tab=leads'],
        'jobs' => ['Jobs', '/desk?tab=jobs'],
        'resets' => ['Password resets', '/desk?tab=resets'],
    ];
    if ($owner) {
        $tabs['admin'] = ['Owner tools', '/admin'];
    }
    $html = '<nav class="tabs" aria-label="Desk sections"><ul>';
    foreach ($tabs as $key => [$label, $href]) {
        $sel = $key === $active ? ' aria-current="page"' : '';
        $cls = $key === $active ? ' class="on"' : '';
        $html .= '<li><a' . $cls . ' href="' . h(url($href)) . '"' . $sel . '>' . h($label) . '</a></li>';
    }
    $html .= '</ul></nav>';
    return $html;
}

function page_desk(): void
{
    Evolve\Core\Response::noindex();
    $u = AuthService::requireRole('owner', 'staff');
    $tab = in_array($_GET['tab'] ?? '', ['applications', 'candidates', 'leads', 'jobs', 'resets'], true)
        ? (string) $_GET['tab']
        : 'applications';

    // Status move (POST from the applications tab)
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'status') {
        try {
            check_csrf();
            $id = (int) ($_POST['id'] ?? 0);
            $status = (string) ($_POST['status'] ?? '');
            $note = field('note', 500);
            $allowed = ['new', 'screening', 'shortlisted', 'interview', 'selected', 'rejected', 'joined'];
            if (!in_array($status, $allowed, true)) {
                throw new Evolve\Core\ValidationError('Unknown step.');
            }
            ApplicationRepo::setStatus($id, $status, $note, $u['email']);
            Http::audit('status_' . $status, '#' . $id . ' ' . $u['email']);
            redirect('/desk' . ($tab !== 'applications' ? '?tab=' . $tab : ''));
        } catch (Throwable $e) {
            log_error($e);
        }
    }
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'cv_status') {
        try {
            check_csrf();
            $id = (int) ($_POST['id'] ?? 0);
            $status = (string) ($_POST['status'] ?? '');
            $allowed = ['new', 'reviewing', 'shortlisted', 'placed', 'archived'];
            if (!in_array($status, $allowed, true)) {
                throw new Evolve\Core\ValidationError('Unknown step.');
            }
            CandidateRepo::setStatus($id, $status, $u['email']);
            Http::audit('cv_status_' . $status, '#' . $id . ' ' . $u['email']);
            redirect('/desk?tab=candidates');
        } catch (Throwable $e) {
            log_error($e);
        }
    }

    $body = '<section class="section"><div class="wrap">';
    $body .= '<div class="deskbar"><div><p class="muted">' . h($u['name']) . ' · ' . h($u['role']) . '</p><h1>Desk</h1></div>';
    $body .= '<div class="desk-actions"><a class="btn btn-line" href="' . h(url('/change-password')) . '">Change password</a>';
    $body .= '<form method="post" action="' . h(url('/logout')) . '"><input type="hidden" name="csrf" value="' . h(csrf()) . '"><button class="btn btn-navy" type="submit">Sign out</button></form></div></div>';
    $body .= desk_tabs($tab, $u['role'] === 'owner');

    if ($tab === 'applications') {
        $filter = in_array($_GET['status'] ?? '', ['new', 'screening', 'shortlisted', 'interview', 'selected', 'rejected', 'joined', ''], true) ? (string) ($_GET['status'] ?? '') : '';
        $apps = ApplicationRepo::deskList($filter);
        $counts = ApplicationRepo::pipelineCounts();
        $body .= '<div class="pipemap" aria-label="Pipeline counts">';
        foreach (['new', 'screening', 'shortlisted', 'interview', 'selected', 'joined', 'rejected'] as $st) {
            $body .= '<a href="' . h(url('/desk' . ($st !== '' ? query_string(['status' => $st]) : ''))) . '" class="chip' . ($filter === $st ? ' on' : '') . '">' . h(status_title($st)) . ' <span class="count">' . (int) ($counts[$st] ?? 0) . '</span></a>';
        }
        $body .= '</div>';
        $body .= '<div class="table-wrap"><table><caption class="sr">Applications</caption><tr><th>Who</th><th>Role</th><th>Step</th><th></th></tr>';
        foreach ($apps as $app) {
            $body .= '<tr><td>' . h($app['name']) . '<br><span class="muted">' . h($app['email']) . ' · ' . h($app['mobile']) . ' · ' . h($app['ref_code']) . '</span></td>';
            $body .= '<td>' . h($app['job_title'] ?: 'General resume') . '<br><span class="muted">' . h(substr((string) $app['created_at'], 0, 10)) . '</span></td>';
            $body .= '<td><form method="post" class="status-form"><input type="hidden" name="csrf" value="' . h(csrf()) . '">';
            $body .= '<input type="hidden" name="action" value="status"><input type="hidden" name="id" value="' . (int) $app['id'] . '">';
            $body .= '<label class="sr" for="st-' . (int) $app['id'] . '">Step for ' . h($app['name']) . '</label>';
            $body .= '<select id="st-' . (int) $app['id'] . '" name="status">';
            foreach (['new', 'screening', 'shortlisted', 'interview', 'selected', 'joined', 'rejected'] as $st) {
                $sel = $st === $app['status'] ? ' selected' : '';
                $body .= '<option value="' . h($st) . '"' . $sel . '>' . h(status_title($st)) . '</option>';
            }
            $body .= '</select>';
            $body .= '<label class="sr" for="nt-' . (int) $app['id'] . '">Note for ' . h($app['name']) . '</label>';
            $body .= '<input id="nt-' . (int) $app['id'] . '" name="note" placeholder="Note (optional)" maxlength="500">';
            $body .= '<button class="btn btn-gold" type="submit">Save</button></form></td>';
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
    } elseif ($tab === 'candidates') {
        $search = field_get('q', 120);
        $cvs = CandidateRepo::deskList($search);
        $body .= '<form class="search" method="get" role="search" style="max-width:32rem">';
        $body .= '<label class="sr" for="cvq">Search CVs</label><input id="cvq" name="q" value="' . h($search) . '" placeholder="Name, email, city, role">';
        $body .= '<input type="hidden" name="tab" value="candidates"><button class="btn btn-navy" type="submit">Search</button></form>';
        $body .= '<div class="table-wrap"><table><caption class="sr">CV bank</caption><tr><th>Who</th><th>Preferences</th><th>Step</th><th></th></tr>';
        foreach ($cvs as $cv) {
            $body .= '<tr><td>' . h($cv['name']) . '<br><span class="muted">' . h($cv['email']) . ' · ' . h($cv['mobile']) . ' · ' . h($cv['ref_code']) . '</span></td>';
            $body .= '<td>' . h(trim(($cv['designation'] ?? '') . ' · ' . ($cv['preferred_location'] ?? '') . ' · ' . ($cv['preferred_function'] ?? '') . ' · ' . ($cv['expected_salary'] ?? ''), ' ·')) . '</td>';
            $body .= '<td><form method="post" class="status-form"><input type="hidden" name="csrf" value="' . h(csrf()) . '">';
            $body .= '<input type="hidden" name="action" value="cv_status"><input type="hidden" name="id" value="' . (int) $cv['id'] . '">';
            $body .= '<label class="sr" for="cs-' . (int) $cv['id'] . '">Step for ' . h($cv['name']) . '</label>';
            $body .= '<select id="cs-' . (int) $cv['id'] . '" name="status">';
            foreach (['new', 'reviewing', 'shortlisted', 'placed', 'archived'] as $st) {
                $sel = $st === $cv['status'] ? ' selected' : '';
                $body .= '<option value="' . h($st) . '"' . $sel . '>' . h(status_title($st)) . '</option>';
            }
            $body .= '</select><button class="btn btn-gold" type="submit">Save</button></form></td>';
            $body .= '<td>';
            if ((string) ($cv['resume_path'] ?? '') !== '') {
                $body .= '<a class="btn btn-line btn-sm" href="' . h(url('/file/cv/' . (int) $cv['id'])) . '">Resume</a>';
            }
            $body .= '</td></tr>';
        }
        if ($cvs === []) {
            $body .= '<tr><td colspan="4" class="muted">The CV bank is empty in this view.</td></tr>';
        }
        $body .= '</table></div>';
    } elseif ($tab === 'leads') {
        $leads = LeadRepo::deskList();
        $messages = Evolve\Service\IntakeService::contactMessages();
        $body .= '<h2>Hiring briefs</h2><div class="table-wrap"><table><tr><th>Company</th><th>Role</th><th>Contact</th><th></th></tr>';
        foreach ($leads as $lead) {
            $body .= '<tr><td>' . h($lead['company']) . '<br><span class="muted">' . h($lead['industry']) . ' · ' . (int) $lead['openings'] . ' opening' . ((int) $lead['openings'] === 1 ? '' : 's') . '</span></td>';
            $body .= '<td>' . h($lead['role_title']) . '<br><span class="muted">' . h(trim(($lead['location'] ?? '') . ' · ' . ($lead['experience'] ?? '') . ' · ' . ($lead['salary'] ?? ''), ' ·')) . '</span></td>';
            $body .= '<td>' . h($lead['contact']) . '<br><span class="muted">' . h($lead['email']) . ' · ' . h($lead['phone']) . '</span></td>';
            $body .= '<td>';
            if ((string) ($lead['jd_path'] ?? '') !== '') {
                $body .= '<a class="btn btn-line btn-sm" href="' . h(url('/file/jd/' . (int) $lead['id'])) . '">JD</a>';
            }
            $body .= '</td></tr>';
        }
        if ($leads === []) {
            $body .= '<tr><td colspan="4" class="muted">No briefs yet.</td></tr>';
        }
        $body .= '</table></div>';
        $body .= '<h2>Website messages</h2><ul class="msglist">';
        foreach ($messages as $m) {
            $body .= '<li><strong>' . h($m['name']) . '</strong> <span class="muted">' . h($m['email']) . ' · ' . h(substr((string) $m['created_at'], 0, 16)) . '</span><br>' . h($m['message']) . '</li>';
        }
        if ($messages === []) {
            $body .= '<li class="muted">No messages yet.</li>';
        }
        $body .= '</ul>';
    } elseif ($tab === 'jobs') {
        $jobs = Evolve\Repository\JobRepo::adminList();
        $body .= '<p><a class="btn btn-navy" href="' . h(url('/admin?section=jobs')) . '">Add or edit roles (owner tools)</a></p>';
        $body .= '<div class="table-wrap"><table><tr><th>Role</th><th>Company</th><th>Status</th><th>Flags</th></tr>';
        foreach ($jobs as $job) {
            $body .= '<tr><td>' . h($job['title']) . '<br><span class="muted">' . h(substr((string) ($job['published_at'] ?? ''), 0, 10)) . '</span></td>';
            $body .= '<td>' . h($job['company']) . '<br><span class="muted">' . h($job['location']) . '</span></td>';
            $body .= '<td>' . h($job['status']) . '</td>';
            $body .= '<td>' . (((int) $job['featured'] === 1 ? 'Featured ' : '') . ((int) $job['urgent'] === 1 ? 'Urgent ' : '') . ((int) $job['internal'] === 1 ? 'Internal' : '')) . '</td></tr>';
        }
        if ($jobs === []) {
            $body .= '<tr><td colspan="4" class="muted">No roles yet.</td></tr>';
        }
        $body .= '</table></div>';
    } elseif ($tab === 'resets') {
        $resets = UserRepo::openResets();
        $body .= '<p class="lede-ink">There is no email OTP. Set a new password here, then tell the person by phone or WhatsApp.</p>';
        $body .= '<div class="table-wrap"><table><tr><th>Who asked</th><th>When</th><th></th></tr>';
        foreach ($resets as $r) {
            $body .= '<tr><td>' . h($r['email']) . '</td><td>' . h(substr((string) $r['created_at'], 0, 16)) . '</td>';
            $body .= '<td><a class="btn btn-gold btn-sm" href="' . h(url('/set-password?reset=' . (int) $r['id'])) . '">Set password</a></td></tr>';
        }
        if ($resets === []) {
            $body .= '<tr><td colspan="3" class="muted">No open requests.</td></tr>';
        }
        $body .= '</table></div>';
    }

    $body .= '</div></section>';
    Evolve\Core\View::layout('Staff desk | Evolve Hire', $body, ['robots' => 'noindex,nofollow'], true);
}

function page_file(array $p): void
{
    Evolve\Core\Response::noindex();
    AuthService::requireRole('owner', 'staff');
    $kind = (string) ($p['kind'] ?? '');
    $id = (int) ($p['id'] ?? 0);
    $rel = '';
    if ($kind === 'resume') {
        $app = ApplicationRepo::byId($id);
        $rel = (string) ($app['resume_path'] ?? '');
    } elseif ($kind === 'cv') {
        $stmt = db()->prepare('SELECT resume_path FROM candidates WHERE id = ?');
        $stmt->execute([$id]);
        $rel = (string) ($stmt->fetchColumn());
    } elseif ($kind === 'jd') {
        $lead = LeadRepo::byId($id);
        $rel = (string) ($lead['jd_path'] ?? '');
    } else {
        http_response_code(404);
        exit('Not found.');
    }
    UploadService::download($rel);
}
