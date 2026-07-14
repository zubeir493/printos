<x-filament-panels::page>
    @php
        $summary = $this->summary();
        $rows = $this->payrollRows();
        $detail = $this->payrollDetail();
    @endphp

    <div class="space-y-6">
        <x-filament::section>
            <div class="grid gap-5 md:grid-cols-2 xl:grid-cols-[1.5fr_0.7fr_1.4fr]">
                <div class="min-w-0 md:col-span-2 xl:col-span-1">
                    <div class="text-sm font-medium text-gray-600 dark:text-gray-300">Period: {{ $summary['period'] }} | {{ $summary['base_days'] }} Base Days</div>
                    <div class="mt-5 grid gap-4 sm:grid-cols-2">
                        <div class="min-w-0">
                            <div class="break-words text-xl font-semibold text-gray-950 dark:text-white sm:text-2xl">{{ $this->money($summary['payroll_cost']) }}</div>
                            <div class="mt-1 text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Payroll cost</div>
                        </div>
                        <div class="min-w-0">
                            <div class="break-words text-xl font-semibold text-gray-950 dark:text-white sm:text-2xl">{{ $this->money($summary['net_pay']) }}</div>
                            <div class="mt-1 text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Employees net pay</div>
                        </div>
                    </div>
                </div>

                <div class="border-t border-gray-200 pt-5 text-center dark:border-gray-800 md:border-s md:border-t-0 md:ps-6 md:pt-0">
                    <div class="text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Pay day</div>
                    <div class="mt-2 text-3xl font-semibold text-gray-950 dark:text-white">{{ $this->record->pay_date?->format('d') ?? '-' }}</div>
                    <div class="mt-1 text-sm font-medium text-gray-700 dark:text-gray-300">{{ $summary['pay_day'] }}</div>
                    <div class="mt-4 border-t border-gray-200 pt-3 text-sm text-gray-700 dark:border-gray-800">{{ $summary['employee_count'] }} Employees</div>
                </div>

                <div class="border-t border-gray-200 pt-5 dark:border-gray-800 md:border-s md:border-t-0 md:ps-6 md:pt-0">
                    <div class="text-sm font-semibold text-gray-950 dark:text-white">Taxes & Deductions</div>
                    <dl class="mt-4 space-y-3 text-sm">
                        <div class="flex justify-between gap-4">
                            <dt class="text-gray-600 dark:text-gray-400">Taxes</dt>
                            <dd class="font-medium text-gray-950 dark:text-white">{{ $this->money($summary['taxes']) }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-gray-600 dark:text-gray-400">Deductions</dt>
                            <dd class="font-medium text-gray-950 dark:text-white">{{ $this->money($summary['deductions']) }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-gray-600 dark:text-gray-400">Bonuses</dt>
                            <dd class="font-medium text-gray-950 dark:text-white">{{ $this->money($summary['bonuses']) }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-gray-600 dark:text-gray-400">Benefits</dt>
                            <dd class="font-medium text-gray-950 dark:text-white">{{ $this->money($summary['benefits']) }}</dd>
                        </div>
                    </dl>
                </div>
            </div>
        </x-filament::section>

        <x-filament::section
            heading="All Employees"
            :description="$summary['employee_count'] . ' employees in this payroll'"
            :has-content-el="false"
        >
            <x-slot name="afterHeader">
                <x-filament::input.wrapper class="w-full sm:w-52" inline-prefix prefix-icon="heroicon-o-magnifying-glass">
                    <x-filament::input
                        inlinePrefix
                        type="search"
                        wire:model.live.debounce.300ms="employeeSearch"
                        placeholder="Search employee"
                    />
                </x-filament::input.wrapper>
            </x-slot>

            <div class="space-y-3 md:hidden">
                @forelse ($rows as $row)
                    <div
                        role="button"
                        tabindex="0"
                        class="w-full rounded-lg border border-gray-200 bg-white p-4 text-left shadow-sm transition-colors hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-900 dark:hover:bg-gray-800/60"
                        wire:click="showPayrollDetails({{ $row->id }})"
                        wire:keydown.enter="showPayrollDetails({{ $row->id }})"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <div class="truncate font-medium text-gray-950 dark:text-white">{{ $row->employee?->full_name }}</div>
                                <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ number_format((float) data_get($row->calculation_snapshot, 'paid_days', 0), 0) }} paid days</div>
                            </div>
                            <span class="shrink-0 text-sm font-semibold tabular-nums text-gray-950 dark:text-white">{{ $this->money($row->net_pay) }}</span>
                        </div>

                        <dl class="mt-4 grid grid-cols-2 gap-3 text-xs">
                            <div>
                                <dt class="text-gray-500 dark:text-gray-400">Gross</dt>
                                <dd class="mt-0.5 font-medium tabular-nums text-gray-800 dark:text-gray-200">{{ $this->money($row->gross_earning) }}</dd>
                            </div>
                            <div>
                                <dt class="text-gray-500 dark:text-gray-400">Deductions</dt>
                                <dd class="mt-0.5 font-medium tabular-nums text-gray-800 dark:text-gray-200">{{ $this->money($row->total_deduction) }}</dd>
                            </div>
                            <div>
                                <dt class="text-gray-500 dark:text-gray-400">Taxes</dt>
                                <dd class="mt-0.5 font-medium tabular-nums text-gray-800 dark:text-gray-200">{{ $this->money($row->income_tax) }}</dd>
                            </div>
                            <div>
                                <dt class="text-gray-500 dark:text-gray-400">Benefits</dt>
                                <dd class="mt-0.5 font-medium tabular-nums text-gray-800 dark:text-gray-200">{{ $this->money((float) $row->transport_allowance) }}</dd>
                            </div>
                        </dl>

                        <div class="mt-4 flex justify-end" x-on:click.stop>
                            <x-filament::icon-button
                                color="danger"
                                icon="heroicon-o-trash"
                                label="Remove from payroll"
                                wire:click="removePayrollEmployee({{ $row->id }})"
                            />
                        </div>
                    </div>
                @empty
                    <div class="rounded-lg border border-dashed border-gray-200 px-4 py-8 text-center text-sm text-gray-500 dark:border-gray-800 dark:text-gray-400">No payroll employees match this view.</div>
                @endforelse
            </div>

            <div class="hidden overflow-x-auto md:block">
                <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-800">
                    <thead class="bg-gray-50 dark:bg-gray-950">
                            <tr>
                                @foreach (['Employee name', 'Paid days', 'Gross pay', 'Deductions', 'Taxes', 'Benefits', 'Net pay', ''] as $heading)
                                    <th class="px-5 py-3 text-left text-xs font-medium uppercase tracking-normal text-gray-500 dark:text-gray-400">{{ $heading }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 bg-white dark:divide-gray-800 dark:bg-gray-900">
                            @forelse ($rows as $row)
                                <tr
                                    class="cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-800/60"
                                    wire:click="showPayrollDetails({{ $row->id }})"
                                >
                                    <td class="whitespace-nowrap px-5 py-4 font-medium text-gray-950 dark:text-white">
                                        {{ $row->employee?->full_name }}
                                    </td>
                                    <td class="whitespace-nowrap px-5 py-4 text-gray-700 dark:text-gray-300">{{ number_format((float) data_get($row->calculation_snapshot, 'paid_days', 0), 0) }}</td>
                                    <td class="whitespace-nowrap px-5 py-4 text-gray-700 dark:text-gray-300">{{ $this->money($row->gross_earning) }}</td>
                                    <td class="whitespace-nowrap px-5 py-4 text-gray-700 dark:text-gray-300">{{ $this->money($row->total_deduction) }}</td>
                                    <td class="whitespace-nowrap px-5 py-4 text-gray-700 dark:text-gray-300">{{ $this->money($row->income_tax) }}</td>
                                    <td class="whitespace-nowrap px-5 py-4 text-gray-700 dark:text-gray-300">{{ $this->money((float) $row->transport_allowance) }}</td>
                                    <td class="whitespace-nowrap px-5 py-4 font-semibold text-gray-950 dark:text-white">{{ $this->money($row->net_pay) }}</td>
                                    <td class="whitespace-nowrap px-5 py-4 text-right" x-on:click.stop>
                                        <x-filament::icon-button
                                            color="danger"
                                            icon="heroicon-o-trash"
                                            label="Remove from payroll"
                                            wire:click="removePayrollEmployee({{ $row->id }})"
                                        />
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="px-5 py-10 text-center text-sm text-gray-500 dark:text-gray-400">No payroll employees match this view.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
            </div>
        </x-filament::section>
    </div>

    @if ($detail)
        <div
            x-data="{ open: false }"
            x-init="$nextTick(() => open = true)"
            x-show="open"
            x-transition.opacity.duration.150ms
            class="payroll-detail-overlay fixed inset-0 bg-gray-950/30"
            x-on:click.self="open = false; setTimeout(() => $wire.closePayrollDetails(), 160)"
        >
            <aside
                x-show="open"
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="translate-x-full opacity-80"
                x-transition:enter-end="translate-x-0 opacity-100"
                x-transition:leave="transition ease-in duration-150"
                x-transition:leave-start="translate-x-0 opacity-100"
                x-transition:leave-end="translate-x-full opacity-80"
                class="ml-auto flex h-full w-full max-w-[30rem] flex-col overflow-hidden bg-white shadow-xl dark:bg-gray-900"
            >
                <div class="bg-primary-50 px-5 py-3">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <h2 class="text-lg font-semibold text-primary-600 dark:text-primary-400">{{ $detail->employee?->full_name }}</h2>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                Payable Days: {{ number_format((float) data_get($detail->calculation_snapshot, 'paid_days', 0), 0) }}
                                LOP: {{ number_format(max(0, (float) data_get($detail->calculation_snapshot, 'base_days', 0) - (float) data_get($detail->calculation_snapshot, 'paid_days', 0)), 0) }}
                            </p>
                        </div>
                        <button
                            type="button"
                            class="flex h-10 w-10 items-center justify-center rounded-md text-gray-400 transition-colors hover:bg-gray-100 hover:text-gray-700 dark:hover:bg-gray-800 dark:hover:text-gray-200"
                            x-on:click="open = false; setTimeout(() => $wire.closePayrollDetails(), 160)"
                            aria-label="Close"
                        >
                            <x-filament::icon icon="heroicon-m-x-mark" class="h-5 w-5" />
                        </button>
                    </div>
                </div>

                <div class="flex-1 space-y-6 overflow-y-auto px-5 py-4 text-sm pr-8">
                    <div>
                        <div class="flex items-center justify-between border-b border-t border-gray-200 py-2 dark:border-gray-800 mb-4">
                            <h3 class="text-xs font-semibold uppercase text-success-600 dark:text-success-400">(+) Earnings</h3>
                            <span class="text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">Amount</span>
                        </div>

                        <div>
                            @foreach ($this->earningBreakdown($detail) as $item)
                                <div class="group relative min-h-10 py-2 pr-10">
                                    <div>
                                        <div class="font-medium text-gray-950 dark:text-white">{{ $item['label'] }}</div>
                                    </div>
                                        <div class="absolute inset-y-0 right-0 flex items-center">
                                        @if ($this->isEditingLine('earning', $item))
                                            <x-filament::input.wrapper class="absolute right-0 top-1/2 w-28 -translate-y-1/2">
                                                <x-filament::input
                                                    type="number"
                                                    step="{{ ($item['suffix'] ?? null) ? '1' : '0.01' }}"
                                                    x-init="$nextTick(() => $el.focus())"
                                                    wire:model.blur="detailForm.{{ $item['field'] ?? 'manual_earnings.' . $item['index'] . '.amount' }}"
                                                    wire:keydown.enter="updatePayrollDetailLine"
                                                    x-on:blur="$wire.updatePayrollDetailLine()"
                                                />
                                            </x-filament::input.wrapper>
                                        @else
                                            <div class="font-semibold tabular-nums text-gray-950 dark:text-white">{{ $this->money($item['amount']) }}</div>
                                        @endif

                                        @if ($item['editable'] ?? false)
                                            @if ($this->isEditingLine('earning', $item))
                                                <x-filament::icon-button
                                                    class="absolute"
                                                    style="right: -1.2rem"
                                                    size="xs"
                                                    color="danger"
                                                    icon="heroicon-o-minus-circle"
                                                    label="Remove {{ $item['label'] }}"
                                                    wire:click="removePayrollDetailLineItem('earning', '{{ $item['code'] }}', {{ $item['index'] ?? 'null' }})"
                                                />
                                            @else
                                                <x-filament::icon-button
                                                    class="absolute opacity-0 transition-opacity group-hover:opacity-100 group-focus-within:opacity-100"
                                                    size="xs"
                                                    style="right: -1.2rem"
                                                    color="gray"
                                                    icon="heroicon-o-pencil-square"
                                                    label="Edit {{ $item['label'] }}"
                                                    wire:click="editPayrollDetailLine('earning', '{{ $item['code'] }}', {{ $item['index'] ?? 'null' }})"
                                                />
                                            @endif
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        <div class="inline-flex w-fit">
                            <x-filament::dropdown placement="bottom-start" teleport>
                                <x-slot name="trigger">
                                    <x-filament::link size="sm" color="primary" icon="heroicon-o-plus-circle">
                                        Add Earning
                                    </x-filament::link>
                                </x-slot>

                                <x-filament::dropdown.list>
                                    @forelse ($this->earningTypeOptions() as $value => $label)
                                        <x-filament::dropdown.list.item wire:click="addPayrollDetailLineItem('earning', '{{ $value }}')">
                                            {{ $label }}
                                        </x-filament::dropdown.list.item>
                                    @empty
                                        <div class="px-3 py-2 text-sm text-gray-500 dark:text-gray-400">No earnings to add.</div>
                                    @endforelse
                                </x-filament::dropdown.list>
                            </x-filament::dropdown>
                        </div>
                    </div>

                    <div>
                        <div class="flex items-center justify-between border-b border-t border-gray-200 py-2 dark:border-gray-800 mb-4">
                            <h3 class="text-xs font-semibold uppercase text-danger-600 dark:text-danger-400">(-) Deductions</h3>
                            <span class="text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">Amount</span>
                        </div>

                        <div>
                            @foreach ($this->deductionBreakdown($detail) as $item)
                                <div class="group relative min-h-10 py-2 pr-10">
                                    <div>
                                        <div class="font-medium text-gray-950 dark:text-white">{{ $item['label'] }}</div>
                                    </div>
                                    <div class="absolute inset-y-0 right-0 flex items-center">
                                        @if ($this->isEditingLine('deduction', $item))
                                            <x-filament::input.wrapper class="absolute right-0 top-1/2 w-28 -translate-y-1/2">
                                                <x-filament::input
                                                    type="number"
                                                    step="{{ ($item['suffix'] ?? null) ? '1' : '0.01' }}"
                                                    x-init="$nextTick(() => $el.focus())"
                                                    wire:model.blur="detailForm.{{ $item['field'] ?? 'manual_deductions.' . $item['index'] . '.amount' }}"
                                                    wire:keydown.enter="updatePayrollDetailLine"
                                                    x-on:blur="$wire.updatePayrollDetailLine()"
                                                />
                                            </x-filament::input.wrapper>
                                        @else
                                            <div class="font-semibold tabular-nums text-gray-950 dark:text-white">{{ $this->money($item['amount']) }}</div>
                                        @endif

                                        @if ($item['editable'] ?? false)
                                            @if ($this->isEditingLine('deduction', $item))
                                                <x-filament::icon-button
                                                    class="absolute"
                                                    style="right: -1.2rem"
                                                    size="xs"
                                                    color="danger"
                                                    icon="heroicon-o-minus-circle"
                                                    label="Remove {{ $item['label'] }}"
                                                    wire:click="removePayrollDetailLineItem('deduction', '{{ $item['code'] }}', {{ $item['index'] ?? 'null' }})"
                                                />
                                            @else
                                                <x-filament::icon-button
                                                    class="absolute opacity-0 transition-opacity group-hover:opacity-100 group-focus-within:opacity-100"
                                                    style="right: -1.2rem"
                                                    size="xs"
                                                    color="gray"
                                                    icon="heroicon-o-pencil-square"
                                                    label="Edit {{ $item['label'] }}"
                                                    wire:click="editPayrollDetailLine('deduction', '{{ $item['code'] }}', {{ $item['index'] ?? 'null' }})"
                                                />
                                            @endif
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        <div class="inline-flex w-fit">
                            <x-filament::dropdown placement="bottom-start" teleport>
                                <x-slot name="trigger">
                                    <x-filament::link size="sm" color="primary" icon="heroicon-o-plus-circle">
                                        Add Deduction
                                    </x-filament::link>
                                </x-slot>

                                <x-filament::dropdown.list>
                                    @forelse ($this->deductionTypeOptions() as $value => $label)
                                        <x-filament::dropdown.list.item wire:click="addPayrollDetailLineItem('deduction', '{{ $value }}')">
                                            {{ $label }}
                                        </x-filament::dropdown.list.item>
                                    @empty
                                        <div class="px-3 py-2 text-sm text-gray-500 dark:text-gray-400">No deductions to add.</div>
                                    @endforelse
                                </x-filament::dropdown.list>
                            </x-filament::dropdown>
                        </div>
                    </div>
                </div>
                <div class="flex items-center justify-between text-md border-t border-gray-200 px-5 py-4 dark:border-gray-800">
                    <span class="font-semibold text-gray-950 dark:text-white">Net pay</span>
                    <span class="font-semibold tabular-nums text-gray-950 dark:text-white">{{ $this->money($detail->net_pay) }}</span>
                </div>
            </aside>
        </div>
    @endif

    @if ($reviewingOvertime)
        <div
            x-data="{ open: false }"
            x-init="$nextTick(() => open = true)"
            x-show="open"
            x-transition.opacity.duration.150ms
            class="fixed inset-0 z-[120] flex items-center justify-center bg-gray-950/30 p-4"
            x-on:click.self="open = false; setTimeout(() => $wire.closeOvertimeReview(), 160)"
        >
            <div
                x-show="open"
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="translate-y-2 opacity-0"
                x-transition:enter-end="translate-y-0 opacity-100"
                x-transition:leave="transition ease-in duration-150"
                x-transition:leave-start="translate-y-0 opacity-100"
                x-transition:leave-end="translate-y-2 opacity-0"
                class="flex max-h-[85vh] w-full max-w-4xl flex-col overflow-hidden rounded-xl bg-white shadow-xl ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10"
            >
                <div class="flex items-start justify-between gap-4 border-b border-gray-200 px-5 py-4 dark:border-gray-800">
                    <div>
                        <h2 class="text-base font-semibold text-gray-950 dark:text-white">Review overtime</h2>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            {{ $this->pendingOvertimeApprovalCount() }} pending candidates. Pending and rejected overtime is not paid.
                        </p>
                    </div>
                    <button
                        type="button"
                        class="flex h-9 w-9 items-center justify-center rounded-md text-gray-400 transition-colors hover:bg-gray-100 hover:text-gray-700 dark:hover:bg-gray-800 dark:hover:text-gray-200"
                        x-on:click="open = false; setTimeout(() => $wire.closeOvertimeReview(), 160)"
                        aria-label="Close"
                    >
                        <x-filament::icon icon="heroicon-m-x-mark" class="h-5 w-5" />
                    </button>
                </div>

                <div class="overflow-y-auto">
                    <div class="hidden border-b border-gray-200 bg-gray-50 px-5 py-2 text-xs font-medium uppercase text-gray-500 dark:border-gray-800 dark:bg-gray-950 dark:text-gray-400 md:grid md:grid-cols-[1.2fr_1fr_0.8fr_0.7fr_0.8fr_0.8fr] md:gap-4">
                        <div>Employee</div>
                        <div>Type</div>
                        <div>Date</div>
                        <div>Hours</div>
                        <div>Amount</div>
                        <div>Status</div>
                    </div>

                    @forelse ($this->overtimeReviewEntries() as $entry)
                        <div class="grid gap-3 border-b border-gray-100 px-5 py-4 text-sm dark:border-gray-800 md:grid-cols-[1.2fr_1fr_0.8fr_0.7fr_0.8fr_0.8fr] md:items-center md:gap-4">
                            <div class="min-w-0">
                                <div class="font-medium text-gray-950 dark:text-white">{{ $entry->employee?->full_name }}</div>
                                <div class="mt-1 text-xs text-gray-500 dark:text-gray-400 md:hidden">{{ $entry->overtimeRule?->name }}</div>
                            </div>
                            <div class="hidden text-gray-700 dark:text-gray-300 md:block">{{ $entry->overtimeRule?->name }}</div>
                            <div class="text-gray-600 dark:text-gray-400">{{ $entry->date?->format('M j') ?? 'Period' }}</div>
                            <div class="font-medium tabular-nums text-gray-950 dark:text-white">{{ number_format((float) $entry->hours, 2) }}</div>
                            <div class="font-semibold tabular-nums text-gray-950 dark:text-white">{{ $this->money($entry->amount) }}</div>
                            <div class="flex items-center justify-between gap-3">
                                <x-filament::badge :color="$this->overtimeStatusColor($entry->status)">
                                    {{ str($entry->status)->headline() }}
                                </x-filament::badge>

                                @if ($this->record->status === 'draft')
                                    <div class="flex items-center gap-1">
                                        <x-filament::icon-button
                                            color="success"
                                            icon="heroicon-m-check"
                                            label="Approve overtime"
                                            size="sm"
                                            wire:click="approveOvertimeEntry({{ $entry->id }})"
                                        />
                                        <x-filament::icon-button
                                            color="danger"
                                            icon="heroicon-m-x-mark"
                                            label="Reject overtime"
                                            size="sm"
                                            wire:click="rejectOvertimeEntry({{ $entry->id }})"
                                        />
                                    </div>
                                @endif
                            </div>
                        </div>
                    @empty
                        <div class="px-5 py-12 text-center text-sm text-gray-500 dark:text-gray-400">
                            No overtime candidates have been detected for this payroll.
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    @endif
</x-filament-panels::page>
