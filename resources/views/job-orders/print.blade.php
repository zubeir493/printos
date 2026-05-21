<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Job Order {{ $jobOrderPrint['job_order_number'] }}</title>
    <style>
        body {
            color: #1f2937;
            font-family: Arial, sans-serif;
            font-size: 12px;
            line-height: 1.45;
            margin: 0;
        }

        .page {
            padding: 32px;
        }

        .header {
            border-bottom: 2px solid #111827;
            margin-bottom: 20px;
            padding-bottom: 12px;
        }

        .brand {
            color: #4b5563;
            float: right;
            font-size: 10px;
            line-height: 1.35;
            text-align: right;
            width: 260px;
        }

        h1 {
            font-size: 24px;
            margin: 0 0 4px;
        }

        h2 {
            border-bottom: 1px solid #d1d5db;
            font-size: 15px;
            margin: 20px 0 8px;
            padding-bottom: 4px;
        }

        table {
            border-collapse: collapse;
            margin-bottom: 12px;
            width: 100%;
        }

        th,
        td {
            border: 1px solid #d1d5db;
            padding: 6px 8px;
            text-align: left;
            vertical-align: top;
        }

        th {
            background: #f3f4f6;
            font-weight: bold;
        }

        .muted {
            color: #6b7280;
        }

        .meta-table td:first-child {
            background: #f9fafb;
            font-weight: bold;
            width: 28%;
        }

        .task {
            margin-bottom: 18px;
            page-break-inside: avoid;
        }

        .task-title {
            background: #111827;
            color: #ffffff;
            font-weight: bold;
            padding: 8px 10px;
        }

        .empty {
            color: #6b7280;
            font-style: italic;
        }

        .services-table td {
            border: none;
            padding: 3px 8px 3px 0;
            width: 50%;
        }

        .checkmark {
            color: #111827;
            font-family: 'DejaVu Sans', sans-serif;
            font-weight: bold;
            padding-right: 5px;
        }
    </style>
</head>
<body>
    <div class="page">
        <div class="header">
            <div class="brand">
                <strong>{{ $jobOrderPrint['company']['name'] ?? $jobOrderPrint['app_name'] }}</strong><br>
                {{ $jobOrderPrint['app_name'] }}
            </div>
            <h1>Job Order {{ $jobOrderPrint['job_order_number'] }}</h1>
            <div class="muted">Production printout</div>
        </div>

        <table class="meta-table">
            <tr>
                <td>Created</td>
                <td>{{ $jobOrderPrint['created_at'] ?? 'Not set' }}</td>
                <td>Submission Date</td>
                <td>{{ $jobOrderPrint['submission_date'] ?? 'Not set' }}</td>
            </tr>
            <tr>
                <td>Deadline</td>
                <td>{{ $jobOrderPrint['due_date'] ?? 'Not set' }}</td>
                <td>Client</td>
                <td>{{ $jobOrderPrint['client']['name'] }}</td>
            </tr>
            <tr>
                <td>Client Contact</td>
                <td colspan="3">
                    {{ $jobOrderPrint['client']['phone'] ?? 'No phone' }}
                    @if($jobOrderPrint['client']['email'])
                        <br>{{ $jobOrderPrint['client']['email'] }}
                    @endif
                    @if($jobOrderPrint['client']['address'])
                        <br>{{ $jobOrderPrint['client']['address'] }}
                    @endif
                </td>
            </tr>
        </table>

        <h2>Tasks And Required Materials</h2>
        @forelse($jobOrderPrint['tasks'] as $task)
            <div class="task">
                <div class="task-title">{{ $task['name'] }}</div>
                <table>
                    <tr>
                        <td><strong>Quantity</strong></td>
                        <td>{{ $task['quantity'] ?? 'Not set' }}</td>
                        <td><strong>Size</strong></td>
                        <td>{{ $task['size'] ?? 'Not set' }}</td>
                    </tr>
                    @if($task['instructions'])
                        <tr>
                            <td><strong>Instructions</strong></td>
                            <td colspan="3">{{ $task['instructions'] }}</td>
                        </tr>
                    @endif
                </table>

                @if(count($task['materials']))
                    <table>
                        <thead>
                            <tr>
                                <th>Material</th>
                                <th>SKU</th>
                                <th>Required</th>
                                <th>Reserve</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($task['materials'] as $material)
                                <tr>
                                    <td>{{ $material['material_name'] }}</td>
                                    <td>{{ $material['sku'] ?? '-' }}</td>
                                    <td>{{ number_format($material['required_quantity'], 2) }} {{ $material['unit'] }}</td>
                                    <td>{{ $material['reserve_quantity'] === null ? '-' : number_format($material['reserve_quantity'], 2).' '.$material['unit'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @else
                    <p class="empty">No required materials recorded for this task.</p>
                @endif
            </div>
        @empty
            <p class="empty">No tasks recorded.</p>
        @endforelse

        <h2>Additional Services</h2>
        @if(count($jobOrderPrint['services']))
            <table class="services-table">
                @foreach(array_chunk($jobOrderPrint['services'], 2) as $serviceRow)
                    <tr>
                        @foreach($serviceRow as $service)
                            <td><span class="checkmark">&check;</span>{{ $service }}</td>
                        @endforeach
                        @if(count($serviceRow) === 1)
                            <td></td>
                        @endif
                    </tr>
                @endforeach
            </table>
        @else
            <p class="empty">No additional services recorded.</p>
        @endif

        <h2>Remarks</h2>
        <p>{{ $jobOrderPrint['remarks'] ?: 'No remarks recorded.' }}</p>
    </div>
</body>
</html>
