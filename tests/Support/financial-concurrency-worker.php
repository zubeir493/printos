<?php

use App\Models\Payment;
use App\Models\PayrollRun;
use App\Models\SalesOrder;
use App\Services\Hr\CalculatePayrollRun;
use App\Services\Hr\PostPayrollRun;
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
        'payroll' => app(PostPayrollRun::class)->handle(PayrollRun::findOrFail($recordId))->id,
        'recalculate' => app(CalculatePayrollRun::class)->handle(PayrollRun::findOrFail($recordId))->id,
        'bank' => Payment::factory()->create(['payment_number' => 'AUDIT-'.bin2hex(random_bytes(8)), 'amount' => 100,
            'method' => 'cpo', 'bank_id' => (int) $recordId, 'direction' => 'outbound'])->id,
        'sale' => app(SalesOrderPaymentService::class)->createImmediatePaymentForCashSale(SalesOrder::findOrFail($recordId))?->id,
        'sequence' => array_map(fn (): string => SequentialNumber::next('audit', Payment::class, 'payment_number', 'AUDIT-', 6, 'AUDIT-%'), range(1, 10)),
    };
    echo json_encode(['status' => $result === null ? 'existing' : 'posted', 'result' => $result]);
} catch (RuntimeException $exception) {
    if (! collect(['Insufficient balance', 'already covers', 'Only draft payroll', 'Confirm attendance review'])->contains(fn (string $message): bool => str_contains($exception->getMessage(), $message))) {
        throw $exception;
    }
    echo json_encode(['status' => 'rejected']);
}
