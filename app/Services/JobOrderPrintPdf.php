<?php

namespace App\Services;

use App\Models\InventoryItem;
use App\Models\JobOrder;
use App\Models\Setting;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class JobOrderPrintPdf
{
    /**
     * @var array<string, string>
     */
    private const SERVICE_LABELS = [
        'typing' => 'Typing',
        'layout_design' => 'Layout Design',
        'cover_design' => 'Cover Design',
        'selling_price' => 'Selling Price on Cover',
        'spine_has_text' => 'Spine has text',
        'cover_inner_printing' => 'Cover inner printing',
        'dont_insert_printer_name' => "Don't insert printer name",
        'cover_proof' => 'Cover Proof',
        'lamination' => 'Lamination',
        'page_no' => 'Number of Pages',
        'text_color_no' => 'Number of Colors (Text)',
        'cover_color_no' => 'Number of Colors (Cover)',
        'cover_ups' => 'Cover Ups',
        'books_per_package' => 'Books per Package',
        'binding_type' => 'Binding Type',
        'new_design' => 'New Design',
        'redesign' => 'Redesign',
        'new_dielines' => 'New dielines',
        'old_dielines' => 'Old dielines',
        'full_color' => 'Full color',
        'one_side_print' => 'One side print',
        'back_side_print' => 'Back side print',
        'work_and_turn' => 'Work and Turn',
        'amount_of_colors' => 'Amount of Colors',
        'printing_ups' => 'Printing Ups',
        'diecutting_ups' => 'Diecutting Ups',
        'numbering_ups' => 'Numbering Ups',
        'pieces_per_sheet' => 'Pieces per sheet',
        'colors_used' => 'Colors Used',
        'panton_no1' => 'Panton No 1',
        'panton_no2' => 'Panton No 2',
        'panton_no3' => 'Panton No 3',
    ];

    public function download(JobOrder $jobOrder): Response
    {
        $pdf = Pdf::loadView('job-orders.print', [
            'jobOrderPrint' => $this->dataFor($jobOrder),
        ])
            ->setPaper('a4')
            ->setOption('defaultFont', 'Arial')
            ->setOption('fontDir', public_path('fonts'))
            ->setOption('fontCache', public_path('fonts'))
            ->setOption('isRemoteEnabled', true);

        return $pdf->download($this->filenameFor($jobOrder));
    }

    /**
     * @return array{
     *     job_order_number: string,
     *     created_at: string|null,
     *     submission_date: string|null,
     *     due_date: string|null,
     *     app_name: string,
     *     company: array{name: string|null, phone: ?string, email: ?string, logo: ?string, logo_data_uri: ?string},
     *     client: array{name: string, phone: ?string, email: ?string, address: ?string},
     *     remarks: ?string,
     *     services: array<int, string>,
     *     tasks: array<int, array{name: string, quantity: int|float|null, size: ?string, instructions: ?string, materials: array<int, array{material_name: string, sku: ?string, unit: ?string, required_quantity: float, reserve_quantity: ?float}>}>
     * }
     */
    public function dataFor(JobOrder $jobOrder): array
    {
        $jobOrder->loadMissing([
            'partner',
            'jobOrderTasks.materialRequests.inventoryItem',
        ]);

        $inventoryItems = $this->inventoryItemsForPaper($jobOrder);
        $companyInfo = Setting::getSettings()->getCompanyInfo();

        return [
            'job_order_number' => $jobOrder->job_order_number,
            'created_at' => $jobOrder->created_at?->format('Y-m-d'),
            'submission_date' => $jobOrder->submission_date?->format('Y-m-d'),
            'due_date' => $jobOrder->due_date?->format('Y-m-d'),
            'app_name' => config('app.name', 'Packledge'),
            'company' => [
                'name' => $companyInfo['name'] ?? null,
                'phone' => $companyInfo['phone'] ?? null,
                'email' => $companyInfo['email'] ?? null,
                'logo' => $companyInfo['logo'] ?? null,
                'logo_data_uri' => $companyInfo['logo_data_uri'] ?? null,
            ],
            'client' => [
                'name' => $jobOrder->partner?->name ?? 'Internal Job',
                'phone' => $jobOrder->partner?->phone,
                'email' => $jobOrder->partner?->email,
                'address' => $jobOrder->partner?->address,
            ],
            'remarks' => $jobOrder->remarks,
            'services' => $this->servicesFor($jobOrder),
            'tasks' => $jobOrder->jobOrderTasks
                ->map(fn ($task): array => [
                    'name' => $task->name,
                    'quantity' => $task->quantity,
                    'size' => $task->size,
                    'instructions' => $task->instructions,
                    'materials' => $this->materialsFor($task, $inventoryItems),
                ])
                ->values()
                ->all(),
        ];
    }

    private function filenameFor(JobOrder $jobOrder): string
    {
        $jobOrderNumber = Str::of($jobOrder->job_order_number ?: "job-order-{$jobOrder->id}")
            ->replaceMatches('/[^A-Za-z0-9_-]+/', '-')
            ->trim('-')
            ->lower();

        return "job-order-{$jobOrderNumber}.pdf";
    }

    /**
     * @return array<int, string>
     */
    private function servicesFor(JobOrder $jobOrder): array
    {
        return collect($jobOrder->services ?? [])
            ->map(function ($service, int|string $key): ?string {
                if (is_string($service)) {
                    if (is_int($key)) {
                        return $service;
                    }

                    return filled($service)
                        ? $this->formatServiceValue($key, $service)
                        : null;
                }

                if (is_array($service)) {
                    if (! is_int($key)) {
                        return $this->formatServiceValue($key, implode(', ', array_filter($service)));
                    }

                    return $service['name']
                        ?? $service['service']
                        ?? $service['label']
                        ?? collect($service)->filter()->implode(' - ');
                }

                if (is_bool($service)) {
                    return $service ? $this->serviceLabel($key) : null;
                }

                if (is_numeric($service)) {
                    return $this->formatServiceValue($key, (string) $service);
                }

                return null;
            })
            ->filter()
            ->values()
            ->all();
    }

    private function formatServiceValue(int|string $key, string $value): string
    {
        return $this->serviceLabel($key).': '.$value;
    }

    private function serviceLabel(int|string $key): string
    {
        if (is_int($key)) {
            return 'Service';
        }

        return self::SERVICE_LABELS[$key] ?? Str::of($key)->replace('_', ' ')->headline()->value();
    }

    private function inventoryItemsForPaper(JobOrder $jobOrder): Collection
    {
        $inventoryItemIds = $jobOrder->jobOrderTasks
            ->flatMap(fn ($task) => collect($task->paper ?? [])->pluck('inventory_item_id'))
            ->filter()
            ->unique()
            ->values();

        if ($inventoryItemIds->isEmpty()) {
            return collect();
        }

        return InventoryItem::query()
            ->whereIn('id', $inventoryItemIds)
            ->get()
            ->keyBy('id');
    }

    /**
     * @return array<int, array{material_name: string, sku: ?string, unit: ?string, required_quantity: float, reserve_quantity: ?float}>
     */
    private function materialsFor($task, Collection $paperInventoryItems): array
    {
        $requestMaterials = $task->materialRequests
            ->map(fn ($request): array => [
                'material_name' => $request->inventoryItem?->name ?? 'Unknown material',
                'sku' => $request->inventoryItem?->sku,
                'unit' => $request->inventoryItem?->unit,
                'required_quantity' => (float) $request->required_quantity,
                'reserve_quantity' => null,
            ]);

        $paperMaterials = collect($task->paper ?? [])
            ->map(function (array $material) use ($paperInventoryItems): array {
                $inventoryItem = $paperInventoryItems->get($material['inventory_item_id'] ?? null);

                return [
                    'material_name' => $inventoryItem?->name ?? ($material['material_name'] ?? 'Unknown material'),
                    'sku' => $inventoryItem?->sku,
                    'unit' => $inventoryItem?->unit,
                    'required_quantity' => (float) ($material['required_quantity'] ?? 0),
                    'reserve_quantity' => isset($material['reserve_quantity']) ? (float) $material['reserve_quantity'] : null,
                ];
            });

        return $requestMaterials
            ->concat($paperMaterials)
            ->unique(fn (array $material): string => implode('|', [
                $material['material_name'],
                $material['sku'] ?? '',
                $material['required_quantity'],
                $material['reserve_quantity'] ?? '',
            ]))
            ->values()
            ->all();
    }
}
