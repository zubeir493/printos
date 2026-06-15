<?php

use App\Filament\Resources\Proformas\Pages\CreateProforma;
use App\Filament\Resources\Proformas\Pages\ListProformas;
use App\Mail\ProformaGenerated;
use App\Models\Bank;
use App\Models\Partner;
use App\Models\Proforma;
use App\Models\ProformaTask;
use App\Models\Setting;
use App\Models\User;
use App\Services\Proformas\ProformaPdfService;
use App\Services\Proformas\ProformaWorkflowService;
use App\UserRole;
use Filament\Facades\Filament;
use Filament\Forms\Components\Repeater;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('creates one linked job order from an approved proforma', function (): void {
    Setting::createDefault();

    $customer = Partner::factory()->create(['is_customer' => true]);
    $proforma = Proforma::factory()->create([
        'partner_id' => $customer->id,
        'status' => 'approved',
        'job_type' => 'packages',
        'subtotal' => 1000,
        'tax_amount' => 150,
        'total' => 1150,
    ]);
    ProformaTask::factory()->for($proforma)->create([
        'name' => 'Carton',
        'quantity' => 100,
        'unit_price' => 10,
        'task_cost' => 1000,
        'paper' => [['inventory_item_id' => null, 'required_quantity' => 20]],
        'deliverables' => [['label' => 'Dieline', 'type' => 'artwork']],
    ]);

    $jobOrder = app(ProformaWorkflowService::class)->createJobOrder($proforma);

    expect($jobOrder->proforma_id)->toBe($proforma->id)
        ->and($jobOrder->partner_id)->toBe($customer->id)
        ->and($jobOrder->jobOrderTasks)->toHaveCount(1)
        ->and($jobOrder->jobOrderTasks->first()->deliverables[0]['label'])->toBe('Dieline')
        ->and($proforma->refresh()->status)->toBe('job_order_created');

    expect(fn () => app(ProformaWorkflowService::class)->createJobOrder($proforma->refresh()))
        ->toThrow(RuntimeException::class);
});

it('stores and emails a proforma pdf privately', function (): void {
    Setting::createDefault();
    Storage::fake('local');
    Mail::fake();

    Bank::create([
        'name' => 'Commercial Bank of Ethiopia',
        'code' => 'CBE',
        'account_number' => '1000563430277',
        'account_holder_name' => 'HiPrint Trading PLC',
        'bank_name' => 'CBE',
        'status' => 'active',
    ]);

    $customer = Partner::factory()->create([
        'is_customer' => true,
        'email' => 'customer@example.com',
    ]);
    $proforma = Proforma::factory()->create([
        'partner_id' => $customer->id,
        'status' => 'draft',
    ]);
    ProformaTask::factory()->for($proforma)->create([
        'name' => 'Book',
        'quantity' => 10,
        'unit_price' => 20,
        'task_cost' => 200,
    ]);

    app(ProformaPdfService::class)->email($proforma, 'customer@example.com', 'Please review.');

    $proforma->refresh();

    Storage::disk('local')->assertExists($proforma->file_path);
    Mail::assertSent(ProformaGenerated::class);

    $mailHtml = (new ProformaGenerated([
        'proforma_data' => ['proforma_number' => $proforma->proforma_number],
    ], ['message' => 'Please review.']))->render();
    $pdfData = app(ProformaPdfService::class)->dataFor($proforma);

    expect($proforma->status)->toBe('sent')
        ->and($proforma->email_recipient)->toBe('customer@example.com')
        ->and($mailHtml)->toContain('Please review.')
        ->and($pdfData['amount_in_words'])->toBe('One thousand one hundred fifty birr')
        ->and($pdfData['bank_accounts'])->toContain([
            'name' => 'CBE',
            'account_number' => '1000563430277',
        ]);
});

it('returns a temporary local download route for a proforma pdf', function (): void {
    Setting::createDefault();
    Storage::disk('local')->deleteDirectory('proformas-temp');

    $this->actingAs(User::factory()->create([
        'role' => UserRole::Admin,
    ]));

    $proforma = Proforma::factory()->create();

    $downloadUrl = app(ProformaPdfService::class)->downloadUrl($proforma);

    expect($downloadUrl)->toBe(route('proformas.download', $proforma));

    $response = $this->get($downloadUrl);

    $response->assertOk()
        ->assertDownload();

    ob_start();
    $response->send();
    ob_end_clean();

    expect(Storage::disk('local')->allFiles('proformas-temp'))->toBeEmpty();
});

it('creates proformas from a full page with a previewed number', function (): void {
    Setting::createDefault();
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Model::shouldBeStrict();

    $this->actingAs(User::factory()->create([
        'role' => UserRole::Admin,
    ]));

    $customer = Partner::factory()->create(['is_customer' => true]);
    $undoRepeaterFake = Repeater::fake();

    try {
        Livewire::test(CreateProforma::class)
            ->assertFormSet([
                'proforma_number' => 'PF-'.now()->format('Y').'-000001',
            ])
            ->set('data.partner_id', $customer->id)
            ->set('data.job_type', 'books')
            ->set('data.issue_date', now()->toDateString())
            ->set('data.expiry_date', now()->addDays(3)->toDateString())
            ->set('data.tasks.0.name', 'Book')
            ->set('data.tasks.0.quantity', 10)
            ->set('data.tasks.0.size', 'Pcs')
            ->set('data.tasks.0.unit_price', 20)
            ->set('data.tasks.1.name', 'Cover')
            ->set('data.tasks.1.quantity', 2)
            ->set('data.tasks.1.size', 'Pcs')
            ->set('data.tasks.1.unit_price', 50)
            ->set('data.tasks.1.deliverables', [])
            ->set('data.remarks', 'Delivery after payment.')
            ->call('create')
            ->assertHasNoFormErrors()
            ->assertNotified()
            ->assertRedirect();
    } finally {
        $undoRepeaterFake();
        Model::shouldBeStrict(false);
    }

    $proforma = Proforma::query()->firstOrFail();

    expect($proforma->proforma_number)->toBe('PF-'.now()->format('Y').'-000001')
        ->and($proforma->status)->toBe('draft')
        ->and($proforma->email_recipient)->toBeNull()
        ->and($proforma->tasks)->toHaveCount(2);
});

