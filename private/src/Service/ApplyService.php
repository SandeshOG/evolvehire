<?php
declare(strict_types=1);

namespace Evolve\Service;

use Evolve\Core\Http;
use Evolve\Core\Log;
use Evolve\Core\Request;
use Evolve\Core\ValidationError;
use Evolve\Repository\ApplicationRepo;
use Evolve\Repository\JobRepo;
use Evolve\Repository\SecurityRepo;

/** Candidate flow: apply to a job (dedupe, ref code, events, notices). */
final class ApplyService
{
    public static function submit(int $jobId, array $in, array $file): array
    {
        if (Request::honeypotTripped()) {
            throw new ValidationError('The application could not be saved.');
        }
        Http::rateLimit('form', 10, 600);
        $name = trim((string) ($in['name'] ?? ''));
        if ($name === '') {
            throw new ValidationError('Name is required.');
        }
        $email = require_email((string) ($in['email'] ?? ''));
        $mobile = require_mobile((string) ($in['mobile'] ?? ''));

        $job = JobRepo::byId($jobId);
        if (!$job || $job['status'] !== 'published') {
            throw new ValidationError('This role is not open.');
        }
        $existing = ApplicationRepo::existingForJob($jobId, $email);
        if ($existing) {
            return ['ref' => (string) $existing['ref_code'], 'duplicate' => true];
        }
        $resumePath = UploadService::save($file, 'resume');

        $ref = ref_code();
        $id = ApplicationRepo::insert([
            'job_id' => $jobId,
            'ref_code' => $ref,
            'name' => $name,
            'mobile' => $mobile,
            'email' => $email,
            'current_location' => substr(trim((string) ($in['current_location'] ?? '')), 0, 120),
            'experience' => substr(trim((string) ($in['experience'] ?? '')), 0, 80),
            'notice_period' => substr(trim((string) ($in['notice_period'] ?? '')), 0, 80),
            'expected_salary' => substr(trim((string) ($in['expected_salary'] ?? '')), 0, 80),
            'note' => substr(trim((string) ($in['note'] ?? '')), 0, 500),
            'resume_path' => $resumePath,
        ]);
        ApplicationRepo::addEvent($id, 'new', '', '');
        SecurityRepo::notify('application', 'New application — ' . $job['title'], $name . ' applied for ' . $job['title'] . ' (' . $email . ').');
        MailService::notifyOwner('New application ' . $ref, $name . ' applied for ' . $job['title'] . ' (' . $email . ').');
        return ['ref' => $ref, 'duplicate' => false];
    }
}
