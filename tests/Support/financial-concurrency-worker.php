<?php

use App\Models\Payment;
use App\Models\SalesOrder;
use App\Services\SalesOrderPaymentService;
use App\Support\SequentialNumber;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

if (! $app->environment('testing') || config('database.connections.mysql.database') !== 'printos_audit_testing') {
    throw new RuntimeException('Concurrency workers require the dedicated audit test database.');
}

[$script, $operation, $recordId, $gate] = $argv;
new Payment;
Payment::creating(function (): void {
    usleep(150000);
});
file_put_contents($gate.'.ready.'.getmypid(), 'ready');
$deadline = microtime(true) + 15;
while (! is_file($gate)) {
    if (microtime(true) > $deadline) {
        throw new RuntimeException('Concurrency barrier timed out.');
    }
    usleep(10000);
}

try {
    $result = match ($operation) {
        'bank' => Payment::factory()->create(['payment_number' => 'AUDIT-'.bin2hex(random_bytes(8)), 'amount' => 100,
            'method' => 'cpo', 'bank_id' => (int) $recordId, 'direction' => 'outbound'])->id,
        'sale' => app(SalesOrderPaymentService::class)->createImmediatePaymentForCashSale(SalesOrder::findOrFail($recordId))?->id,
        'sequence' => array_map(fn (): string => SequentialNumber::next('audit', Payment::class, 'payment_number', 'AUDIT-', 6, 'AUDIT-%'), range(1, 10)),
    };
    echo json_encode(['status' => $result === null ? 'existing' : 'posted', 'result' => $result]);
} catch (RuntimeException $exception) {
    if (! str_contains($exception->getMessage(), 'Insufficient balance')) {
        throw $exception;
    }
    echo json_encode(['status' => 'rejected']);
}
