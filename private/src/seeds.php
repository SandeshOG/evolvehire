<?php
declare(strict_types=1);

/**
 * Content seeds: the industries/functions/services catalogues from the Node
 * app, plus default settings. Idempotent — INSERT ... ON CONFLICT/IGNORE,
 * never overwrites edited rows.
 */

function seed_content(): void
{
    $pdo = db();
    $sqlite = \Evolve\Core\Db::isSqlite();

    $industries = [
        ['Manufacturing', 'manufacturing', 'Plants, shop-floor and industrial units hiring operators through managers.', 1],
        ['Automotive', 'automotive', 'OEMs, ancillaries and dealer networks.', 2],
        ['Engineering', 'engineering', 'Project, design, site and maintenance roles.', 3],
        ['Information technology', 'information-technology', 'Software, infrastructure and support roles.', 4],
        ['Banking and financial services', 'banking-financial-services', 'Branch, operations, credit and support hiring.', 5],
        ['Healthcare', 'healthcare', 'Hospitals, diagnostics and health services.', 6],
        ['Pharmaceuticals', 'pharmaceuticals', 'Plant, quality, sales and corporate roles.', 7],
        ['FMCG', 'fmcg', 'Sales, distribution and plant hiring.', 8],
        ['Logistics', 'logistics', 'Warehouse, transport and supply-chain teams.', 9],
        ['Retail', 'retail', 'Stores, area teams and backend operations.', 10],
        ['Construction', 'construction', 'Site, project and support staff.', 11],
        ['Education', 'education', 'Institutions and education services.', 12],
    ];
    foreach ($industries as $i) {
        if ($sqlite) {
            $pdo->prepare('INSERT INTO industries (name, slug, summary, sort_order) VALUES (?, ?, ?, ?) ON CONFLICT(slug) DO NOTHING')->execute($i);
        } else {
            $pdo->prepare('INSERT IGNORE INTO industries (name, slug, summary, sort_order) VALUES (?, ?, ?, ?)')->execute($i);
        }
    }

    $functions = [
        ['Human resources', 'human-resources', 'HR generalists, talent acquisition and employee relations.', 1],
        ['Finance and accounts', 'finance-and-accounts', 'Accounts, costing, audit and MIS.', 2],
        ['Sales', 'sales', 'Field, key-account and channel sales.', 3],
        ['Operations', 'operations', 'Plant, branch and process operations.', 4],
        ['Production', 'production', 'Shop-floor supervision and production control.', 5],
        ['Quality', 'quality', 'QA, QC and compliance.', 6],
        ['Engineering', 'engineering', 'Design, projects, maintenance and site engineering.', 7],
        ['Information technology', 'information-technology', 'Development, infrastructure and application support.', 8],
        ['Supply chain', 'supply-chain', 'Purchase, stores, planning and logistics.', 9],
        ['Administration', 'administration', 'Office administration and coordination.', 10],
    ];
    foreach ($functions as $f) {
        if ($sqlite) {
            $pdo->prepare('INSERT INTO functions (name, slug, summary, sort_order) VALUES (?, ?, ?, ?) ON CONFLICT(slug) DO NOTHING')->execute($f);
        } else {
            $pdo->prepare('INSERT IGNORE INTO functions (name, slug, summary, sort_order) VALUES (?, ?, ?, ?)')->execute($f);
        }
    }

    $services = [
        ['Workforce hiring', 'workforce-hiring', 'High-volume hiring that stays organised as the numbers grow.', 'For plants, sites, warehouses and branch networks that need many people against one brief. Roles are defined once, sourcing is tracked, and joining is followed through.', 1],
        ['Specialist hiring', 'specialist-hiring', 'A defined skill, domain or certification — screened against the brief.', 'When the role is narrow, the search is mapped. Shortlists say why a person fits, not only that they applied.', 2],
        ['Leadership hiring', 'leadership-hiring', 'Managers and functional heads, searched more quietly.', 'A smaller slate, more context, and a process that respects both the organisation and the candidate.', 3],
        ['Contract staffing', 'contract-staffing', 'Time-bound teams for a project, a season or a ramp-up.', 'Headcount that should not become a permanent structure. Tenure, location and replacement are agreed up front.', 4],
        ['Hiring support', 'hiring-support', 'Intake, shortlisting and coordination, so your team decides rather than sorts.', 'Useful when the internal team is thin. Evolve Hire keeps the record; your managers keep the decision.', 5],
    ];
    foreach ($services as $s) {
        if ($sqlite) {
            $pdo->prepare('INSERT INTO services (name, slug, summary, body, sort_order) VALUES (?, ?, ?, ?, ?) ON CONFLICT(slug) DO NOTHING')->execute($s);
        } else {
            $pdo->prepare('INSERT IGNORE INTO services (name, slug, summary, body, sort_order) VALUES (?, ?, ?, ?, ?)')->execute($s);
        }
    }

    $settings = [
        'notify_email' => 'ritikkumbhare760@gmail.com',
        'phone' => '7517885549',
        'phone_display' => '+91 75178 85549',
        'email' => 'ritikkumbhare760@gmail.com',
        'address' => 'Plot No. 51, Bhawani Nagar, Pardi, Near Sangita General Stores, Nagpur, Maharashtra 440035',
        'city' => 'Nagpur, Maharashtra',
        'website' => 'https://evolvehire.in',
        'proprietor' => 'Vinayak Kishorilal Kumbhare',
        'gstin' => '27DDCPK9961G1Z6',
        'udyam' => 'UDYAM-MH-20-0182397',
        'establishment_receipt' => '2652300321234645',
        'established' => '2020-01-01',
    ];
    foreach ($settings as $k => $v) {
        if ($sqlite) {
            $pdo->prepare('INSERT INTO settings (skey, svalue) VALUES (?, ?) ON CONFLICT(skey) DO NOTHING')->execute([$k, $v]);
        } else {
            $pdo->prepare('INSERT IGNORE INTO settings (skey, svalue) VALUES (?, ?)')->execute([$k, $v]);
        }
    }
}
