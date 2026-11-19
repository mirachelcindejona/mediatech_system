<?php

use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

// Separate HTTP-kernel process for InventoryConcurrencyTest, never a production entry point.
require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (getenv('INVENTORY_DISPOSABLE_MYSQL') !== '1'
    || DB::connection()->getDriverName() !== 'mysql'
    || ! str_starts_with(DB::connection()->getDatabaseName(), 'mediatech_inventory_test_')) {
    throw new RuntimeException('Concurrency workers require an explicitly disposable MySQL database.');
}
$connection = DB::selectOne('SELECT CONNECTION_ID() AS id')->id;
Auth::guard()->setUser(User::findOrFail($argv[1]));
$request = Request::create('http://app.meditech.local/inventory/goods-issues', 'POST', server: [
    'HTTP_ACCEPT' => 'application/json', 'CONTENT_TYPE' => 'application/json',
], content: base64_decode($argv[2], true));
echo "READY:$connection\n";
flush();
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$response = $kernel->handle($request);
echo json_encode(['status' => $response->getStatusCode(), 'data' => json_decode($response->getContent(), true)], JSON_THROW_ON_ERROR)."\n";
$kernel->terminate($request, $response);
