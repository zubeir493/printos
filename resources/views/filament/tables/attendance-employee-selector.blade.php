@php
    $employees = \App\Models\Employee::query()
        ->orderBy('first_name')
        ->orderBy('last_name')
        ->get(['id', 'employee_id', 'first_name', 'last_name']);
@endphp

<div class="fi-w-64">
    <x-filament::input.wrapper>
        <x-filament::input.select
            aria-label="Employee"
            wire:model.live="selectedEmployeeId"
        >
            @foreach ($employees as $employee)
                <option value="{{ $employee->id }}">
                    {{ $employee->full_name }} @if ($employee->employee_id)
                        ({{ $employee->employee_id }})
                    @endif
                </option>
            @endforeach
        </x-filament::input.select>
    </x-filament::input.wrapper>
</div>
