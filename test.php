<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$response = $kernel->handle(Illuminate\Http\Request::create('/test-transaksi', 'GET'));
echo $response->getStatusCode() === 500 ? 'Error 500' : 'Success ' . $response->getStatusCode();
