<?php

use App\Models\InventoryItem;
use App\Models\JobOrder;
use App\Models\JobOrderTask;
use App\Models\MaterialRequest;
use App\Models\Partner;
use App\Models\User;
use App\Services\JobOrderPrintPdf;
use App\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('builds job order print data without money or status fields', function () {
    $partner = Partner::factory()->create([
        'name' => 'Acme Print Buyer',
        'phone' => '555-1000',
        'email' => 'buyer@example.com',
        'address' => 'Main Street',
    ]);

    $jobOrder = JobOrder::factory()->create([
        'partner_id' => $partner->id,
        'job_order_number' => 'JO-PRINT-001',
        'services' => [
            'lamination' => true,
            'page_no' => '48',
            'binding_type' => 'Saddle stitch',
        ],
        'remarks' => 'Use approved artwork only.',
        'submission_date' => '2026-05-25',
        'due_date' => '2026-05-28',
        'subtotal' => 2500,
        'tax_amount' => 375,
        'total' => 2875,
        'status' => 'active',
    ]);

    $inventoryItem = InventoryItem::factory()->create([
        'name' => 'Art Card',
        'sku' => 'ART-CARD',
        'unit' => 'sheet',
        'type' => 'raw_material',
    ]);

    $task = JobOrderTask::factory()->create([
        'job_order_id' => $jobOrder->id,
        'name' => 'Cover Printing',
        'quantity' => 500,
        'size' => 'A4',
        'instructions' => 'Full color',
    ]);

    MaterialRequest::create([
        'job_order_task_id' => $task->id,
        'inventory_item_id' => $inventoryItem->id,
        'required_quantity' => 750,
        'requested_quantity' => 800,
        'issued_quantity' => 600,
        'reason' => 'Cover stock',
    ]);

    $data = app(JobOrderPrintPdf::class)->dataFor($jobOrder);

    expect($data)
        ->toHaveKeys(['job_order_number', 'created_at', 'submission_date', 'due_date', 'client', 'remarks', 'services', 'tasks'])
        ->not->toHaveKeys(['status', 'subtotal', 'tax_amount', 'total', 'balance', 'advance_amount'])
        ->and($data['app_name'])->toBe(config('app.name'))
        ->and($data['company'])->toHaveKeys(['name', 'phone', 'email'])
        ->and($data['client']['name'])->toBe('Acme Print Buyer')
        ->and($data['services'])->toBe(['Lamination', 'Number of Pages: 48', 'Binding Type: Saddle stitch'])
        ->and($data['tasks'][0]['materials'][0])->toMatchArray([
            'material_name' => 'Art Card',
            'sku' => 'ART-CARD',
            'unit' => 'sheet',
            'required_quantity' => 750.0,
        ])
        ->and($data['tasks'][0]['materials'][0])->not->toHaveKeys(['requested_quantity', 'issued_quantity', 'reason']);
});

it('downloads the print pdf for users who can view job orders', function () {
    $user = User::factory()->create([
        'role' => UserRole::Production,
    ]);

    $jobOrder = JobOrder::factory()->create();

    $this->actingAs($user)
        ->get(route('job-orders.print', $jobOrder))
        ->assertSuccessful()
        ->assertHeader('Content-Type', 'application/pdf')
        ->assertHeader('Content-Disposition', 'attachment; filename=job-order-'.strtolower($jobOrder->job_order_number).'.pdf');
});
