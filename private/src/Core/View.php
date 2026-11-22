<?php
declare(strict_types=1);

namespace Evolve\Core;

/**
 * View layer: layout() renders the full site shell; templates live in
 * private/views/ as plain PHP functions that return HTML strings.
 */
final class View
{
    /** Page title/description/canonical/OG metadata for <head>. */
    public static function meta(string $title, string $description, string $canonical = '', string $ogType = 'website', string $robots = 'index,follow'): array
    {
        return [
            'title' => $title,
            'description' => $description,
            'canonical' => $canonical,
            'og_type' => $ogType,
            'robots' => $robots,
        ];
    }

    /** Render the site shell around a body fragment. */
    public static function layout(string $title, string $body, array $meta = [], bool $desk = false): void
    {
        $u = user();
        $f = firm();
        $desc = $meta['description'] ?? 'Evolve Hire is a recruitment agency in Nagpur. Candidates apply and check status with a reference or email.';
        $canonical = $meta['canonical'] ?? '';
        $ogType = $meta['og_type'] ?? 'website';
        $robots = $meta['robots'] ?? 'index,follow';
        $navDesk = $u ? '/desk' : '/gate';
        $navDeskLabel = $u ? 'Desk' : 'Staff';

        echo '<!doctype html><html lang="en"><head><meta charset="utf-8">';
        echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
        echo '<title>' . h($title) . '</title>';
        echo '<meta name="description" content="' . h($desc) . '">';
        echo '<meta name="robots" content="' . h($robots) . '">';
        echo '<link rel="preload" href="' . h(url('/assets/fonts/instrument-serif-latin-400-normal.woff2')) . '" as="font" type="font/woff2" crossorigin>';
        echo '<link rel="preload" href="' . h(url('/assets/fonts/inter-latin-wght-normal.woff2')) . '" as="font" type="font/woff2" crossorigin>';
        if ($canonical !== '') {
            echo '<link rel="canonical" href="' . h($canonical) . '">';
        }
        echo '<meta property="og:site_name" content="Evolve Hire">';
        echo '<meta property="og:title" content="' . h($title) . '">';
        echo '<meta property="og:description" content="' . h($desc) . '">';
        echo '<meta property="og:type" content="' . h($ogType) . '">';
        if ($canonical !== '') {
            echo '<meta property="og:url" content="' . h($canonical) . '">';
        }
        echo '<meta property="og:image" content="' . h(url('/og.jpg')) . '">';
        echo '<meta name="theme-color" content="#0B1C33">';
        echo '<link rel="icon" href="' . h(url('/favicon.svg')) . '" type="image/svg+xml">';
        echo '<link rel="stylesheet" href="' . h(url('/assets/site.css?v=150')) . '">';
        echo '<script src="' . h(url('/assets/site.js?v=150')) . '" defer></script>';
        echo '</head><body' . ($desk ? ' class="is-desk"' : '') . '>';
        echo '<a class="skip" href="#main">Skip to content</a>';
        echo '<header class="top"><div class="wrap">';
        echo '<a class="brand" href="' . h(url('/')) . '">';
        echo '<img src="' . h(url('/brand/logo.jpg')) . '" alt="" width="64" height="64">';
        echo '<span><strong>Evolve Hire</strong><span>Nagpur desk</span></span></a>';
        echo '<button class="navtoggle" type="button" aria-expanded="false" aria-controls="nav"><span class="navtoggle-bar"></span><span class="navtoggle-bar"></span><span class="navtoggle-bar"></span><span class="sr">Menu</span></button>';
        $here = path();
        $navLink = static function (string $href, string $label, string $class = '') use ($here): void {
            $on = $href === '/' ? $here === '/' : ($here === $href || str_starts_with($here, rtrim($href, '/') . '/'));
            echo '<a href="' . h(url($href)) . '"'
                . ($class !== '' ? ' class="' . h($class) . '"' : '')
                . ($on ? ' aria-current="page"' : '')
                . '>' . h($label) . '</a>';
        };
        echo '<nav class="nav" id="nav" aria-label="Primary">';
        $navLink('/jobs', 'Jobs');
        $navLink('/hire', 'Hire');
        $navLink('/track', 'Status');
        $navLink('/about', 'About');
        $navLink('/resume', 'Submit resume', 'nav-cta');
        $navLink($navDesk, $navDeskLabel, 'nav-quiet');
        echo '</nav></div></header>';
        echo '<main id="main">';
        echo $body;
        echo '</main>';
        if (!$desk) {
            echo '<footer class="foot"><div class="wrap foot-grid">';
            echo '<div><strong>' . h($f['name']) . '</strong><p class="muted-on-dark">' . h($f['tagline']) . '</p>';
            echo '<p class="muted-on-dark">' . h($f['address']) . '</p>';
            echo '<p><a class="btn btn-gold btn-sm" href="tel:' . h($f['tel']) . '">Call</a> <a class="btn btn-wa btn-sm" href="' . h($f['whatsapp']) . '">WhatsApp</a></p></div>';
            echo '<nav aria-label="For candidates"><h2>For candidates</h2><ul>';
            foreach ([['Jobs', '/jobs'], ['Submit resume', '/resume'], ['Status', '/track'], ['Job alerts', '/job-alerts']] as [$label, $href]) {
                echo '<li><a href="' . h(url($href)) . '">' . h($label) . '</a></li>';
            }
            echo '</ul></nav>';
            echo '<nav aria-label="For companies"><h2>For companies</h2><ul>';
            foreach ([['Hire talent', '/hire'], ['Services', '/services'], ['Industries', '/industries']] as [$label, $href]) {
                echo '<li><a href="' . h(url($href)) . '">' . h($label) . '</a></li>';
            }
            echo '</ul></nav>';
            echo '<nav aria-label="On record"><h2>On record</h2><ul>';
            foreach ([['About', '/about'], ['The work', '/work'], ['Credentials', '/credentials'], ['Contact', '/contact']] as [$label, $href]) {
                echo '<li><a href="' . h(url($href)) . '">' . h($label) . '</a></li>';
            }
            echo '</ul></nav>';
            echo '</div><p class="foot-base muted-on-dark">GST ' . h($f['gstin']) . ' · ' . h($f['udyam']) . ' · ' . h($f['email']) . '</p></footer>';
        }
        echo '</body></html>';
    }
}
