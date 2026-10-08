<?php
declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';

use App\Core\Router;
use App\Controllers\HomeController;
use App\Controllers\SignalController;

$router = new Router();
$homeController = new HomeController();
$signalController = new SignalController();

$router->get('/', [$homeController, 'showJoin']);
$router->post('/join', [$homeController, 'join']);
$router->get('/channel', [$homeController, 'showChannel']);
$router->post('/leave', [$homeController, 'leave']);
$router->post('/signal', [$signalController, 'handle']);

$router->dispatch();
