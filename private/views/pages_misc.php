<?php
declare(strict_types=1);

use Evolve\Repository\UserRepo;
use Evolve\Service\AuthService;

/** Password reset (no email OTP — staff set it and confirm by call/WhatsApp) + sitemap/robots/health. */

function page_forgot(): void
{
    Evolve\Core\Response::noindex();
    $errors = [];
    $done = false;
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        try {
            check_csrf();
            Evolve\Service\IntakeService::requestPasswordReset(field('email', 190));
            $done = true;
        } catch (Evolve\Core\ValidationError $e) {
            $errors['email'] = $e->getMessage();
        } catch (Throwable $e) {
            log_error($e);
            $errors['form'] = 'Could not record the request.';
        }
    }
    $body = '<section class="section"><div class="wrap narrow"><h1>Reset password</h1>';
    $body .= '<p class="lede-ink">This desk has no email OTP. Leave the email. A staff member sets a new password, then confirms it on a call or WhatsApp.</p>';
    if ($done) {
        $body .= tpl_notice_good('<strong>The request is on the desk.</strong> Someone will call to confirm before the password is set.');
    } else {
        $body .= tpl_error_summary($errors);
        $body .= '<form class="card grid" method="post" novalidate>';
        $body .= '<input type="hidden" name="csrf" value="' . h(csrf()) . '">';
        $body .= tpl_field('email', 'Email', 'email', field('email', 190), ['required' => true, 'autocomplete' => 'email'], '', $errors['email'] ?? '');
        $body .= '<button class="btn btn-navy" type="submit">Leave the request</button></form>';
    }
    $body .= '</div></section>';
    Evolve\Core\View::layout('Reset password | Evolve Hire', $body, ['robots' => 'noindex,nofollow'], true);
}

function page_set_password(): void
{
    Evolve\Core\Response::noindex();
    AuthService::requireRole('owner', 'staff');
    $resetId = (int) ($_GET['reset'] ?? 0);
    $reset = UserRepo::resolveReset($resetId);
    $errors = [];
    $ok = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        try {
            check_csrf();
            $email = require_email(field('email', 190));
            $new = (string) ($_POST['new'] ?? '');
            if (strlen($new) < 12) {
                throw new Evolve\Core\ValidationError('The new password must be at least 12 characters.');
            }
            $target = UserRepo::byEmail($email);
            if (!$target) {
                throw new Evolve\Core\ValidationError('No desk account uses that email.');
            }
            UserRepo::setPassword((int) $target['id'], password_hash($new, PASSWORD_DEFAULT), 1);
            if ($reset && $reset['email'] === $email) {
                UserRepo::closeReset($resetId);
            }
            Evolve\Core\Http::audit('password_set', $email);
            $ok = 'Password set. The person must change it at first login.';
        } catch (Evolve\Core\ValidationError $e) {
            $errors['form'] = $e->getMessage();
        } catch (Throwable $e) {
            log_error($e);
            $errors['form'] = 'Could not set the password.';
        }
    }
    $body = '<section class="section"><div class="wrap narrow"><h1>Set a password</h1>';
    $body .= '<p class="lede-ink">Set a password for a desk account, then confirm it to the person by phone or WhatsApp. They change it at first login.</p>';
    if ($reset) {
        $body .= '<p class="notice good">Requested for <strong>' . h($reset['email']) . '</strong></p>';
    }
    if ($ok !== '') {
        $body .= tpl_notice_good('<strong>' . h($ok) . '</strong>');
    }
    if ($ok === '') {
        $body .= tpl_error_summary($errors);
        $body .= '<form class="card grid" method="post" novalidate>';
        $body .= '<input type="hidden" name="csrf" value="' . h(csrf()) . '">';
        $value = $reset ? (string) $reset['email'] : field('email', 190);
        $body .= tpl_field('email', 'Account email', 'email', $value, ['required' => true, 'autocomplete' => 'off'], '', $errors['email'] ?? '');
        $body .= tpl_field('new', 'New password', 'password', '', ['required' => true, 'minlength' => 12, 'autocomplete' => 'new-password'], 'At least 12 characters.');
        $body .= '<button class="btn btn-navy" type="submit">Set password</button></form>';
    }
    $body .= '</div></section>';
    Evolve\Core\View::layout('Set password | Evolve Hire', $body, ['robots' => 'noindex,nofollow'], true);
}

function page_sitemap(): void
{
    header('Content-Type: application/xml; charset=utf-8');
    $base = rtrim((string) firm()['url'], '/');
    $urls = ['/', '/jobs', '/resume', '/hire', '/track', '/job-alerts', '/about', '/services', '/industries', '/functions', '/work', '/insights', '/case-studies', '/companies', '/careers', '/credentials', '/contact'];
    echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
    $driver = Evolve\Core\Db::isSqlite();
    $today = $driver ? "date('now')" : 'CURDATE()';
    $jobs = db()->query("SELECT slug, published_at FROM jobs WHERE status = 'published' AND internal = 0 AND (expires_at IS NULL OR expires_at >= $today)")->fetchAll();
    $insights = db()->query("SELECT slug, published_at FROM insights WHERE published = 1")->fetchAll();
    $cases = db()->query("SELECT slug, published_at FROM case_studies WHERE published = 1")->fetchAll();
    foreach ($urls as $path) {
        echo '<url><loc>' . h($base . $path) . '</loc></url>' . "\n";
    }
    foreach (array_merge($jobs, $insights, $cases) as $row) {
        echo '<url><loc>' . h($base . '/jobs/' . $row['slug']) . '</loc></url>' . "\n";
    }
    echo '</urlset>';
    exit;
}

function page_robots(): void
{
    header('Content-Type: text/plain; charset=utf-8');
    $base = rtrim((string) firm()['url'], '/');
    echo "User-agent: *\n";
    echo "Disallow: /gate\nDisallow: /desk\nDisallow: /admin\nDisallow: /forgot\nDisallow: /set-password\nDisallow: /change-password\n";
    echo "Sitemap: " . $base . "/sitemap.xml\n";
    exit;
}
