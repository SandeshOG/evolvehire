<?php
declare(strict_types=1);

/** Shared template fragments. Each returns an HTML string; data is escaped at use. */

function tpl_field(string $name, string $label, string $type = 'text', string $value = '', array $attrs = [], string $hint = '', string $error = ''): string
{
    $id = 'f-' . $name;
    $attr = '';
    foreach ($attrs as $k => $v) {
        $attr .= ' ' . $k . '="' . h((string) $v) . '"';
    }
    $described = '';
    if ($hint !== '') {
        $described = ' aria-describedby="' . $id . '-hint"';
    } elseif ($error !== '') {
        $described = ' aria-describedby="' . $id . '-err"';
    }
    $html = '<div class="field">';
    $html .= '<label for="' . $id . '">' . h($label) . '</label>';
    if ($type === 'textarea') {
        $html .= '<textarea id="' . $id . '" name="' . h($name) . '"' . $described . $attr . '>' . h($value) . '</textarea>';
    } elseif ($type === 'select') {
        $html .= '<select id="' . $id . '" name="' . h($name) . '"' . $described . $attr . '>';
        foreach ($attrs['options'] ?? [] as $k => $v) {
            $sel = (string) $k === $value ? ' selected' : '';
            $html .= '<option value="' . h((string) $k) . '"' . $sel . '>' . h((string) $v) . '</option>';
        }
        $html .= '</select>';
    } else {
        $html .= '<input id="' . $id . '" type="' . h($type) . '" name="' . h($name) . '" value="' . h($value) . '"' . $described . $attr . '>';
    }
    if ($hint !== '') {
        $html .= '<p class="hint" id="' . $id . '-hint">' . h($hint) . '</p>';
    }
    if ($error !== '') {
        $html .= '<p class="ferr" id="' . $id . '-err">' . h($error) . '</p>';
    }
    $html .= '</div>';
    return $html;
}

function tpl_select(string $name, string $label, array $options, string $value = '', string $hint = '', bool $required = false): string
{
    $id = 'f-' . $name;
    $html = '<div class="field"><label for="' . $id . '">' . h($label) . '</label>';
    $html .= '<select id="' . $id . '" name="' . h($name) . '"'
        . ($required ? ' required' : '')
        . ($hint !== '' ? ' aria-describedby="' . $id . '-hint"' : '') . '>';
    $html .= '<option value="">Any</option>';
    foreach ($options as $k => $v) {
        $sel = (string) $k === $value ? ' selected' : '';
        $html .= '<option value="' . h((string) $k) . '"' . $sel . '>' . h((string) $v) . '</option>';
    }
    $html .= '</select>';
    if ($hint !== '') {
        $html .= '<p class="hint" id="' . $id . '-hint">' . h($hint) . '</p>';
    }
    $html .= '</div>';
    return $html;
}

/** Error summary a11y pattern: focusable, links each item to its field. */
function tpl_error_summary(array $errors): string
{
    if ($errors === []) {
        return '';
    }
    $html = '<div class="notice bad esum" role="alert" tabindex="-1" aria-labelledby="esum-t">';
    $html .= '<h2 id="esum-t" class="esum-t">There is a problem</h2><ul>';
    foreach ($errors as $field => $msg) {
        $html .= '<li><a href="#f-' . h($field) . '">' . h($msg) . '</a></li>';
    }
    $html .= '</ul></div>';
    return $html;
}

function tpl_steps(string $status): string
{
    $order = ['new' => 0, 'screening' => 1, 'shortlisted' => 2, 'interview' => 3, 'selected' => 4, 'joined' => 4];
    $labels = ['Submitted', 'In review', 'Shortlisted', 'Interview', 'Hired'];
    $at = $order[$status] ?? -1;
    $html = '<ol class="track" aria-label="Progress">';
    foreach ($labels as $i => $label) {
        $on = $status !== 'rejected' && $i <= $at;
        $html .= '<li class="track-step' . ($on ? ' on' : '') . '"'
            . ($on ? ' aria-current="step"' : '') . '>' . h($label) . '</li>';
    }
    if ($status === 'rejected') {
        $html .= '<li class="track-step on" aria-current="step">Not selected</li>';
    }
    $html .= '</ol>';
    return $html;
}

function tpl_job_card(array $job): string
{
    $co = trim((string) ($job['company'] ?? ''));
    $mode = (string) ($job['work_mode'] ?? '');
    $modeLabel = $mode !== '' ? (work_modes()[$mode] ?? $mode) : '';
    $emp = (string) ($job['employment_type'] ?? '');
    $empLabel = $emp !== '' ? (employment_types()[$emp] ?? $emp) : '';
    $meta = array_filter([
        trim((string) ($job['location'] ?? '')),
        $modeLabel,
    ]);
    $tags = array_values(array_filter([
        (string) ($job['industry'] ?? ''),
        (string) ($job['function_name'] ?? ''),
        (string) ($job['experience_label'] ?? ''),
        $empLabel,
    ], static fn ($t): bool => trim($t) !== ''));
    $pay = trim((string) ($job['salary_label'] ?? ''));
    $initial = mb_strtoupper(mb_substr($co !== '' ? $co : 'E', 0, 1));

    $html = '<a class="job" href="' . h(url('/jobs/' . $job['slug'])) . '">';
    $html .= '<span class="job-mark" aria-hidden="true">' . h($initial) . '</span>';
    $html .= '<span class="job-body"><span class="job-kicker">' . h($co !== '' ? $co : 'Open mandate');
    if ((int) ($job['urgent'] ?? 0) === 1) {
        $html .= '<span class="badge urgent">Urgent</span>';
    }
    $html .= '</span><strong>' . h((string) $job['title']) . '</strong>';
    if ($meta !== []) {
        $html .= '<span class="job-meta">' . h(implode(' · ', $meta)) . '</span>';
    }
    if ($tags !== []) {
        $html .= '<span class="job-tags">';
        foreach ($tags as $tag) {
            $html .= '<span>' . h($tag) . '</span>';
        }
        $html .= '</span>';
    }
    $html .= '</span><span class="job-end"><span class="job-pay">' . h($pay !== '' ? $pay : 'Discussed on the call') . '</span>';
    $html .= '<span class="job-go">View role</span></span></a>';
    return $html;
}

function tpl_notice_good(string $text): string
{
    return '<p class="notice good">' . $text . '</p>';
}

function tpl_notice_bad(string $text): string
{
    return '<p class="notice bad">' . h($text) . '</p>';
}
