<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$token = \App\Models\Pengaturan::where('key', 'token_fonnte')->value('value');
$response = \Illuminate\Support\Facades\Http::withHeaders([
    'Authorization' => $token,
])->post('https://api.fonnte.com/send', []);
echo "Status: " . $response->status() . "\n";
echo "Body: " . $response->body() . "\n";
