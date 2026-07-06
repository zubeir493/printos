<?php

namespace Database\Seeders;

use App\Models\JobOrder;
use App\Models\SalesOrder;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DemoPortfolioSeeder extends Seeder
{
    private CarbonImmutable $now;

    public function run(): void
    {
        $this->now = CarbonImmutable::parse('2026-06-24 09:00:00');

        DB::transaction(function (): void {
            $partners = $this->partners();
            $users = $this->users();
            $warehouses = $this->warehouses();
            $items = $this->inventoryItems();
            $machines = $this->machines();
            $accounts = $this->accounts();
            $banks = $this->banks();

            $this->seedEmployees();
            $this->seedInventory($warehouses, $items);
            $jobs = $this->seedSalesAndProduction($partners, $users, $warehouses, $items, $machines);
            $purchaseOrders = $this->seedPurchasing($partners, $warehouses, $items);
            $salesOrders = $this->seedRetailSales($partners, $warehouses, $items);
            $this->seedFinance($partners, $accounts, $banks, $jobs, $purchaseOrders, $salesOrders);
            $this->seedBids($partners, $banks);
        });
    }

    /**
     * @return array<string, int>
     */
    private function partners(): array
    {
        return DB::table('partners')
            ->whereIn('tin_number', [
                'TIN-AAU-001',
                'TIN-ETH-002',
                'TIN-CBE-003',
                'TIN-MPT-101',
                'TIN-NPS-102',
                'TIN-AIC-103',
            ])
            ->pluck('id', 'tin_number')
            ->all();
    }

    /**
     * @return array<string, int>
     */
    private function users(): array
    {
        return DB::table('users')->pluck('id', 'role')->all();
    }

    /**
     * @return array<string, int>
     */
    private function warehouses(): array
    {
        return DB::table('warehouses')->pluck('id', 'code')->all();
    }

    /**
     * @return array<string, object>
     */
    private function inventoryItems(): array
    {
        return DB::table('inventory_items')
            ->get()
            ->keyBy('sku')
            ->all();
    }

    /**
     * @return array<string, int>
     */
    private function machines(): array
    {
        return DB::table('machines')->pluck('id', 'code')->all();
    }

    /**
     * @return array<string, int>
     */
    private function accounts(): array
    {
        return DB::table('accounts')->pluck('id', 'code')->all();
    }

    /**
     * @return array<string, int>
     */
    private function banks(): array
    {
        return DB::table('banks')->pluck('id', 'code')->all();
    }

    private function seedEmployees(): void
    {
        $employees = [
            ['employee_id' => 'EMP-1001', 'first_name' => 'Mekdes', 'last_name' => 'Tadesse', 'department' => 'Design', 'position' => 'Prepress Designer', 'basic_salary' => 28500],
            ['employee_id' => 'EMP-1002', 'first_name' => 'Abel', 'last_name' => 'Bekele', 'department' => 'Production', 'position' => 'Press Operator', 'basic_salary' => 32000],
            ['employee_id' => 'EMP-1003', 'first_name' => 'Hana', 'last_name' => 'Tesfaye', 'department' => 'Warehouse', 'position' => 'Store Keeper', 'basic_salary' => 24500],
            ['employee_id' => 'EMP-1004', 'first_name' => 'Dawit', 'last_name' => 'Kebede', 'department' => 'Finance', 'position' => 'Accountant', 'basic_salary' => 36000],
        ];

        foreach ($employees as $employee) {
            $this->updateOrCreate('employees', ['employee_id' => $employee['employee_id']], [
                ...$employee,
                'attendance_device_id' => str_replace('EMP-', 'BIO-', $employee['employee_id']),
                'phone' => '+251911'.substr($employee['employee_id'], -4),
                'hire_date' => $this->now->subMonths(8)->toDateString(),
                'status' => 'active',
                'employment_type' => 'permanent',
                'pension_enabled' => true,
                'transport_allowance' => 2500,
                'payment_method' => 'bank',
                'bank_name' => 'Bank of Abyssinia',
                'account_number' => '9000'.substr($employee['employee_id'], -4),
            ]);
        }
    }

    /**
     * @param  array<string, int>  $warehouses
     * @param  array<string, object>  $items
     */
    private function seedInventory(array $warehouses, array $items): void
    {
        $balances = [
            'PAPER-A4-80' => 185000,
            'PAPER-A4-120G' => 96000,
            'PAPER-A5-80' => 122000,
            'STICKER-A4' => 18500,
            'INK-BLK' => 82,
            'DUPLEX-70X100' => 4300,
        ];

        foreach ($balances as $sku => $quantity) {
            $item = $items[$sku];

            $this->updateOrCreate('inventory_balances', [
                'inventory_item_id' => $item->id,
                'warehouse_id' => $warehouses['MAIN'],
            ], [
                'quantity_on_hand' => $quantity,
            ]);

            $this->updateOrCreate('stock_movements', [
                'inventory_item_id' => $item->id,
                'warehouse_id' => $warehouses['MAIN'],
                'reference_type' => 'demo-opening',
                'reference_id' => $item->id,
            ], [
                'type' => 'adjustment',
                'quantity' => $quantity,
                'unit_cost' => $item->price,
                'total_cost' => round($quantity * (float) $item->price, 2),
                'movement_date' => $this->now->subDays(18),
            ]);
        }
    }

    /**
     * @param  array<string, int>  $partners
     * @param  array<string, int>  $users
     * @param  array<string, int>  $warehouses
     * @param  array<string, object>  $items
     * @param  array<string, int>  $machines
     * @return array<string, int>
     */
    private function seedSalesAndProduction(array $partners, array $users, array $warehouses, array $items, array $machines): array
    {
        $jobs = [];

        $definitions = [
            [
                'estimate' => 'CE-PORT-1001',
                'proforma' => 'PF-PORT-1001',
                'job' => 'JO-PORT-1001',
                'partner' => 'TIN-AAU-001',
                'description' => 'Admissions booklet and envelope package',
                'job_type' => 'books',
                'quantity' => 12000,
                'status' => 'active',
                'task_status' => 'production',
                'task' => 'Admissions booklet, 48 pages',
                'cost' => 486000,
                'material' => 'PAPER-A4-80',
                'required' => 72000,
                'issued' => 52000,
            ],
            [
                'estimate' => 'CE-PORT-1002',
                'proforma' => 'PF-PORT-1002',
                'job' => 'JO-PORT-1002',
                'partner' => 'TIN-ETH-002',
                'description' => 'Cargo handling sticker labels',
                'job_type' => 'labels',
                'quantity' => 45000,
                'status' => 'active',
                'task_status' => 'design',
                'task' => 'Weatherproof sticker sheet',
                'cost' => 328500,
                'material' => 'STICKER-A4',
                'required' => 18000,
                'issued' => 7200,
            ],
            [
                'estimate' => 'CE-PORT-1003',
                'proforma' => 'PF-PORT-1003',
                'job' => 'JO-PORT-1003',
                'partner' => 'TIN-CBE-003',
                'description' => 'Branch poster and teller pad set',
                'job_type' => 'posters',
                'quantity' => 8500,
                'status' => 'completed',
                'task_status' => 'completed',
                'task' => 'A3 campaign poster',
                'cost' => 214000,
                'material' => 'PAPER-A4-120G',
                'required' => 24000,
                'issued' => 24000,
            ],
        ];

        foreach ($definitions as $index => $definition) {
            $subtotal = (float) $definition['cost'];
            $tax = round($subtotal * 0.15, 2);
            $total = $subtotal + $tax;
            $issueDate = $this->now->subDays(12 - $index * 3);

            $estimateId = $this->updateOrCreate('cost_estimates', ['estimate_number' => $definition['estimate']], [
                'job_type' => $definition['job_type'],
                'partner_id' => $partners[$definition['partner']],
                'description' => $definition['description'],
                'quantity' => $definition['quantity'],
                'deadline' => $issueDate->addDays(12)->toDateString(),
                'services' => json_encode(['design', 'plate', 'print', 'finish']),
                'remarks' => 'Demo portfolio estimate with material and machine costing.',
                'subtotal' => $subtotal,
                'overhead_amount' => round($subtotal * 0.08, 2),
                'profit_amount' => round($subtotal * 0.18, 2),
                'vat_rate' => 15,
                'tax_amount' => $tax,
                'total' => $total,
                'unit_price' => round($total / $definition['quantity'], 4),
                'margin_percent' => 24,
                'status' => 'finalized',
                'finalized_at' => $issueDate,
            ]);

            $proformaId = $this->updateOrCreate('proformas', ['proforma_number' => $definition['proforma']], [
                'cost_estimate_id' => $estimateId,
                'partner_id' => $partners[$definition['partner']],
                'job_type' => $definition['job_type'],
                'services' => json_encode(['design', 'prepress', 'printing', 'finishing']),
                'issue_date' => $issueDate->toDateString(),
                'expiry_date' => $issueDate->addDays(14)->toDateString(),
                'remarks' => 'Approved demo proforma for portfolio screenshots.',
                'subtotal' => $subtotal,
                'tax_amount' => $tax,
                'total' => $total,
                'status' => 'approved',
                'approved_at' => $issueDate->addDay(),
                'approved_by' => $users['admin'],
            ]);

            $jobId = $this->updateOrCreate('job_orders', ['job_order_number' => $definition['job']], [
                'proforma_id' => $proformaId,
                'partner_id' => $partners[$definition['partner']],
                'job_type' => $definition['job_type'],
                'production_mode' => 'make_to_order',
                'services' => json_encode(['print', 'cut', 'finish']),
                'submission_date' => $issueDate->addDays(2)->toDateString(),
                'due_date' => $issueDate->addDays(10)->toDateString(),
                'remarks' => 'Demo production job seeded for portfolio screenshots.',
                'advance_paid' => true,
                'advance_amount' => round($total * 0.4, 2),
                'subtotal' => $subtotal,
                'tax_amount' => $tax,
                'total' => $total,
                'status' => $definition['status'],
            ]);

            $taskId = $this->updateOrCreate('job_order_tasks', [
                'job_order_id' => $jobId,
                'name' => $definition['task'],
            ], [
                'designer_id' => $users['design'],
                'typist_id' => $users['typist'] ?? null,
                'quantity' => $definition['quantity'],
                'task_cost' => $subtotal,
                'paper' => json_encode([[
                    'inventory_item_id' => $items[$definition['material']]->id,
                    'required_quantity' => $definition['required'],
                    'reserve_quantity' => 0,
                ]]),
                'deliverables' => json_encode([
                    ['type' => 'artwork', 'label' => 'Print-ready artwork'],
                    ['type' => 'text_file', 'label' => 'Copy deck'],
                ]),
                'status' => $definition['task_status'],
                'size' => $index === 1 ? 'A4 sticker sheet' : 'A4',
                'instructions' => 'Portfolio demo task with approved deliverables and material demand.',
            ]);

            $this->updateOrCreate('material_requests', [
                'job_order_task_id' => $taskId,
                'inventory_item_id' => $items[$definition['material']]->id,
            ], [
                'required_quantity' => $definition['required'],
                'requested_quantity' => $definition['required'],
                'issued_quantity' => $definition['issued'],
                'reason' => 'Planned material issue for active production.',
            ]);

            $this->updateOrCreate('artworks', ['filename' => $definition['job'].'-artwork.pdf'], [
                'job_order_id' => $jobId,
                'job_order_task_id' => $taskId,
                'deliverable' => 'Print-ready artwork',
                'is_approved' => $index !== 1,
                'uploaded_by' => $users['design'],
            ]);

            $this->updateOrCreate('text_files', ['filename' => $definition['job'].'-copy.docx'], [
                'job_order_task_id' => $taskId,
                'uploaded_by' => $users['typist'] ?? $users['design'],
                'deliverable' => 'Copy deck',
                'original_name' => $definition['description'].'.docx',
                'is_approved' => true,
            ]);

            $this->updateOrCreate('stock_movements', [
                'inventory_item_id' => $items[$definition['material']]->id,
                'warehouse_id' => $warehouses['MAIN'],
                'reference_type' => JobOrder::class,
                'reference_id' => $jobId,
            ], [
                'type' => 'consumption',
                'quantity' => -abs($definition['issued']),
                'unit_cost' => $items[$definition['material']]->price,
                'total_cost' => round(abs($definition['issued']) * (float) $items[$definition['material']]->price, 2),
                'movement_date' => $issueDate->addDays(3),
            ]);

            if ($index === 2) {
                $dispatchId = $this->updateOrCreate('dispatches', ['job_order_id' => $jobId], [
                    'warehouse_id' => $warehouses['MAIN'],
                    'delivery_date' => $issueDate->addDays(9)->toDateString(),
                    'status' => 'delivered',
                    'remarks' => 'Delivered to customer receiving desk.',
                ]);

                $this->updateOrCreate('dispatch_items', [
                    'dispatch_id' => $dispatchId,
                    'job_order_task_id' => $taskId,
                ], [
                    'quantity' => $definition['quantity'],
                ]);
            }

            $jobs[$definition['job']] = $jobId;
        }

        $planId = $this->updateOrCreate('production_plans', ['week_start' => '2026-06-22'], [
            'week_end' => '2026-06-28',
            'status' => 'approved',
        ]);

        $planMachineId = $this->updateOrCreate('production_plan_machines', [
            'production_plan_id' => $planId,
            'machine_id' => $machines['PRESS-SM74'],
        ], []);

        foreach ($jobs as $jobId) {
            $taskId = DB::table('job_order_tasks')->where('job_order_id', $jobId)->value('id');

            $this->updateOrCreate('production_plan_items', [
                'production_plan_id' => $planId,
                'job_order_task_id' => $taskId,
            ], [
                'production_plan_machine_id' => $planMachineId,
                'machine_id' => $machines['PRESS-SM74'],
                'planned_quantity' => DB::table('job_order_tasks')->where('id', $taskId)->value('quantity'),
                'planned_plates' => 4,
                'planned_rounds' => 18,
            ]);
        }

        $this->updateOrCreate('production_reports', ['production_plan_id' => $planId], [
            'status' => 'submitted',
        ]);

        return $jobs;
    }

    /**
     * @param  array<string, int>  $partners
     * @param  array<string, int>  $warehouses
     * @param  array<string, object>  $items
     * @return array<string, int>
     */
    private function seedPurchasing(array $partners, array $warehouses, array $items): array
    {
        $poId = $this->updateOrCreate('purchase_orders', ['po_number' => 'PO-PORT-1001'], [
            'partner_id' => $partners['TIN-MPT-101'],
            'order_date' => '2026-06-10',
            'due_date' => '2026-06-25',
            'status' => 'received',
            'subtotal' => 620000,
            'tax_amount' => 93000,
            'total' => 713000,
        ]);

        $lines = [
            ['sku' => 'PAPER-A4-80', 'quantity' => 600, 'received' => 600, 'unit_price' => 650],
            ['sku' => 'STICKER-A4', 'quantity' => 120, 'received' => 80, 'unit_price' => 1800],
        ];

        foreach ($lines as $line) {
            $item = $items[$line['sku']];
            $lineId = $this->updateOrCreate('purchase_order_items', [
                'purchase_order_id' => $poId,
                'inventory_item_id' => $item->id,
            ], [
                'quantity' => $line['quantity'],
                'received_quantity' => $line['received'],
                'unit_price' => $line['unit_price'],
                'total' => $line['quantity'] * $line['unit_price'],
                'status' => $line['received'] >= $line['quantity'] ? 'received' : 'partial',
            ]);

            $receiptId = $this->updateOrCreate('goods_receipts', ['receipt_number' => 'GR-PORT-1001'], [
                'purchase_order_id' => $poId,
                'warehouse_id' => $warehouses['MAIN'],
                'receipt_date' => '2026-06-18',
                'status' => 'posted',
                'posted_at' => '2026-06-18 15:20:00',
            ]);

            $this->updateOrCreate('goods_receipt_items', [
                'goods_receipt_id' => $receiptId,
                'purchase_order_item_id' => $lineId,
            ], [
                'quantity_received' => $line['received'],
            ]);
        }

        return ['PO-PORT-1001' => $poId];
    }

    /**
     * @param  array<string, int>  $partners
     * @param  array<string, int>  $warehouses
     * @param  array<string, object>  $items
     * @return array<string, int>
     */
    private function seedRetailSales(array $partners, array $warehouses, array $items): array
    {
        $salesOrderId = $this->updateOrCreate('sales_orders', ['order_number' => 'SO-PORT-1001'], [
            'warehouse_id' => $warehouses['MAIN'],
            'partner_id' => $partners['TIN-CBE-003'],
            'order_date' => '2026-06-21',
            'due_date' => '2026-06-24',
            'payment_mode' => 'credit',
            'payment_method' => 'bank',
            'subtotal' => 175000,
            'tax_amount' => 26250,
            'total' => 201250,
            'status' => SalesOrder::STATUS_SUBMITTED,
        ]);

        $this->updateOrCreate('sales_order_items', [
            'sales_order_id' => $salesOrderId,
            'inventory_item_id' => $items['DUPLEX-70X100']->id,
        ], [
            'quantity' => 50,
            'unit_label' => 'ream',
            'unit_price' => 3500,
            'total' => 175000,
        ]);

        return ['SO-PORT-1001' => $salesOrderId];
    }

    /**
     * @param  array<string, int>  $partners
     * @param  array<string, int>  $accounts
     * @param  array<string, int>  $banks
     * @param  array<string, int>  $jobs
     * @param  array<string, int>  $purchaseOrders
     * @param  array<string, int>  $salesOrders
     */
    private function seedFinance(array $partners, array $accounts, array $banks, array $jobs, array $purchaseOrders, array $salesOrders): void
    {
        DB::table('payments')->where('payment_number', 'PAY-PORT-1001')->delete();

        $documents = [
            ['number' => 'INV-PORT-1001', 'type' => 'job_order', 'id' => $jobs['JO-PORT-1001'], 'partner' => 'TIN-AAU-001', 'total' => 558900, 'paid' => 220000, 'status' => 'partial'],
            ['number' => 'INV-PORT-1002', 'type' => 'sales_order', 'id' => $salesOrders['SO-PORT-1001'], 'partner' => 'TIN-CBE-003', 'total' => 201250, 'paid' => 0, 'status' => 'sent'],
            ['number' => 'PI-PORT-1001', 'type' => 'purchase_order', 'id' => $purchaseOrders['PO-PORT-1001'], 'partner' => 'TIN-MPT-101', 'total' => 713000, 'paid' => 350000, 'status' => 'partial', 'invoice_type' => 'purchase'],
        ];

        foreach ($documents as $document) {
            $invoiceId = $this->updateOrCreate('invoices', ['invoice_number' => $document['number']], [
                'invoice_type' => $document['invoice_type'] ?? 'sales',
                'order_id' => $document['id'],
                'order_type' => $document['type'],
                'partner_id' => $partners[$document['partner']],
                'invoice_date' => '2026-06-22',
                'due_date' => '2026-07-06',
                'subtotal' => round($document['total'] / 1.15, 2),
                'tax_amount' => round($document['total'] - ($document['total'] / 1.15), 2),
                'total_amount' => $document['total'],
                'balance_due' => $document['total'] - $document['paid'],
                'status' => $document['status'],
                'filename' => $document['number'].'.pdf',
                'file_path' => 'demo/invoices/'.$document['number'].'.pdf',
                'email_recipient' => 'finance@example.com',
            ]);

            if ($document['paid'] > 0) {
                $paymentPrefix = ($document['invoice_type'] ?? 'sales') === 'purchase' ? 'PAY-OUT' : 'PAY-IN';
                $paymentNumber = str_replace(['INV', 'PI'], $paymentPrefix, $document['number']);
                $this->updateOrCreate('payments', ['payment_number' => $paymentNumber], [
                    'partner_id' => $partners[$document['partner']],
                    'bank_id' => $banks['BOAN'],
                    'payment_date' => '2026-06-23',
                    'amount' => $document['paid'],
                    'direction' => ($document['invoice_type'] ?? 'sales') === 'purchase' ? 'outbound' : 'inbound',
                    'method' => 'bank',
                    'reference' => 'BNK-'.$paymentNumber,
                    'payable_type' => ($document['invoice_type'] ?? 'sales') === 'purchase' ? 'App\\Models\\PurchaseOrder' : 'App\\Models\\Invoice',
                    'payable_id' => ($document['invoice_type'] ?? 'sales') === 'purchase' ? $document['id'] : $invoiceId,
                    'transaction_type' => ($document['invoice_type'] ?? 'sales') === 'purchase' ? 'supplier_payment' : 'customer_receipt',
                    'payment_type' => 'standard',
                    'account_id' => $accounts['1010'],
                ]);
            }
        }

        $entryId = $this->updateOrCreate('journal_entries', ['reference' => 'JE-PORT-1001'], [
            'date' => '2026-06-23',
            'narration' => 'Demo portfolio sales and inventory activity',
            'total_debit' => 760150,
            'total_credit' => 760150,
            'status' => 'posted',
            'posted_at' => '2026-06-23 17:30:00',
        ]);

        foreach (
            [
                ['account' => '1200', 'debit' => 760150, 'credit' => 0],
                ['account' => '4000', 'debit' => 0, 'credit' => 660999.99],
                ['account' => '2100', 'debit' => 0, 'credit' => 99150.01],
            ] as $line
        ) {
            $this->updateOrCreate('journal_items', [
                'journal_entry_id' => $entryId,
                'account_id' => $accounts[$line['account']],
            ], [
                'debit' => $line['debit'],
                'credit' => $line['credit'],
            ]);
        }
    }

    /**
     * @param  array<string, int>  $partners
     * @param  array<string, int>  $banks
     */
    private function seedBids(array $partners, array $banks): void
    {
        $bidId = $this->updateOrCreate('bids', ['bid_number' => 'BID-PORT-1001'], [
            'title' => 'Annual school textbook printing tender',
            'tender_reference' => 'MOE/PRINT/2026/04',
            'partner_id' => $partners['TIN-AAU-001'],
            'status' => 'submitted',
            'submission_date' => '2026-06-20',
            'deadline_date' => '2026-06-27',
            'estimated_value' => 1850000,
            'bid_bond_percentage' => 2,
            'bid_bond_amount' => 37000,
            'bid_bond_issuing_partner_id' => $partners['TIN-CBE-003'],
            'bid_bond_expiry_date' => '2026-08-27',
            'notes' => 'Demo bid for portfolio screenshots.',
        ]);

        $this->updateOrCreate('bonds', ['reference' => 'CPO-PORT-1001'], [
            'type' => 'bid',
            'bid_id' => $bidId,
            'issuing_partner_id' => $partners['TIN-CBE-003'],
            'bank_id' => $banks['CBEN'],
            'amount' => 37000,
            'issue_date' => '2026-06-19',
            'expiry_date' => '2026-08-27',
            'status' => 'active',
            'notes' => 'Bid bond issued for textbook tender.',
        ]);
    }

    /**
     * @param  array<string, mixed>  $keys
     * @param  array<string, mixed>  $values
     */
    private function updateOrCreate(string $table, array $keys, array $values): int
    {
        $columns = Schema::getColumnListing($table);
        $keys = array_intersect_key($keys, array_flip($columns));
        $values = array_intersect_key($values, array_flip($columns));

        $timestampedValues = [
            ...$values,
            'updated_at' => $this->now,
        ];

        if (! DB::table($table)->where($keys)->exists()) {
            $timestampedValues['created_at'] = $this->now;
        }

        DB::table($table)->updateOrInsert($keys, $timestampedValues);

        return (int) DB::table($table)->where($keys)->value('id');
    }
}
