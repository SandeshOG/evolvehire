<?php
declare(strict_types=1);

namespace Evolve\Service;

use Evolve\Core\Http;
use Evolve\Core\ValidationError;
use Evolve\Repository\JobRepo;

/** Job create/update validation + save (owner + staff roles). */
final class JobService
{
    public static function validate(array $in): array
    {
        $title = trim((string) ($in['title'] ?? ''));
        $company = trim((string) ($in['company'] ?? ''));
        if ($title === '' || $company === '') {
            throw new ValidationError('Title and company are required.');
        }
        $slug = trim((string) ($in['slug'] ?? '')) ?: slugify($title);
        if (!preg_match('/^[a-z0-9-]{1,190}$/', $slug)) {
            throw new ValidationError('The slug can use lower-case letters, numbers and dashes.');
        }
        $status = in_array($in['status'] ?? '', ['draft', 'published', 'closed'], true) ? $in['status'] : 'draft';
        $mode = array_key_exists($in['work_mode'] ?? '', work_modes()) ? $in['work_mode'] : '';
        $emp = array_key_exists($in['employment_type'] ?? '', employment_types()) ? $in['employment_type'] : '';
        $expMin = is_numeric($in['experience_min'] ?? null) ? (float) $in['experience_min'] : null;
        $expMax = is_numeric($in['experience_max'] ?? null) ? (float) $in['experience_max'] : null;
        if ($expMin !== null && ($expMin < 0 || $expMin > 50)) {
            $expMin = null;
        }
        if ($expMax !== null && ($expMax < 0 || $expMax > 50)) {
            $expMax = null;
        }
        $salMin = is_numeric($in['salary_min'] ?? null) ? (float) $in['salary_min'] : null;
        $salMax = is_numeric($in['salary_max'] ?? null) ? (float) $in['salary_max'] : null;
        if ($salMin !== null && ($salMin < 0 || $salMin > 999999)) {
            $salMin = null;
        }
        if ($salMax !== null && ($salMax < 0 || $salMax > 999999)) {
            $salMax = null;
        }
        $expires = trim((string) ($in['expires_at'] ?? ''));
        if ($expires !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $expires)) {
            $expires = '';
        }
        return [
            'title' => substr($title, 0, 180),
            'slug' => substr($slug, 0, 190),
            'company' => substr($company, 0, 180),
            'industry' => substr(trim((string) ($in['industry'] ?? '')), 0, 180),
            'function_name' => substr(trim((string) ($in['function'] ?? '')), 0, 180),
            'location' => substr(trim((string) ($in['location'] ?? '')), 0, 180),
            'experience_label' => substr(trim((string) ($in['experience_label'] ?? '')), 0, 80),
            'experience_min' => $expMin,
            'experience_max' => $expMax,
            'salary_label' => substr(trim((string) ($in['salary_label'] ?? '')), 0, 80),
            'salary_min' => $salMin,
            'salary_max' => $salMax,
            'employment_type' => $emp,
            'work_mode' => $mode,
            'eligibility' => substr(trim((string) ($in['eligibility'] ?? '')), 0, 500),
            'description' => (string) ($in['description'] ?? ''),
            'responsibilities' => (string) ($in['responsibilities'] ?? ''),
            'requirements' => (string) ($in['requirements'] ?? ''),
            'interview_process' => (string) ($in['interview_process'] ?? ''),
            'application_instructions' => (string) ($in['application_instructions'] ?? ''),
            'status' => $status,
            'featured' => ($in['featured'] ?? '') === '1',
            'urgent' => ($in['urgent'] ?? '') === '1',
            'internal' => ($in['internal'] ?? '') === '1',
            'published_at' => null,
            'expires_at' => $expires !== '' ? $expires : null,
        ];
    }

    public static function save(array $in, int $id = 0): int
    {
        $j = self::validate($in);
        if (JobRepo::slugExists($j['slug'], $id)) {
            throw new ValidationError('That web address is already used. Change the slug.');
        }
        if ($id > 0) {
            $current = JobRepo::byId($id);
            $j['published_at'] = $current ? $current['published_at'] : null;
            JobRepo::update($id, $j);
            Http::audit('job_update', $j['slug']);
            return $id;
        }
        $newId = JobRepo::insert($j);
        if ($j['status'] === 'published') {
            db()->prepare('UPDATE jobs SET published_at = ? WHERE id = ?')->execute([date('Y-m-d H:i:s'), $newId]);
        }
        Http::audit('job_create', $j['slug']);
        return $newId;
    }

    public static function delete(int $id): void
    {
        if (JobRepo::countApplications($id) > 0) {
            throw new ValidationError('This role has applications. Close it instead of deleting it.');
        }
        $job = JobRepo::byId($id);
        JobRepo::delete($id);
        Http::audit('job_delete', $job['slug'] ?? (string) $id);
    }
}
