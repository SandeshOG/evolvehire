<?php
declare(strict_types=1);

namespace Evolve\Service;

use Evolve\Core\Http;
use Evolve\Core\Request;
use Evolve\Core\ValidationError;
use Evolve\Repository\CandidateRepo;
use Evolve\Repository\LeadRepo;
use Evolve\Repository\SecurityRepo;
use Evolve\Repository\UserRepo;

/** CV bank submissions, employer briefs, contact messages, password reset requests. */
final class IntakeService
{
    public static function submitCv(array $in, array $file): array
    {
        if (Request::honeypotTripped()) {
            throw new ValidationError('The resume could not be saved.');
        }
        Http::rateLimit('form', 10, 600);
        $name = trim((string) ($in['name'] ?? ''));
        if ($name === '') {
            throw new ValidationError('Name is required.');
        }
        $email = require_email((string) ($in['email'] ?? ''));
        $mobile = require_mobile((string) ($in['mobile'] ?? ''));
        $existing = CandidateRepo::byEmail($email);
        $resumePath = UploadService::save($file, 'cv');
        $result = CandidateRepo::upsert([
            'name' => $name,
            'mobile' => $mobile,
            'email' => $email,
            'current_location' => substr(trim((string) ($in['current_location'] ?? '')), 0, 120),
            'experience' => substr(trim((string) ($in['experience'] ?? '')), 0, 80),
            'designation' => substr(trim((string) ($in['designation'] ?? '')), 0, 120),
            'preferred_location' => substr(trim((string) ($in['preferred_location'] ?? '')), 0, 120),
            'preferred_industry' => substr(trim((string) ($in['preferred_industry'] ?? '')), 0, 180),
            'preferred_function' => substr(trim((string) ($in['preferred_function'] ?? '')), 0, 180),
            'expected_salary' => substr(trim((string) ($in['expected_salary'] ?? '')), 0, 80),
            'notice_period' => substr(trim((string) ($in['notice_period'] ?? '')), 0, 80),
            'resume_path' => $resumePath,
        ]);
        if (!$result['existing']) {
            SecurityRepo::notify('cv', 'CV on file — ' . $name, $name . ' left a resume. ' . $email);
            MailService::notifyOwner('Resume ' . $result['ref_code'], $name . ' left a resume. ' . $email);
        }
        return $result;
    }

    public static function submitBrief(array $in, array $jdFile = []): void
    {
        if (Request::honeypotTripped()) {
            throw new ValidationError('The brief could not be saved.');
        }
        Http::rateLimit('form', 10, 600);
        $company = trim((string) ($in['company'] ?? ''));
        $contact = trim((string) ($in['contact'] ?? ''));
        $role = trim((string) ($in['role_title'] ?? ''));
        if ($company === '' || $contact === '' || $role === '') {
            throw new ValidationError('Company, contact, and role are required.');
        }
        $email = require_email((string) ($in['email'] ?? ''));
        $phone = require_mobile((string) ($in['phone'] ?? ''));
        $openings = (int) ($in['openings'] ?? 1);
        $openings = max(1, min(5000, $openings));
        $jdPath = '';
        if (!empty($jdFile['name'])) {
            $jdPath = UploadService::save($jdFile, 'jd');
        }
        LeadRepo::insert([
            'company' => $company,
            'contact' => $contact,
            'email' => $email,
            'phone' => $phone,
            'industry' => substr(trim((string) ($in['industry'] ?? '')), 0, 180),
            'role_title' => $role,
            'openings' => $openings,
            'location' => substr(trim((string) ($in['location'] ?? '')), 0, 120),
            'experience' => substr(trim((string) ($in['experience'] ?? '')), 0, 80),
            'salary' => substr(trim((string) ($in['salary'] ?? '')), 0, 80),
            'employment_type' => substr(trim((string) ($in['employment_type'] ?? '')), 0, 40),
            'work_mode' => substr(trim((string) ($in['work_mode'] ?? '')), 0, 40),
            'detail' => substr(trim((string) ($in['detail'] ?? '')), 0, 1000),
            'jd_path' => $jdPath,
        ]);
        SecurityRepo::notify('employer', 'Hiring enquiry — ' . $company, $contact . ' needs ' . $role . ' in ' . ($in['location'] ?? '-') . '. ' . $email);
        MailService::notifyOwner('Hiring brief from ' . $company, $contact . ' needs ' . $role . ' in ' . ($in['location'] ?? '-') . '. ' . $email . ' ' . $phone);
    }

    public static function submitContact(array $in): void
    {
        if (Request::honeypotTripped()) {
            throw new ValidationError('The message could not be saved.');
        }
        Http::rateLimit('form', 10, 600);
        $name = trim((string) ($in['name'] ?? ''));
        if ($name === '') {
            throw new ValidationError('Name is required.');
        }
        $email = require_email((string) ($in['email'] ?? ''));
        $message = trim((string) ($in['message'] ?? ''));
        if (strlen($message) < 8) {
            throw new ValidationError('Write at least a line in the message.');
        }
        db()->prepare('INSERT INTO contact_messages (name, email, phone, message) VALUES (?, ?, ?, ?)')
            ->execute([
                substr($name, 0, 120),
                $email,
                substr(preg_replace('/\D+/', '', (string) ($in['phone'] ?? '')) ?? '', 0, 20),
                substr($message, 0, 2000),
            ]);
        SecurityRepo::notify('contact', 'Message — ' . $name, $message . ' (' . $email . ')');
        MailService::notifyOwner('Message from ' . $name, $message . ' (' . $email . ')');
    }

    public static function requestPasswordReset(string $email): void
    {
        Http::rateLimit('reset', 5, 900);
        $email = require_email($email);
        UserRepo::requestReset($email);
        SecurityRepo::notify('reset', 'Password reset — ' . $email, 'Someone asked for a desk password reset for ' . $email . '. If this was not you, ignore this.');
    }

    public static function contactMessages(): array
    {
        return db()->query('SELECT name, email, phone, message, created_at FROM contact_messages ORDER BY id DESC LIMIT 100')->fetchAll();
    }
}
