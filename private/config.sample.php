<?php
/** Copy to config.php (same folder). Never upload/commit the real file via Git. */
return [
    // 'mysql' on WebspaceKit cPanel; 'sqlite' for local sandbox dev only.
    'db_driver' => 'mysql',
    'db_host' => 'localhost',
    'db_port' => 3306,
    'db_name' => 'cpaneluser_evolve',
    'db_user' => 'cpaneluser_evolve',
    'db_pass' => 'change-me',
    // Sandbox dev: 'db_driver' => 'sqlite', 'db_sqlite_path' => '/home/USERNAME/evolvehire/private/storage/dev.sqlite'
    'db_sqlite_path' => '',
    'notify_email' => 'owner@example.com',
    // 24+ random chars. Generate: openssl rand -hex 16   (guards /install.php and /migrate.php)
    'setup_token' => 'REPLACE_WITH_RANDOM_32_HEX_CHARS_NOW',
];
