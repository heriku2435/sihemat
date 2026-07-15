<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$token = \App\Models\Pengaturan::where('key', 'token_fonnte')->value('value');
$response = \Illuminate\Support\Facades\Http::asForm()->withHeaders([
    'Authorization' => $token,
])->post('https://api.fonnte.com/send', [
    'target' => '081234567890',
    'message' => 'Test message',
]);
echo "Status: " . $response->status() . "\n";
echo "Body: " . $response->body() . "\n";
