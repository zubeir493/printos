<?php

use App\Console\Commands\BackupDatabase;
use App\Models\Invoice;
use App\Models\JobOrder;
use App\Models\Payment;
use App\Services\InvoiceGeneratorService;
use App\Support\RequiredStorage;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;

uses(RefreshDatabase::class);

test('failed storage writes cannot be treated as successful uploads', function (): void {
    $disk = Mockery::mock(FilesystemAdapter::class);
    $disk->shouldReceive('put')->once()->andReturn(false);
    Storage::shouldReceive('disk')->with('s3')->andReturn($disk);
    expect(fn () => RequiredStorage::put('s3', 'test.pdf', 'pdf'))->toThrow(RuntimeException::class, 'Could not store');
});

test('a failed backup upload prevents success and retention cleanup', function (): void {
    $this->app->instance(BackupDatabase::class, new class extends BackupDatabase
    {
        protected function dumpProcess(array $command, array $environment): Process
        {
            return new Process([PHP_BINARY, '-r', 'echo "CREATE TABLE restore_probe (id INTEGER);";']);
        }
    });
    $disk = Mockery::mock(FilesystemAdapter::class);
    $disk->shouldReceive('put')->once()->andReturn(false);
    $disk->shouldNotReceive('files');
    $disk->shouldNotReceive('delete');
    Storage::shouldReceive('disk')->with('s3')->andReturn($disk);
    $this->artisan('backup:database')->expectsOutputToContain('Backup failed')->assertFailed();
});

test('backup streams a verifiable dump before retention runs', function (): void {
    Storage::fake('s3');
    $this->app->instance(BackupDatabase::class, new class extends BackupDatabase
    {
        protected function dumpProcess(array $command, array $environment): Process
        {
            return new Process([PHP_BINARY, '-r', 'echo "CREATE TABLE restore_probe (id INTEGER);";']);
        }
    });
    $this->artisan('backup:database')->assertSuccessful();
    $files = Storage::disk('s3')->files('backups/database');
    expect($files)->toHaveCount(1);
    $sql = Storage::disk('s3')->get($files[0]);
    $restore = new PDO('sqlite::memory:');
    $restore->exec($sql);
    expect($restore->query("SELECT name FROM sqlite_master WHERE name = 'restore_probe'")->fetchColumn())->toBe('restore_probe');
});

test('failed invoice storage does not create a successful invoice record', function (): void {
    config()->set('filesystems.private_disk', 's3');
    $job = JobOrder::factory()->create();
    $pdf = Mockery::mock(Barryvdh\DomPDF\PDF::class);
    $pdf->shouldReceive('setPaper', 'setOption')->andReturnSelf();
    $pdf->shouldReceive('output')->andReturn('pdf-content');
    Pdf::shouldReceive('loadView')->andReturn($pdf);
    $disk = Mockery::mock(FilesystemAdapter::class);
    $disk->shouldReceive('put')->once()->andReturn(false);
    Storage::shouldReceive('disk')->with('s3')->andReturn($disk);
    expect(fn () => app(InvoiceGeneratorService::class)->generateFromJobOrder($job))->toThrow(RuntimeException::class, 'Could not store');
    expect(Invoice::count())->toBe(0);
});

test('the reconciliation audit identifies historical CPO and missing files without changing records', function (): void {
    Storage::fake('s3');
    Storage::fake('local');
    config()->set('filesystems.private_disk', 's3');
    $payment = Payment::withoutEvents(fn () => Payment::factory()->create([
        'method' => 'cpo', 'bank_id' => null, 'amount' => 100, 'transaction_type' => 'customer_receipt',
    ]));
    Invoice::create(['invoice_number' => 'INV-MISSING', 'invoice_type' => 'sales', 'partner_id' => $payment->partner_id,
        'order_type' => 'sales_order', 'order_id' => 1, 'invoice_date' => today(), 'due_date' => today()->addDays(30), 'subtotal' => 100, 'tax_amount' => 0, 'total_amount' => 100,
        'balance_due' => 100, 'status' => 'draft', 'filename' => 'missing.pdf', 'file_path' => 'missing.pdf']);
    $original = $payment->fresh()->getAttributes();
    $this->artisan('finance:audit-records', ['--check-files' => true])
        ->expectsOutputToContain('CPO reconciliation candidates: 1')
        ->expectsOutputToContain('Missing generated files: 1')->assertSuccessful();
    expect($payment->fresh()->getAttributes())->toBe($original);
});
