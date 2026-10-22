<?php

use App\Models\Bank;
use App\Models\Partner;
use App\Models\Payment;
use App\Models\SalesOrder;
use App\Models\Warehouse;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;

beforeEach(function (): void {
    if (DB::getDriverName() !== 'mysql' || DB::connection()->getDatabaseName() !== 'printos_audit_testing') {
        $this->markTestSkipped('Run with phpunit.mysql.xml against the dedicated printos_audit_testing database.');
    }
    Artisan::call('migrate:fresh', ['--force' => true, '--no-interaction' => true]);
});

/** @return list<array{status: string, result?: mixed}> */
function runAuditWorkers(string $operation, int $recordId = 0): array
{
    $connection = config('database.connections.mysql');
    $environment = ['APP_ENV' => 'testing', 'DB_CONNECTION' => 'mysql', 'DB_DATABASE' => 'printos_audit_testing',
        'DB_HOST' => $connection['host'], 'DB_PORT' => (string) $connection['port'], 'DB_USERNAME' => $connection['username'],
        'DB_PASSWORD' => $connection['password'], 'CACHE_STORE' => 'array', 'QUEUE_CONNECTION' => 'sync', 'MAIL_MAILER' => 'array'];
    $gate = sys_get_temp_dir().'/erp-concurrency-'.bin2hex(random_bytes(8));
    $workers = [];
    try {
        foreach (range(1, 2) as $index) {
            $worker = new Process([PHP_BINARY, base_path('tests/Support/financial-concurrency-worker.php'), $operation, (string) $recordId, $gate], base_path(), $environment);
            $worker->setTimeout(30)->start();
            $workers[] = $worker;
        }
        $deadline = microtime(true) + 15;
        while (count(glob($gate.'.ready.*')) < 2) {
            if (microtime(true) > $deadline) {
                throw new RuntimeException('Workers failed to reach the concurrency barrier.');
            }
            usleep(10000);
        }
        touch($gate);

        return array_map(function (Process $worker): array {
            $worker->wait();
            expect($worker->isSuccessful())->toBeTrue($worker->getErrorOutput());

            return json_decode($worker->getOutput(), true, flags: JSON_THROW_ON_ERROR);
        }, $workers);
    } finally {
        foreach ($workers as $worker) {
            if ($worker->isRunning()) {
                $worker->stop();
            }
        }
        foreach (glob($gate.'*') as $file) {
            unlink($file);
        }
    }
}

test('concurrent withdrawals cannot overspend a bank', function (): void {
    $bank = Bank::create(['name' => 'Concurrency Bank', 'code' => 'CONCURRENT', 'account_number' => '12345678',
        'account_holder_name' => 'Pilot', 'bank_name' => 'Bank', 'branch' => 'Main', 'current_balance' => 150, 'status' => 'active']);
    $results = runAuditWorkers('bank', $bank->id);
    expect(collect($results)->pluck('status')->sort()->values()->all())->toBe(['posted', 'rejected'])
        ->and((float) $bank->fresh()->current_balance)->toBe(50.0)->and(Payment::count())->toBe(1);
});

test('concurrent allocations reserve unique document numbers before insertion', function (): void {
    $results = runAuditWorkers('sequence');
    $numbers = collect($results)->pluck('result')->flatten();
    expect($numbers)->toHaveCount(20)->and($numbers->unique())->toHaveCount(20);
});

test('concurrent cash sale receipt requests create one receipt', function (): void {
    $sale = SalesOrder::withoutEvents(fn (): SalesOrder => SalesOrder::create([
        'order_number' => 'SO-CONCURRENT', 'warehouse_id' => Warehouse::factory()->create()->id,
        'partner_id' => Partner::factory()->create()->id, 'order_date' => today(), 'total' => 100,
        'payment_mode' => 'cash', 'payment_method' => 'cash', 'status' => 'draft',
    ]));
    runAuditWorkers('sale', $sale->id);
    expect($sale->payments()->count())->toBe(1)->and((float) $sale->fresh()->paid_amount)->toBe(100.0);
});

test('a real MySQL backup restores its records into an isolated database', function (): void {
    Storage::fake('s3');
    Payment::factory()->create(['amount' => 100, 'method' => 'cash', 'direction' => 'inbound']);
    expect(Artisan::call('backup:database'))->toBe(0, Artisan::output());
    $files = Storage::disk('s3')->files('backups/database');
    expect($files)->toHaveCount(1);
    $connection = config('database.connections.mysql');
    DB::statement('CREATE DATABASE printos_audit_restore_testing');
    config()->set('database.connections.audit_restore', array_merge($connection, ['database' => 'printos_audit_restore_testing']));
    $stream = Storage::disk('s3')->readStream($files[0]);
    try {
        $restore = new Process(['mariadb', '-h', $connection['host'], '-P', (string) $connection['port'], '-u', $connection['username'],
            'printos_audit_restore_testing'], base_path(), ['MYSQL_PWD' => $connection['password']]);
        $restore->setTimeout(60)->setInput($stream)->run();
        expect($restore->isSuccessful())->toBeTrue($restore->getErrorOutput());
        expect(DB::connection('audit_restore')->table('payments')->count())->toBe(1);
        expect((float) DB::connection('audit_restore')->table('payments')->value('amount'))->toBe(100.0);
    } finally {
        if (is_resource($stream)) {
            fclose($stream);
        }
        DB::purge('audit_restore');
        DB::statement('DROP DATABASE printos_audit_restore_testing');
    }
});
