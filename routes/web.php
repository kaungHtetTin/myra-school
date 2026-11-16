<?php

use App\Controllers\SchoolController as School;
use App\Controllers\LearningController as Learning;
$router->get('/students', [Learning::class, 'students'], true);
$router->get('/students/{id}', [Learning::class, 'student'], true);
$router->get('/classes/{id}/batches', [Learning::class, 'batches'], true);
$router->get('/batches/{id}', [Learning::class, 'batch'], true);
$router->get('/invoices/{id}/payments', [Learning::class, 'payments'], true);
$router->get('/finance', [Learning::class, 'finance'], true);
$router->post('/learning/{entity}', [Learning::class, 'save'], true);
$router->get('/login', [School::class, 'login']);
$router->post('/login', [School::class, 'authenticate']);
$router->post('/setup', [School::class, 'setup']);
$router->post('/logout', [School::class, 'logout'], true);
$router->post('/password', [School::class, 'password'], true);
$router->get('/', [School::class, 'dashboard'], true);
$router->get('/reports', [School::class, 'reportsRedirect'], true);
$router->get('/invoices/{id}/print', [School::class, 'printInvoice'], true);
foreach (['teachers','classes','fees','settings'] as $page) {
    $router->get('/'.$page, fn() => (new School)->page($page), true);
}
$router->post('/save/{entity}', [School::class, 'save'], true);
$router->post('/archive/{id}', [School::class, 'archive'], true);
$router->get('/export/{entity}', [School::class, 'export'], true);