it('creates proformas when a deliverables row is present but left blank', function (): void {
    Setting::createDefault();
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $this->actingAs(User::factory()->create([
        'role' => UserRole::Admin,
    ]));

    $customer = Partner::factory()->create(['is_customer' => true]);
    $undoRepeaterFake = Repeater::fake();

    try {
        Livewire::test(CreateProforma::class)
            ->set('data.partner_id', $customer->id)
            ->set('data.job_type', 'books')
            ->set('data.issue_date', now()->toDateString())
            ->set('data.expiry_date', now()->addDays(3)->toDateString())
            ->set('data.tasks.0.name', 'Book')
            ->set('data.tasks.0.quantity', 10)
            ->set('data.tasks.0.size', 'Pcs')
            ->set('data.tasks.0.unit_price', 20)
            ->set('data.tasks.0.deliverables.0.label', null)
            ->set('data.tasks.0.deliverables.0.type', 'artwork')
            ->call('create')
            ->assertHasNoFormErrors();
    } finally {
        $undoRepeaterFake();
    }

    expect(Proforma::count())->toBe(1)
        ->and(ProformaTask::query()->first()->deliverables)->toEqual([
            [
                'label' => null,
                'type' => 'artwork',
            ],
        ]);
});

it('does not show create and create another for proformas', function (): void {
    $property = new ReflectionProperty(CreateProforma::class, 'canCreateAnother');
    $property->setAccessible(true);

    expect($property->getValue())->toBeFalse();
});

it('updates proforma totals dynamically while editing the form', function (): void {
    Setting::createDefault();
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $this->actingAs(User::factory()->create([
        'role' => UserRole::Admin,
    ]));

    $customer = Partner::factory()->create(['is_customer' => true]);
    $undoRepeaterFake = Repeater::fake();

    try {
        Livewire::test(CreateProforma::class)
            ->set('data.partner_id', $customer->id)
            ->set('data.job_type', 'books')
            ->set('data.issue_date', now()->toDateString())
            ->set('data.expiry_date', now()->addDays(3)->toDateString())
            ->set('data.tasks.0.name', 'Book')
            ->set('data.tasks.0.quantity', 10)
            ->set('data.tasks.0.size', 'Pcs')
            ->set('data.tasks.0.unit_price', 20)
            ->assertFormSet(function (array $state): void {
                $task = $state['tasks'][0];

                expect($task['name'])->toBe('Book')
                    ->and($task['quantity'])->toBe(10)
                    ->and($task['size'])->toBe('Pcs')
                    ->and($task['unit_price'])->toBe(20)
                    ->and($task['task_cost'])->toBe(200.0)
                    ->and($state['subtotal'])->toBe(200.0)
                    ->and($state['tax_amount'])->toBe(30.0)
                    ->and($state['total'])->toBe(230.0);
            });
    } finally {
        $undoRepeaterFake();
    }
});

it('emails a proforma from the list table action', function (): void {
    Setting::createDefault();
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Storage::fake('local');
    Mail::fake();

    $this->actingAs(User::factory()->create([
        'role' => UserRole::Admin,
    ]));

    $customer = Partner::factory()->create([
        'is_customer' => true,
        'email' => 'customer@example.com',
    ]);
    $proforma = Proforma::factory()->create([
        'partner_id' => $customer->id,
        'status' => 'draft',
    ]);
    ProformaTask::factory()->for($proforma)->create([
        'name' => 'Book',
        'quantity' => 10,
        'unit_price' => 20,
        'task_cost' => 200,
    ]);

    Livewire::test(ListProformas::class)
        ->callTableAction('email', $proforma, [
            'email' => 'customer@example.com',
            'message' => 'Please review.',
        ])
        ->assertNotified('Proforma emailed');

    Mail::assertSent(ProformaGenerated::class, function (ProformaGenerated $mail): bool {
        return $mail->hasTo('customer@example.com');
    });

    expect($proforma->refresh()->status)->toBe('sent')
        ->and($proforma->email_recipient)->toBe('customer@example.com');
});

it('renders the proforma list without lazy loading the partner relation', function (): void {
    Setting::createDefault();
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $this->actingAs(User::factory()->create([
        'role' => UserRole::Admin,
    ]));

    $customer = Partner::factory()->create(['is_customer' => true]);
    Proforma::factory()->create([
        'partner_id' => $customer->id,
        'job_type' => 'books',
    ]);

    try {
        Model::shouldBeStrict();

        Livewire::test(ListProformas::class)
            ->assertSuccessful();
    } finally {
        Model::shouldBeStrict(false);
    }
});

it('removes the legacy cost calculation file column from job orders', function (): void {
    expect(Schema::hasColumn('job_orders', 'cost_calc_file'))->toBeFalse()
        ->and(Schema::hasColumn('job_orders', 'proforma_id'))->toBeTrue();
});
