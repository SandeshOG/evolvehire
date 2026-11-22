<?php
declare(strict_types=1);

/** Global helper functions used across controllers and templates. */

use Evolve\Core\Db;
use Evolve\Core\Log;

function private_dir(): string
{
    return PRIVATE_ROOT; // private/
}

function cfg(): array
{
    static $cfg = null;
    if ($cfg !== null) {
        return $cfg;
    }
    $path = private_dir() . '/config.php';
    if (!is_file($path)) {
        http_response_code(500);
        exit('Missing private/config.php. Copy config.sample.php and add the cPanel database.');
    }
    $cfg = require $path;
    return $cfg;
}

function db(): PDO
{
    return Db::pdo();
}

function h(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/** FIRM facts: config overrides, then settings table, then defaults. */
function firm(): array
{
    static $firm = null;
    if ($firm !== null) {
        return $firm;
    }
    $firm = [
        'name' => 'Evolve Hire',
        'tagline' => 'Connecting Talent. Building Futures.',
        'proprietor' => 'Vinayak Kishorilal Kumbhare',
        'constitution' => 'Proprietorship',
        'activity' => 'Employment placement agencies',
        'nic' => '7810',
        'phone' => '+91 75178 85549',
        'tel' => '+917517885549',
        'whatsapp' => 'https://wa.me/917517885549',
        'email' => 'ritikkumbhare760@gmail.com',
        'address' => 'Plot No. 51, Bhawani Nagar, Pardi, Near Sangita General Stores, Nagpur, Maharashtra 440035',
        'city' => 'Nagpur, Maharashtra',
        'gstin' => '27DDCPK9961G1Z6',
        'gst_from' => '13 March 2024',
        'udyam' => 'UDYAM-MH-20-0182397',
        'udyam_on' => '8 June 2026',
        'establishment_receipt' => '2652300321234645',
        'establishment_app' => '105030382603',
        'shop_on' => '8 June 2026',
        'commenced' => '1 January 2020',
        'url' => 'https://evolvehire.in',
    ];
    try {
        $rows = db()->query('SELECT skey, svalue FROM settings')->fetchAll(\PDO::FETCH_KEY_PAIR);
        foreach ($rows as $k => $v) {
            if ($v !== '' && $v !== null && array_key_exists($k, $firm)) {
                $firm[$k] = (string) $v;
            }
        }
    } catch (\Throwable $e) {
        Log::warning('settings table unavailable: ' . $e->getMessage());
    }
    return $firm;
}

function url(string $path): string
{
    return \Evolve\Core\Request::base() . $path;
}

function redirect(string $path): void
{
    header('Location: ' . url($path));
    exit;
}

function slugify(string $text): string
{
    $text = trim(mb_strtolower($text));
    $text = str_replace('&', ' and ', $text);
    $text = preg_replace('/[^a-z0-9]+/', '-', $text) ?? '';
    $text = substr(trim($text, '-'), 0, 80);
    return $text !== '' ? $text : 'role';
}

function status_title(string $status): string
{
    return match ($status) {
        'new' => 'Submitted',
        'screening' => 'In review',
        'shortlisted' => 'Shortlisted',
        'interview' => 'Interview',
        'selected' => 'Hired',
        'joined' => 'Joined',
        'rejected' => 'Not selected',
        'reviewing' => 'In review',
        'placed' => 'Placed',
        'archived' => 'Archived',
        default => 'Submitted',
    };
}

function status_note(string $status): string
{
    return match ($status) {
        'new' => 'Resume is in. The desk has not moved it yet.',
        'screening' => 'Someone is reading it against the brief.',
        'shortlisted' => 'You are in the smaller group.',
        'interview' => 'Interview is the current step.',
        'selected' => 'This one is hired.',
        'joined' => 'The person has joined.',
        'rejected' => 'This one is not moving ahead.',
        default => 'Waiting on the desk.',
    };
}

function ref_code(): string
{
    return 'EH' . strtoupper(bin2hex(random_bytes(3)));
}

function field(string $key, int $max): string
{
    $value = trim((string) ($_POST[$key] ?? ''));
    if (mb_strlen($value) > $max) {
        $value = mb_substr($value, 0, $max);
    }
    return $value;
}

function field_get(string $key, int $max): string
{
    $value = trim((string) ($_GET[$key] ?? ''));
    if (mb_strlen($value) > $max) {
        $value = mb_substr($value, 0, $max);
    }
    return $value;
}

function require_email(string $value): string
{
    $value = strtolower($value);
    if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
        throw new \Evolve\Core\ValidationError('Enter a valid email address.');
    }
    return $value;
}

function require_mobile(string $value): string
{
    $digits = preg_replace('/\D+/', '', $value) ?? '';
    if (strlen($digits) < 10 || strlen($digits) > 15) {
        throw new \Evolve\Core\ValidationError('Enter a valid mobile number.');
    }
    return $digits;
}

function experience_bands(): array
{
    return ['0-2', '2-5', '5-10', '10+'];
}

function salary_bands(): array
{
    return ['0-3', '3-6', '6-12', '12+'];
}

function work_modes(): array
{
    return ['office' => 'Office', 'hybrid' => 'Hybrid', 'remote' => 'Remote', 'field' => 'Field'];
}

function employment_types(): array
{
    return ['full-time' => 'Full-time', 'part-time' => 'Part-time', 'contract' => 'Contract', 'temporary' => 'Temporary'];
}

function query_string(array $params): string
{
    $keep = [];
    foreach ($params as $k => $v) {
        if ($v !== '' && $v !== null) {
            $keep[$k] = $v;
        }
    }
    return $keep === [] ? '' : '?' . http_build_query($keep);
}
