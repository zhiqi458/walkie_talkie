<?php
declare(strict_types=1);

namespace App;

require_once __DIR__ . '/Core/Autoloader.php';

\App\Core\Autoloader::register();
\App\Core\Env::load(__DIR__ . '/../.env');
\App\Core\Session::start();

$GLOBALS['app_config'] = require __DIR__ . '/../config/app.php';
