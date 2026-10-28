<?php

use App\Controllers\SchoolController as School;
$router->get('/login', [School::class, 'login']);
$router->post('/login', [School::class, 'authenticate']);
$router->post('/setup', [School::class, 'setup']);
$router->post('/logout', [School::class, 'logout'], true);
$router->post('/password', [School::class, 'password'], true);
$router->get('/', [School::class, 'dashboard'], true);
$router->get('/invoices/{id}/print', [School::class, 'printInvoice'], true);
foreach (['students','teachers','classes','attendance','fees','results','reports','settings'] as $page) {
    $router->get('/'.$page, fn() => (new School)->page($page), true);
}
$router->post('/save/{entity}', [School::class, 'save'], true);
$router->post('/archive/{id}', [School::class, 'archive'], true);
$router->get('/export/{entity}', [School::class, 'export'], true);
